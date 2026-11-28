import { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import { RacketDataSource } from './dataSource.js';
import { searchRacketsInputShape, searchRackets } from './tools/searchRackets.js';
import { getRacketDetailsInputShape, getRacketDetails } from './tools/getRacketDetails.js';
import { compareRacketsInputShape, compareRackets } from './tools/compareRackets.js';

export interface CreateRacketMcpServerOptions {
  storefrontBaseUrl: string;
  racketDataTtlSeconds: number;
}

function asToolResult(payload: unknown) {
  return { content: [{ type: 'text' as const, text: JSON.stringify(payload) }] };
}

/**
 * Builds the MCP server that exposes the racket catalog as tools. Kept
 * separate from transport wiring (index.ts) so it can be reused by tests
 * and by the MCP Inspector without spawning a child process.
 */
export function createRacketMcpServer(options: CreateRacketMcpServerOptions): McpServer {
  const dataSource = new RacketDataSource({
    storefrontBaseUrl: options.storefrontBaseUrl,
    ttlSeconds: options.racketDataTtlSeconds,
  });

  const server = new McpServer({ name: 'racket-advisor-mcp', version: '0.1.0' });

  server.registerTool(
    'search_rackets',
    {
      title: 'Search rackets',
      description:
        'Search the store\'s badminton racket catalog by free-text query and/or spec filters ' +
        '(brand, series, level, flex, feel, weight, balance point, head size, price). ' +
        'Returns matching rackets with their specs, live price and stock status.',
      inputSchema: searchRacketsInputShape,
    },
    async (args) => asToolResult(await searchRackets(args, dataSource))
  );

  server.registerTool(
    'get_racket_details',
    {
      title: 'Get racket details',
      description: 'Get the full spec sheet, live price and stock status for one racket by product_id.',
      inputSchema: getRacketDetailsInputShape,
    },
    async (args) => asToolResult(await getRacketDetails(args, dataSource))
  );

  server.registerTool(
    'compare_rackets',
    {
      title: 'Compare rackets',
      description: 'Compare 2 to 5 rackets side by side by product_id, returning full specs for each.',
      inputSchema: compareRacketsInputShape,
    },
    async (args) => asToolResult(await compareRackets(args, dataSource))
  );

  return server;
}
