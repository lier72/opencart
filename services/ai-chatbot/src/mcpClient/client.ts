import { Client } from '@modelcontextprotocol/sdk/client/index.js';
import { StdioClientTransport } from '@modelcontextprotocol/sdk/client/stdio.js';
import type { ToolDefinition } from '../llm/types.js';

export interface McpToolClientOptions {
  /** Absolute path to the compiled MCP server entrypoint (mcp-server/dist/src/index.js). */
  serverEntrypoint: string;
  env: Record<string, string>;
}

/**
 * Thin wrapper around the MCP SDK's Client, spawning the racket MCP server
 * as a child process over stdio and keeping one connection for the process
 * lifetime. The orchestrator never talks to the MCP protocol directly —
 * only through listToolsAsDefinitions()/callTool() here.
 */
export class McpToolClient {
  private client: Client | null = null;
  private toolsCache: ToolDefinition[] | null = null;

  constructor(private readonly options: McpToolClientOptions) {}

  async connect(): Promise<void> {
    if (this.client) return;

    const transport = new StdioClientTransport({
      command: process.execPath,
      args: [this.options.serverEntrypoint],
      env: this.options.env,
    });

    const client = new Client({ name: 'ai-chatbot-orchestrator', version: '0.1.0' });
    await client.connect(transport);
    this.client = client;
  }

  async listToolsAsDefinitions(): Promise<ToolDefinition[]> {
    if (this.toolsCache) return this.toolsCache;
    await this.connect();
    const { tools } = await this.client!.listTools();
    this.toolsCache = tools.map((t) => ({
      name: t.name,
      description: t.description ?? '',
      inputSchema: t.inputSchema as Record<string, unknown>,
    }));
    return this.toolsCache;
  }

  async callTool(name: string, args: Record<string, unknown>): Promise<unknown> {
    await this.connect();
    const result = await this.client!.callTool({ name, arguments: args });
    const content = Array.isArray(result.content) ? result.content : [];
    const textBlock = content.find((c): c is { type: 'text'; text: string } => c.type === 'text');

    // An unknown tool name, or a handler that throws, comes back as a normal
    // CallToolResult with isError:true — the SDK does NOT throw for this.
    // Surface it as a thrown error so callers (the orchestrator's tool loop)
    // handle it the same way as a transport/connection failure.
    if (result.isError) {
      throw new Error(textBlock?.text || `tool "${name}" call failed`);
    }

    if (!textBlock) {
      return { error: 'tool returned no text content' };
    }
    try {
      return JSON.parse(textBlock.text);
    } catch {
      return textBlock.text;
    }
  }

  async close(): Promise<void> {
    await this.client?.close();
    this.client = null;
    this.toolsCache = null;
  }
}
