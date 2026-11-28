import type { ChatMessage, LLMProvider, LLMResponse, ToolResult } from './types.js';

/**
 * Test-only provider that replays a scripted sequence of responses, so the
 * orchestrator's HTTP/session/MCP-wiring can be exercised end-to-end without
 * spending real LLM API credits (Phase 2 of the build plan).
 */
export class FakeProvider implements LLMProvider {
  readonly name = 'fake';
  private cursor = 0;

  constructor(private readonly scripted: LLMResponse[]) {}

  async sendMessage(): Promise<LLMResponse> {
    if (this.cursor >= this.scripted.length) {
      throw new Error('FakeProvider: ran out of scripted responses — add more to the test script');
    }
    return this.scripted[this.cursor++];
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
