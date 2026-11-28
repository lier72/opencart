import { createHmac, timingSafeEqual } from 'node:crypto';

export interface VerifySignedRequestParams {
  rawBody: string;
  timestamp: string | undefined;
  signature: string | undefined;
  secret: string;
  replayWindowSeconds: number;
}

export type VerifyResult = { ok: true } | { ok: false; reason: string };

/**
 * Verifies the HMAC-SHA256 signature the OpenCart ai_chatbot module proxy
 * attaches to every forwarded request: hash_hmac('sha256', "$ts\n$body", $secret),
 * sent as X-Chatbot-Timestamp / X-Chatbot-Signature. Rejects stale timestamps
 * as a replay-attack defense.
 */
export function verifySignedRequest(params: VerifySignedRequestParams): VerifyResult {
  const { rawBody, timestamp, signature, secret, replayWindowSeconds } = params;

  if (!timestamp || !signature) {
    return { ok: false, reason: 'missing signature headers' };
  }

  const ts = Number(timestamp);
  if (!Number.isFinite(ts)) {
    return { ok: false, reason: 'invalid timestamp' };
  }
  if (Math.abs(Date.now() / 1000 - ts) > replayWindowSeconds) {
    return { ok: false, reason: 'stale timestamp' };
  }

  const expected = createHmac('sha256', secret).update(`${timestamp}\n${rawBody}`).digest();
  let actual: Buffer;
  try {
    actual = Buffer.from(signature, 'hex');
  } catch {
    return { ok: false, reason: 'malformed signature' };
  }

  if (actual.length !== expected.length || !timingSafeEqual(actual, expected)) {
    return { ok: false, reason: 'signature mismatch' };
  }

  return { ok: true };
}
