import OpenAI from 'openai';
import type {
  ChatMessage,
  LLMProvider,
  LLMResponse,
  StopReason,
  ToolCall,
  ToolDefinition,
  ToolResult,
} from './types.js';

export interface OpenAiProviderOptions {
  apiKey: string;
  model: string;
  maxTokens: number;
}

type OpenAiMessageParam = OpenAI.Chat.Completions.ChatCompletionMessageParam;

const FINISH_REASON_MAP: Record<string, StopReason> = {
  stop: 'end',
  tool_calls: 'tool_use',
  length: 'max_tokens',
};

function toOpenAiMessages(messages: ChatMessage[]): OpenAiMessageParam[] {
  return messages.flatMap((m): OpenAiMessageParam[] => {
    if (m.role === 'assistant') {
      const tool_calls = (m.toolCalls ?? []).map((call) => ({
        id: call.id,
        type: 'function' as const,
        function: { name: call.name, arguments: JSON.stringify(call.arguments) },
      }));
      return [
        {
          role: 'assistant',
          content: m.content || null,
          ...(tool_calls.length ? { tool_calls } : {}),
        },
      ];
    }

    if (m.toolResults?.length) {
      // OpenAI expects one role:"tool" message per tool call, not one combined message.
      return m.toolResults.map((r) => ({
        role: 'tool',
        tool_call_id: r.toolCallId,
        content: JSON.stringify(r.output),
      }));
    }

    return [{ role: 'user', content: m.content }];
  });
}

function parseArguments(raw: string): Record<string, unknown> {
  try {
    const parsed = JSON.parse(raw);
    return typeof parsed === 'object' && parsed !== null ? (parsed as Record<string, unknown>) : {};
  } catch {
    return {};
  }
}

/** Provider-agnostic chat/tool-use backend using the OpenAI Chat Completions API. */
export class OpenAiProvider implements LLMProvider {
  readonly name = 'openai';
  private readonly client: OpenAI;

  constructor(private readonly options: OpenAiProviderOptions) {
    this.client = new OpenAI({ apiKey: options.apiKey });
  }

  async sendMessage(messages: ChatMessage[], tools: ToolDefinition[], systemPrompt: string): Promise<LLMResponse> {
    const response = await this.client.chat.completions.create({
      model: this.options.model,
      max_tokens: this.options.maxTokens,
      messages: [{ role: 'system', content: systemPrompt }, ...toOpenAiMessages(messages)],
      tools: tools.map((t) => ({
        type: 'function',
        function: { name: t.name, description: t.description, parameters: t.inputSchema },
      })),
    });

    const choice = response.choices[0];
    const message = choice.message;

    const toolCalls: ToolCall[] = (message.tool_calls ?? [])
      .filter((tc): tc is OpenAI.Chat.Completions.ChatCompletionMessageFunctionToolCall => tc.type === 'function')
      .map((tc) => ({ id: tc.id, name: tc.function.name, arguments: parseArguments(tc.function.arguments) }));

    return {
      text: message.content ?? null,
      toolCalls,
      stopReason: choice.finish_reason ? FINISH_REASON_MAP[choice.finish_reason] ?? 'error' : 'error',
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
