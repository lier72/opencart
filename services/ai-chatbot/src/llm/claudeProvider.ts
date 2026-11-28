import Anthropic from '@anthropic-ai/sdk';
import type {
  ChatMessage,
  LLMProvider,
  LLMResponse,
  StopReason,
  ToolCall,
  ToolDefinition,
  ToolResult,
} from './types.js';

export interface ClaudeProviderOptions {
  apiKey: string;
  model: string;
  maxTokens: number;
}

const STOP_REASON_MAP: Record<string, StopReason> = {
  end_turn: 'end',
  stop_sequence: 'end',
  tool_use: 'tool_use',
  max_tokens: 'max_tokens',
};

type ClaudeMessageParam = Anthropic.Messages.MessageParam;
type ClaudeContentBlockParam =
  | Anthropic.Messages.TextBlockParam
  | Anthropic.Messages.ToolUseBlockParam
  | Anthropic.Messages.ToolResultBlockParam;

function toClaudeMessages(messages: ChatMessage[]): ClaudeMessageParam[] {
  return messages.map((m): ClaudeMessageParam => {
    if (m.role === 'assistant') {
      const blocks: ClaudeContentBlockParam[] = [];
      if (m.content) blocks.push({ type: 'text', text: m.content });
      for (const call of m.toolCalls ?? []) {
        blocks.push({ type: 'tool_use', id: call.id, name: call.name, input: call.arguments });
      }
      return { role: 'assistant', content: blocks.length ? blocks : m.content };
    }

    if (m.toolResults?.length) {
      const blocks: ClaudeContentBlockParam[] = m.toolResults.map((r) => ({
        type: 'tool_result',
        tool_use_id: r.toolCallId,
        content: JSON.stringify(r.output),
        is_error: r.isError,
      }));
      return { role: 'user', content: blocks };
    }

    return { role: 'user', content: m.content };
  });
}

/** Provider-agnostic chat/tool-use backend using the Anthropic Messages API. */
export class ClaudeProvider implements LLMProvider {
  readonly name = 'anthropic';
  private readonly client: Anthropic;

  constructor(private readonly options: ClaudeProviderOptions) {
    this.client = new Anthropic({ apiKey: options.apiKey });
  }

  async sendMessage(messages: ChatMessage[], tools: ToolDefinition[], systemPrompt: string): Promise<LLMResponse> {
    const response = await this.client.messages.create({
      model: this.options.model,
      max_tokens: this.options.maxTokens,
      system: systemPrompt,
      tools: tools.map((t) => ({ name: t.name, description: t.description, input_schema: t.inputSchema as Anthropic.Messages.Tool.InputSchema })),
      messages: toClaudeMessages(messages),
    });

    const textParts: string[] = [];
    const toolCalls: ToolCall[] = [];
    for (const block of response.content) {
      if (block.type === 'text') {
        textParts.push(block.text);
      } else if (block.type === 'tool_use') {
        toolCalls.push({
          id: block.id,
          name: block.name,
          arguments: (block.input ?? {}) as Record<string, unknown>,
        });
      }
    }

    return {
      text: textParts.length ? textParts.join('\n') : null,
      toolCalls,
      stopReason: response.stop_reason ? STOP_REASON_MAP[response.stop_reason] ?? 'error' : 'error',
      raw: response,
    };
  }

  appendAssistantTurn(messages: ChatMessage[], resp: LLMResponse): ChatMessage[] {
    return [
      ...messages,
      { role: 'assistant', content: resp.text ?? '', toolCalls: resp.toolCalls.length ? resp.toolCalls : undefined },
    ];
  }

  appendToolResults(messages: ChatMessage[], results: ToolResult[]): ChatMessage[] {
    return [...messages, { role: 'user', content: '', toolResults: results }];
  }
}
