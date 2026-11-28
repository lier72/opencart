import { config, assertLlmCredentials } from '../config.js';
import type { LLMProvider } from './types.js';
import { ClaudeProvider } from './claudeProvider.js';
import { OpenAiProvider } from './openaiProvider.js';

const DEFAULT_MODELS = {
  anthropic: 'claude-sonnet-5-5',
  openai: 'gpt-5',
} as const;

/**
 * Selects the LLM backend via the LLM_PROVIDER env var. This one switch is
 * the entire "swap provider" story — nothing else in the orchestrator knows
 * or cares which provider is behind the LLMProvider interface.
 */
export function createLlmProvider(): LLMProvider {
  assertLlmCredentials();

  if (config.llmProvider === 'anthropic') {
    return new ClaudeProvider({
      apiKey: config.anthropicApiKey,
      model: config.llmModel || DEFAULT_MODELS.anthropic,
      maxTokens: config.maxResponseTokens,
    });
  }

  return new OpenAiProvider({
    apiKey: config.openaiApiKey,
    model: config.llmModel || DEFAULT_MODELS.openai,
    maxTokens: config.maxResponseTokens,
  });
}
