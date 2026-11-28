import type { ChatMessage, LLMProvider, ToolResult } from '../llm/types.js';
import type { McpToolClient } from '../mcpClient/client.js';

export interface SuggestedProduct {
  product_id: number;
  display_name: string;
  price: number | null;
  product_url: string | null;
}

export interface TurnResult {
  messages: ChatMessage[];
  finalText: string;
  suggestedProducts: SuggestedProduct[];
}

function isRacketLike(value: unknown): value is SuggestedProduct {
  if (!value || typeof value !== 'object') return false;
  const v = value as Record<string, unknown>;
  return typeof v.product_id === 'number' && typeof v.display_name === 'string';
}

/**
 * Pulls any racket records out of a tool's output, whatever shape the
 * specific tool returned them in (search_rackets' {results:[...]},
 * get_racket_details' single object, compare_rackets' {items:[...]}) —
 * generic on purpose, so adding a tool never requires touching this loop.
 */
function collectSuggestedProducts(output: unknown, into: Map<number, SuggestedProduct>): void {
  if (!output || typeof output !== 'object') return;
  const obj = output as Record<string, unknown>;

  const candidates: unknown[] = [obj];
  for (const value of Object.values(obj)) {
    if (Array.isArray(value)) candidates.push(...value);
  }

  for (const candidate of candidates) {
    if (isRacketLike(candidate)) {
      into.set(candidate.product_id, {
        product_id: candidate.product_id,
        display_name: candidate.display_name,
        price: typeof candidate.price === 'number' ? candidate.price : null,
        product_url: typeof candidate.product_url === 'string' ? candidate.product_url : null,
      });
    }
  }
}

/**
 * Runs the provider-agnostic tool-calling loop for one user turn: ask the
 * LLM, and while it asks for tools, call them through the MCP client and
 * feed the results back, until it produces a final answer or the iteration
 * cap is hit.
 */
export async function runTurn(
  provider: LLMProvider,
  mcp: McpToolClient,
  messages: ChatMessage[],
  systemPrompt: string,
  maxIterations: number
): Promise<TurnResult> {
  const tools = await mcp.listToolsAsDefinitions();
  const suggested = new Map<number, SuggestedProduct>();

  let current = messages;

  for (let i = 0; i < maxIterations; i++) {
    const response = await provider.sendMessage(current, tools, systemPrompt);
    current = provider.appendAssistantTurn(current, response);

    if (response.stopReason !== 'tool_use') {
      return { messages: current, finalText: response.text ?? '', suggestedProducts: [...suggested.values()] };
    }

    const results: ToolResult[] = await Promise.all(
      response.toolCalls.map(async (call) => {
        try {
          const output = await mcp.callTool(call.name, call.arguments);
          collectSuggestedProducts(output, suggested);
          return { toolCallId: call.id, name: call.name, output };
        } catch (err) {
          return {
            toolCallId: call.id,
            name: call.name,
            output: { error: err instanceof Error ? err.message : String(err) },
            isError: true,
          };
        }
      })
    );

    current = provider.appendToolResults(current, results);
  }

  throw new Error(`tool loop exceeded MAX_TOOL_ITERATIONS (${maxIterations})`);
}
