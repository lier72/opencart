# ai-chatbot

MCP server + provider-agnostic chat orchestrator behind the storefront's AI racket advisor
(the `extension/module/ai_chatbot` OpenCart module proxies to this service).

## What's here

- `mcp-server/` — an MCP server exposing the store's racket catalog as three tools
  (`search_rackets`, `get_racket_details`, `compare_rackets`). It reads the two URLs the
  existing deterministic Racket Finder widget already serves publicly
  (`catalog/view/javascript/racket_finder/finder-data.json` and
  `index.php?route=extension/module/racket_finder/data`) — no new OpenCart data endpoint,
  and no second copy of the data to keep in sync.
- `src/` — the chat orchestrator: a provider-agnostic tool-calling loop (`orchestrator/loop.ts`)
  that talks to an `LLMProvider` (Claude or OpenAI, picked by `LLM_PROVIDER`) and to the MCP
  server via a real MCP client spawned over stdio (`mcpClient/client.ts`). Exposes one HTTP
  endpoint, `POST /chat`, authenticated with an HMAC signature from the OpenCart proxy.

## Setup

```bash
npm install
cp .env.example .env
# edit .env: set STOREFRONT_BASE_URL, CHAT_SHARED_SECRET, and the API key matching LLM_PROVIDER
```

Generate a shared secret (must match the OpenCart module's "Shared secret" setting):

```bash
node -e "console.log(require('crypto').randomBytes(32).toString('hex'))"
```

## Run

```bash
npm run build
npm start          # starts the HTTP orchestrator; it spawns the MCP server itself
npm run start:mcp  # or: run the MCP server standalone, e.g. for inspection
```

Point the OpenCart `ai_chatbot` module's "Chat service URL" setting at `http://<host>:<PORT>`.

## Test

```bash
npm test
```

Runs against a fixture storefront (`test/fixtures/`) serving trimmed sample data — no live
OpenCart install or LLM API key needed. Covers the data loader and all three tools, the
real MCP stdio round-trip, the provider-agnostic tool loop (via a scripted `FakeProvider`),
HMAC signing/replay rejection, rate limiting, and the full `/chat` HTTP contract.

To exercise it against the real storefront's live data (no LLM involved):

```bash
curl http://localhost/~max/oc3.uniqsport.ru/catalog/view/javascript/racket_finder/finder-data.json
curl http://localhost/~max/oc3.uniqsport.ru/index.php?route=extension/module/racket_finder/data
npx @modelcontextprotocol/inspector node dist/mcp-server/src/index.js
```

To exercise a real conversation end-to-end against a real LLM:

```bash
npm start
curl -X POST http://localhost:8787/chat \
  -H 'Content-Type: application/json' \
  -H "X-Chatbot-Timestamp: $(date +%s)" \
  -H "X-Chatbot-Signature: <hex hmac-sha256 of \"$(date +%s)\n<body>\" with CHAT_SHARED_SECRET>" \
  -d '{"message":"beginner who smashes hard, budget 5000 rubles","conversation_id":"test-1"}'
```

(The OpenCart module computes that signature for you in production; see
`upload/catalog/controller/extension/module/ai_chatbot.php`.)

## Adding shoes/shuttlecocks later (not built yet)

Tool names stay racket-specific (`search_rackets`, not `search_products`) precisely so a
later phase can add sibling tools (`search_shoes`, etc.) by adding a new data source file
under `mcp-server/src/` — the orchestrator, provider adapters, HTTP/auth layer, and the
OpenCart module need no changes.
