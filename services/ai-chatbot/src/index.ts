import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { config } from './config.js';
import { createLlmProvider } from './llm/factory.js';
import { McpToolClient } from './mcpClient/client.js';
import { ChatSessionStore } from './orchestrator/chatSession.js';
import { RateLimiter } from './http/rateLimit.js';
import { createChatHttpServer } from './http/server.js';

const here = path.dirname(fileURLToPath(import.meta.url));
// This file compiles to dist/src/index.js; the MCP server compiles alongside
// it to dist/mcp-server/src/index.js.
const mcpServerEntrypoint = path.join(here, '..', 'mcp-server', 'src', 'index.js');

async function main() {
  if (!config.chatSharedSecret) {
    throw new Error('CHAT_SHARED_SECRET must be set — it authenticates requests from the OpenCart proxy');
  }

  const provider = createLlmProvider();

  const mcp = new McpToolClient({
    serverEntrypoint: mcpServerEntrypoint,
    env: {
      STOREFRONT_BASE_URL: config.storefrontBaseUrl,
      RACKET_DATA_TTL_SECONDS: String(config.racketDataTtlSeconds),
    },
  });
  await mcp.connect();

  const sessions = new ChatSessionStore(config.conversationTtlSeconds * 1000, config.conversationMaxMessages);
  const rateLimiter = new RateLimiter(config.rateLimitMaxRequests, config.rateLimitWindowSeconds * 1000);

  const server = createChatHttpServer({
    provider,
    mcp,
    sessions,
    rateLimiter,
    sharedSecret: config.chatSharedSecret,
    hmacReplayWindowSeconds: config.hmacReplayWindowSeconds,
    maxToolIterations: config.maxToolIterations,
  });

  server.listen(config.port, () => {
    console.log(`[ai-chatbot] listening on :${config.port}, provider=${provider.name}`);
  });

  const shutdown = async () => {
    console.log('[ai-chatbot] shutting down...');
    sessions.stop();
    rateLimiter.stop();
    await mcp.close();
    server.close(() => process.exit(0));
  };
  process.on('SIGINT', shutdown);
  process.on('SIGTERM', shutdown);
}

main().catch((err) => {
  console.error('[ai-chatbot] fatal error during startup:', err);
  process.exit(1);
});
