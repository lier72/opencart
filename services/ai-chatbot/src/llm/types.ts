/**
 * Provider-agnostic chat/tool-use types. The orchestrator (orchestrator/loop.ts)
 * only ever sees these shapes; each LLMProvider implementation is responsible
 * for translating to/from its own wire format (Claude's content-block messages,
 * OpenAI's tool_calls/role:"tool" messages, ...).
 */

export interface ToolCall {
  id: string;
  name: string;
  arguments: Record<string, unknown>;
}

export interface ToolResult {
  toolCallId: string;
  name: string;
  output: unknown;
  isError?: boolean;
}

/**
 * One turn of the conversation.
 * - A plain user/assistant turn has only `content`.
 * - An assistant turn that invoked tools has `toolCalls` set (content may be '' if the
 *   model emitted no text alongside the tool calls).
 * - The turn that answers those calls is a role:'user' message with `toolResults` set
 *   (content is unused) — kept as ONE message with potentially several results, because
 *   Claude requires all tool_result blocks answering one assistant turn to be combined
 *   into a single user message.
 */
export interface ChatMessage {
  role: 'user' | 'assistant';
  content: string;
  toolCalls?: ToolCall[];
  toolResults?: ToolResult[];
}

/** Mirrors exactly what the MCP server's tools/list returns — never hand-written. */
export interface ToolDefinition {
  name: string;
  description: string;
  inputSchema: Record<string, unknown>;
}

export type StopReason = 'end' | 'tool_use' | 'max_tokens' | 'error';

export interface LLMResponse {
  text: string | null;
  toolCalls: ToolCall[];
  stopReason: StopReason;
  raw: unknown; // provider-native response, for logging only
}

export interface LLMProvider {
  readonly name: string;

  sendMessage(messages: ChatMessage[], tools: ToolDefinition[], systemPrompt: string): Promise<LLMResponse>;

  /** Appends the assistant's turn (text and/or tool calls) to the running history. */
  appendAssistantTurn(messages: ChatMessage[], resp: LLMResponse): ChatMessage[];

  /** Appends the (single, combined) tool-results turn answering the preceding assistant turn. */
  appendToolResults(messages: ChatMessage[], results: ToolResult[]): ChatMessage[];
}
