import 'dotenv/config';
import { StdioServerTransport } from '@modelcontextprotocol/sdk/server/stdio.js';
import { createRacketMcpServer } from './server.js';

/**
 * Entrypoint for running the racket MCP server as a standalone stdio process
 * — either spawned by the chat orchestrator's mcpClient, or run directly for
 * manual testing with `npx @modelcontextprotocol/inspector`.
 */
async function main() {
  const server = createRacketMcpServer({
    storefrontBaseUrl: (process.env.STOREFRONT_BASE_URL || 'http://localhost').replace(/\/+$/, ''),
    racketDataTtlSeconds: Number.parseInt(process.env.RACKET_DATA_TTL_SECONDS || '600', 10),
  });

  const transport = new StdioServerTransport();
  await server.connect(transport);
}

main().catch((err) => {
  console.error('[mcp-server] fatal error:', err);
  process.exit(1);
});
