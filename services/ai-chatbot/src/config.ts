import 'dotenv/config';

function required(name: string): string {
  const value = process.env[name];
  if (!value) {
    throw new Error(`Missing required environment variable: ${name}`);
  }
  return value;
}

function int(name: string, fallback: number): number {
  const raw = process.env[name];
  if (!raw) return fallback;
  const n = Number.parseInt(raw, 10);
  if (!Number.isFinite(n)) {
    throw new Error(`Environment variable ${name} must be an integer, got: ${raw}`);
  }
  return n;
}

export type LlmProviderName = 'anthropic' | 'openai';

function llmProvider(): LlmProviderName {
  const raw = (process.env.LLM_PROVIDER || 'anthropic').toLowerCase();
  if (raw !== 'anthropic' && raw !== 'openai') {
    throw new Error(`LLM_PROVIDER must be "anthropic" or "openai", got: ${raw}`);
  }
  return raw;
}

export const config = {
  llmProvider: llmProvider(),
  anthropicApiKey: process.env.ANTHROPIC_API_KEY || '',
  openaiApiKey: process.env.OPENAI_API_KEY || '',
  llmModel: process.env.LLM_MODEL || '',

  storefrontBaseUrl: (process.env.STOREFRONT_BASE_URL || 'http://localhost').replace(/\/+$/, ''),
  racketDataTtlSeconds: int('RACKET_DATA_TTL_SECONDS', 600),

  port: int('PORT', 8787),
  chatSharedSecret: process.env.CHAT_SHARED_SECRET || '',
  hmacReplayWindowSeconds: int('HMAC_REPLAY_WINDOW_SECONDS', 300),

  maxToolIterations: int('MAX_TOOL_ITERATIONS', 6),
  maxResponseTokens: int('MAX_RESPONSE_TOKENS', 1024),

  conversationTtlSeconds: int('CONVERSATION_TTL_SECONDS', 1800),
  conversationMaxMessages: int('CONVERSATION_MAX_MESSAGES', 40),

  rateLimitMaxRequests: int('RATE_LIMIT_MAX_REQUESTS', 20),
  rateLimitWindowSeconds: int('RATE_LIMIT_WINDOW_SECONDS', 60),
};

/** Fails fast with a clear message instead of a confusing runtime error later. */
export function assertLlmCredentials(): void {
  if (config.llmProvider === 'anthropic') {
    required('ANTHROPIC_API_KEY');
  } else {
    required('OPENAI_API_KEY');
  }
}
