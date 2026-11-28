import { createServer as createHttpServer, type IncomingMessage, type Server } from 'node:http';
import type { LLMProvider } from '../llm/types.js';
import type { McpToolClient } from '../mcpClient/client.js';
import { ChatSessionStore } from '../orchestrator/chatSession.js';
import { runTurn } from '../orchestrator/loop.js';
import { RACKET_ADVISOR_SYSTEM_PROMPT } from '../orchestrator/systemPrompt.js';
import { verifySignedRequest } from './verifyHmac.js';
import { RateLimiter } from './rateLimit.js';

export interface ChatServerDeps {
  provider: LLMProvider;
  mcp: McpToolClient;
  sessions: ChatSessionStore;
  rateLimiter: RateLimiter;
  sharedSecret: string;
  hmacReplayWindowSeconds: number;
  maxToolIterations: number;
  maxMessageLength?: number;
}

const MAX_BODY_BYTES = 16 * 1024;
const DEFAULT_MAX_MESSAGE_LENGTH = 2000;

/**
 * The entire public surface of this service: one HTTP endpoint, called only
 * by the OpenCart ai_chatbot module's server-side proxy (never directly by
 * a browser) — see upload/catalog/controller/extension/module/ai_chatbot.php.
 */
export function createChatHttpServer(deps: ChatServerDeps): Server {
  return createHttpServer(async (req, res) => {
    try {
      if (req.method === 'GET' && req.url === '/healthz') {
        res.writeHead(200, { 'Content-Type': 'application/json' }).end(JSON.stringify({ status: 'ok' }));
        return;
      }

      if (req.method !== 'POST' || req.url !== '/chat') {
        res.writeHead(404, { 'Content-Type': 'application/json' }).end(JSON.stringify({ error: 'not_found' }));
        return;
      }

      const rawBody = await readBody(req, MAX_BODY_BYTES);
      if (rawBody === null) {
        res.writeHead(413, { 'Content-Type': 'application/json' }).end(JSON.stringify({ error: 'payload_too_large' }));
        return;
      }

      const verified = verifySignedRequest({
        rawBody,
        timestamp: header(req, 'x-chatbot-timestamp'),
        signature: header(req, 'x-chatbot-signature'),
        secret: deps.sharedSecret,
        replayWindowSeconds: deps.hmacReplayWindowSeconds,
      });
      if (!verified.ok) {
        res.writeHead(401, { 'Content-Type': 'application/json' }).end(JSON.stringify({ error: 'unauthorized' }));
        return;
      }

      let payload: unknown;
      try {
        payload = JSON.parse(rawBody);
      } catch {
        res.writeHead(400, { 'Content-Type': 'application/json' }).end(JSON.stringify({ error: 'invalid_json' }));
        return;
      }

      const parsed = parseChatRequest(payload, deps.maxMessageLength ?? DEFAULT_MAX_MESSAGE_LENGTH);
      if (!parsed.ok) {
        res.writeHead(400, { 'Content-Type': 'application/json' }).end(JSON.stringify({ error: parsed.reason }));
        return;
      }

      if (!deps.rateLimiter.allow(parsed.conversationId)) {
        res.writeHead(429, { 'Content-Type': 'application/json' }).end(JSON.stringify({ error: 'rate_limited' }));
        return;
      }

      const history = deps.sessions.get(parsed.conversationId);
      const messages = [...history, { role: 'user' as const, content: parsed.message }];

      const result = await runTurn(
        deps.provider,
        deps.mcp,
        messages,
        RACKET_ADVISOR_SYSTEM_PROMPT,
        deps.maxToolIterations
      );
      deps.sessions.save(parsed.conversationId, result.messages);

      res.writeHead(200, { 'Content-Type': 'application/json' }).end(
        JSON.stringify({
          reply: result.finalText,
          suggested_products: result.suggestedProducts,
          conversation_id: parsed.conversationId,
        })
      );
    } catch (err) {
      console.error('[http] unhandled error:', err);
      res.writeHead(500, { 'Content-Type': 'application/json' }).end(JSON.stringify({ error: 'internal_error' }));
    }
  });
}

function header(req: IncomingMessage, name: string): string | undefined {
  const value = req.headers[name];
  return Array.isArray(value) ? value[0] : value;
}

function readBody(req: IncomingMessage, maxBytes: number): Promise<string | null> {
  return new Promise((resolve, reject) => {
    let size = 0;
    const chunks: Buffer[] = [];
    req.on('data', (chunk: Buffer) => {
      size += chunk.length;
      if (size > maxBytes) {
        req.destroy();
        resolve(null);
        return;
      }
      chunks.push(chunk);
    });
    req.on('end', () => resolve(Buffer.concat(chunks).toString('utf8')));
    req.on('error', reject);
  });
}

function parseChatRequest(
  payload: unknown,
  maxMessageLength: number
): { ok: true; message: string; conversationId: string } | { ok: false; reason: string } {
  if (!payload || typeof payload !== 'object') {
    return { ok: false, reason: 'invalid_body' };
  }
  const p = payload as Record<string, unknown>;

  if (typeof p.message !== 'string' || !p.message.trim()) {
    return { ok: false, reason: 'missing_message' };
  }
  if (p.message.length > maxMessageLength) {
    return { ok: false, reason: 'message_too_long' };
  }
  if (typeof p.conversation_id !== 'string' || !p.conversation_id) {
    return { ok: false, reason: 'missing_conversation_id' };
  }

  return { ok: true, message: p.message, conversationId: p.conversation_id };
}
