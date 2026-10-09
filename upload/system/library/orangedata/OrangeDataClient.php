<?php
/**
 * OrangeDataClient
 *
 * Low-level HTTP client for the OrangeData cloud fiscalization service (ФЗ-54) and its
 * ТС ПИоТ marking-code verification endpoint (разрешительный режим, постановление РФ № 1944).
 *
 * Responsibilities (and ONLY these — this class is transport, not business logic):
 *   - Establish the mutual-TLS connection using the client's SSL certificate + key.
 *   - Sign request bodies that require it (SHA256-RSA, base64) into the X-Signature header.
 *   - Perform the three calls this integration needs and return the decoded result untouched:
 *       checkPiotCodes()   -> POST /api/v2/piot/codes/check   (разрешительный режим check)
 *       createDocument()   -> POST /api/v2/documents/         (issue the fiscal receipt)
 *       getDocumentStatus()-> GET  /api/v2/documents/{inn}/status/{id} (poll receipt result)
 *
 * Scope of use:
 *   Instantiate from the admin order workflow / event handler and the CLI. It is intentionally
 *   framework-agnostic (no OpenCart registry dependency) so it can be unit-tested and reused from
 *   both the catalog/admin apps and cli/ scripts. Business decisions (which order, which status,
 *   what to do when isSaleAllowed=false) live in the caller, not here.
 *
 * Credentials & endpoints are supplied entirely via the constructor $config so nothing is hard-coded.
 */
class OrangeDataClient {

    /** @var array Normalised configuration (see __construct). */
    private $config;

    /** @var callable|null Optional logger: function(string $level, string $message, array $context). */
    private $logger;

    /**
     * @param array $config {
     *   @type string   base_url        Trailing-slash base, e.g. https://apip.orangedata.ru:12001/api/v2/
     *   @type string   inn             Organisation INN (10 or 12 digits).
     *   @type string   key             Signature key name (usually INN, or INN_ID for new clients).
     *   @type string   group           Device group, e.g. "main_2".
     *   @type string   ssl_cert_path   Absolute path to the client SSL certificate (PEM, client.crt).
     *   @type string   ssl_key_path    Absolute path to the client SSL private key (PEM, client.key).
     *   @type string   ssl_key_pass    Passphrase for ssl_key_path, or '' if none.
     *   @type string   sign_key_pem    PEM string of the RSA private key used for X-Signature signing.
     *   @type array    client_info     ПМСР identity for ТС ПИоТ: name, version, id, token (all required).
     *   @type bool     verify_peer     Verify the server certificate (true in prod; may be false in test).
     *   @type int      timeout         Request timeout in seconds (default 30).
     * }
     * @param callable|null $logger Optional logger callback.
     */
    public function __construct(array $config, $logger = null) {
        $this->config = array_merge(array(
            'base_url'      => '',
            'inn'           => '',
            'key'           => '',
            'group'         => 'main',
            'ssl_cert_path' => '',
            'ssl_key_path'  => '',
            'ssl_key_pass'  => '',
            'sign_key_pem'  => '',
            'client_info'   => array(),
            'verify_peer'   => true,
            'timeout'       => 30,
        ), $config);

        $this->config['base_url'] = rtrim($this->config['base_url'], '/') . '/';
        $this->logger = is_callable($logger) ? $logger : null;
    }

    /* ------------------------------------------------------------------ *
     *  Public API methods
     * ------------------------------------------------------------------ */

    /**
     * checkPiotCodes
     *
     * Runs the разрешительный режим check for one or more marking codes through ТС ПИоТ.
     * This is the call that produces the sale permission (checkResult.isSaleAllowed) and the
     * tag1265 value (UUID=<reqId>&Time=<reqTimestamp>) that must later be written into the receipt.
     *
     * Scope: call this at packing/shipment, once the physical items' DataMatrix codes are scanned,
     * BEFORE createDocument(). The caller decides how to react to each code's isSaleAllowed / statusCode.
     *
     * @param array      $codes       Array of code structures. Each: ['cis' => <base64 of raw KM>,
     *                                 optional 'pg' => <int товарная группа>, optional 'price' => <float>].
     *                                 Use encodeCisForCheck() to build 'cis' from a scanned raw code.
     * @param array|null $clientInfo  Override ПМСР clientInfo; defaults to config['client_info'].
     * @return array ['http_code' => int, 'body' => array|null, 'raw' => string, 'error' => string|null]
     */
    public function checkPiotCodes(array $codes, array $clientInfo = null) {
        $body = array(
            'inn'     => $this->config['inn'],
            'group'   => $this->config['group'],
            'key'     => $this->config['key'],
            'content' => array(
                'codes'      => array_values($codes),
                'clientInfo' => $clientInfo !== null ? $clientInfo : $this->config['client_info'],
            ),
        );

        return $this->request('POST', 'piot/codes/check', $body, true);
    }

    /**
     * createDocument
     *
     * Submits a fiscal receipt (чек) to OrangeData. For marked goods each position must already carry
     * itemCode (tag 1163, the raw KM with GS separators) and, for разрешительный режим, industryAttribute
     * (tag 1260) built from the tag1265 returned by checkPiotCodes().
     *
     * Scope: called after a successful ТС ПИоТ check (or in аварийный режим, without tag 1260). The
     * document 'id' MUST be stable/idempotent for the order+leg so retries return 409 instead of dup receipts.
     *
     * @param array $document Full document payload per API §2.1 (id, inn, group, key, content{...}).
     *                        The caller builds this; the client only adds inn/group/key if omitted.
     * @return array ['http_code' => int (201 ok / 409 dup / 400 / 401), 'body' => array|null, 'raw' => string, 'error' => string|null]
     */
    public function createDocument(array $document) {
        $document += array(
            'inn'   => $this->config['inn'],
            'group' => $this->config['group'],
            'key'   => $this->config['key'],
        );

        return $this->request('POST', 'documents/', $document, true);
    }

    /**
     * getDocumentStatus
     *
     * Polls the processing result of a previously submitted receipt. Returns 202 while the receipt is
     * still queued/unprocessed and 200 with the fiscal result (fp, deviceSN, marking check tag 2106, etc.)
     * once done. This request is NOT signed (per API §2.2).
     *
     * Scope: call on a short poll loop after createDocument() returns 201, until http_code != 202.
     *
     * @param string $documentId The 'id' used when the document was created.
     * @return array ['http_code' => int, 'body' => array|null, 'raw' => string, 'error' => string|null]
     */
    public function getDocumentStatus($documentId) {
        $path = 'documents/' . rawurlencode($this->config['inn']) . '/status/' . rawurlencode($documentId);

        return $this->request('GET', $path, null, false);
    }

    /* ------------------------------------------------------------------ *
     *  Static helpers (pure, no I/O)
     * ------------------------------------------------------------------ */

    /**
     * encodeCisForCheck
     *
     * Builds the 'cis' value for a checkPiotCodes() request. The check endpoint expects the marking
     * code in Base64, whereas the receipt's itemCode expects the RAW code (with GS \x1d separators).
     * Keep the two representations distinct — this helper is only for the check request.
     *
     * @param string $rawKm The marking code exactly as read by the scanner (raw bytes, incl. GS).
     * @return string Base64 of the raw code.
     */
    public static function encodeCisForCheck($rawKm) {
        return base64_encode($rawKm);
    }

    /**
     * buildIndustryAttribute
     *
     * Builds the industryAttribute object (tag 1260) for a marked receipt position under разрешительный
     * режим. foivId/causeDocumentDate/causeDocumentNumber are the fixed values mandated by постановление
     * № 1944; value (tag 1265) is the per-code token from the ТС ПИоТ check response.
     *
     * Scope: called by the receipt builder for every marked position when the check returned 200 and the
     * sale is allowed. Do NOT attach it in аварийный/пост-аварийный режим (statusCode 203).
     *
     * @param string $tag1265Value The tag1265 string, e.g. "UUID=<reqId>&Time=<reqTimestamp>".
     * @return array The industryAttribute structure.
     */
    public static function buildIndustryAttribute($tag1265Value) {
        return array(
            'foivId'              => '030',
            'causeDocumentDate'   => '21.11.2023',
            'causeDocumentNumber' => '1944',
            'value'               => $tag1265Value,
        );
    }

    /**
     * xmlRsaPrivateKeyToPem
     *
     * Converts a .NET RSAKeyValue XML private key (OrangeData's test private_key.xml format) into a
     * PKCS#1 PEM string usable by openssl_sign(). Production keys issued from the OrangeData ЛК are
     * already PEM and need no conversion.
     *
     * Scope: one-time/config-time helper so the admin module can accept either an XML or a PEM key.
     *
     * @param string $xml The <RSAKeyValue>...</RSAKeyValue> XML content.
     * @return string PEM-encoded "RSA PRIVATE KEY".
     * @throws RuntimeException if the XML is missing required private-key components.
     */
    public static function xmlRsaPrivateKeyToPem($xml) {
        $prev = libxml_use_internal_errors(true);
        $doc = simplexml_load_string($xml);
        libxml_use_internal_errors($prev);
        if ($doc === false) {
            throw new RuntimeException('OrangeData: invalid RSA XML key.');
        }

        $get = function ($name) use ($doc) {
            if (!isset($doc->{$name})) {
                throw new RuntimeException('OrangeData: RSA XML key missing <' . $name . '> (a full private key is required).');
            }
            return base64_decode((string) $doc->{$name});
        };

        // PKCS#1 RSAPrivateKey ::= SEQUENCE { version, n, e, d, p, q, dp, dq, qinv }
        $der = self::asn1Sequence(
            self::asn1Integer("\x00") .            // version = 0
            self::asn1Integer($get('Modulus')) .   // n
            self::asn1Integer($get('Exponent')) .  // e
            self::asn1Integer($get('D')) .         // d
            self::asn1Integer($get('P')) .         // p
            self::asn1Integer($get('Q')) .         // q
            self::asn1Integer($get('DP')) .        // dp
            self::asn1Integer($get('DQ')) .        // dq
            self::asn1Integer($get('InverseQ'))    // qinv
        );

        return "-----BEGIN RSA PRIVATE KEY-----\n"
            . chunk_split(base64_encode($der), 64, "\n")
            . "-----END RSA PRIVATE KEY-----\n";
    }

    /* ------------------------------------------------------------------ *
     *  Internals
     * ------------------------------------------------------------------ */

    /**
     * sign
     *
     * Produces the X-Signature value for a request body: base64( RSA-SHA256( body ) ), using the
     * configured signing private key. The signature is computed over the exact bytes that are sent.
     *
     * @param string $body The raw JSON request body (exact bytes transmitted).
     * @return string Base64-encoded signature.
     * @throws RuntimeException on key/signing failure.
     */
    private function sign($body) {
        if ($this->config['sign_key_pem'] === '') {
            throw new RuntimeException('OrangeData: signing key (sign_key_pem) is not configured.');
        }

        $key = openssl_pkey_get_private($this->config['sign_key_pem']);
        if ($key === false) {
            throw new RuntimeException('OrangeData: could not load signing private key: ' . openssl_error_string());
        }

        $signature = '';
        $ok = openssl_sign($body, $signature, $key, OPENSSL_ALGO_SHA256);
        if (PHP_MAJOR_VERSION < 8) {
            openssl_free_key($key);
        }
        if (!$ok) {
            throw new RuntimeException('OrangeData: signing failed: ' . openssl_error_string());
        }

        return base64_encode($signature);
    }

    /**
     * request
     *
     * Performs a single mTLS HTTP request and returns a normalised result array. JSON encoding uses
     * JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES so the signed bytes match exactly what we send.
     *
     * @param string     $method 'GET' or 'POST'.
     * @param string     $path   Path relative to base_url (no leading slash).
     * @param array|null $body   Body to JSON-encode (POST), or null (GET).
     * @param bool       $sign   Whether to attach the X-Signature header.
     * @return array ['http_code' => int, 'body' => array|null, 'raw' => string, 'error' => string|null]
     */
    private function request($method, $path, $body = null, $sign = false) {
        $url = $this->config['base_url'] . ltrim($path, '/');

        $headers = array('Accept: application/json');
        $payload = null;

        if ($body !== null) {
            $payload = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($payload === false) {
                return array('http_code' => 0, 'body' => null, 'raw' => '', 'error' => 'json_encode failed: ' . json_last_error_msg());
            }
            $headers[] = 'Content-Type: application/json';
            if ($sign) {
                $headers[] = 'X-Signature: ' . $this->sign($payload);
            }
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_SSLCERT        => $this->config['ssl_cert_path'],
            CURLOPT_SSLKEY         => $this->config['ssl_key_path'],
            CURLOPT_SSL_VERIFYPEER => $this->config['verify_peer'] ? 2 : 0,
            CURLOPT_SSL_VERIFYHOST => $this->config['verify_peer'] ? 2 : 0,
            CURLOPT_CONNECTTIMEOUT => (int) $this->config['timeout'],
            CURLOPT_TIMEOUT        => (int) $this->config['timeout'],
        ));
        if ($this->config['ssl_key_pass'] !== '') {
            curl_setopt($ch, CURLOPT_KEYPASSWD, $this->config['ssl_key_pass']);
        }
        if ($payload !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        }

        $raw       = curl_exec($ch);
        $httpCode  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr   = curl_errno($ch) ? curl_error($ch) : null;
        curl_close($ch);

        $this->log($curlErr ? 'error' : 'debug', 'OrangeData ' . $method . ' ' . $path, array(
            'http_code' => $httpCode,
            'error'     => $curlErr,
        ));

        $decoded = null;
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $decoded = null; // 201/202 have empty bodies; non-JSON is left as raw.
            }
        }

        return array(
            'http_code' => $httpCode,
            'body'      => $decoded,
            'raw'       => is_string($raw) ? $raw : '',
            'error'     => $curlErr,
        );
    }

    /** Emits a log line through the injected logger, if any. */
    private function log($level, $message, array $context = array()) {
        if ($this->logger) {
            call_user_func($this->logger, $level, $message, $context);
        }
    }

    /* ---- Minimal ASN.1 DER helpers for xmlRsaPrivateKeyToPem() ---- */

    /** DER-encodes a length prefix. */
    private static function asn1Len($length) {
        if ($length < 0x80) {
            return chr($length);
        }
        $bytes = '';
        while ($length > 0) {
            $bytes = chr($length & 0xff) . $bytes;
            $length >>= 8;
        }
        return chr(0x80 | strlen($bytes)) . $bytes;
    }

    /** DER-encodes an INTEGER from raw big-endian bytes (adds a leading 0x00 when the high bit is set). */
    private static function asn1Integer($bytes) {
        $bytes = ltrim($bytes, "\x00");
        if ($bytes === '') {
            $bytes = "\x00";
        }
        if (ord($bytes[0]) & 0x80) {
            $bytes = "\x00" . $bytes;
        }
        return "\x02" . self::asn1Len(strlen($bytes)) . $bytes;
    }

    /** DER-encodes a SEQUENCE wrapping the given contents. */
    private static function asn1Sequence($contents) {
        return "\x30" . self::asn1Len(strlen($contents)) . $contents;
    }
}
