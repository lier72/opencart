import { test } from 'node:test';
import assert from 'node:assert/strict';
import { createHmac } from 'node:crypto';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import type { Server } from 'node:http';
import { verifySignedRequest } from '../src/http/verifyHmac.js';
import { RateLimiter } from '../src/http/rateLimit.js';
import { createChatHttpServer } from '../src/http/server.js';
import { ChatSessionStore } from '../src/orchestrator/chatSession.js';
import { FakeProvider } from '../src/llm/fakeProvider.js';
import { McpToolClient } from '../src/mcpClient/client.js';
import { startFixtureStorefront } from './helpers/fixtureServer.js';

const here = path.dirname(fileURLToPath(import.meta.url));
const mcpServerEntrypoint = path.join(here, '..', 'dist', 'mcp-server', 'src', 'index.js');
const SECRET = 'test-shared-secret';

function sign(body: string, timestamp: string): string {
  return createHmac('sha256', SECRET).update(`${timestamp}\n${body}`).digest('hex');
}

test('verifySignedRequest: accepts a correctly signed, fresh request', () => {
  const body = '{"message":"hi"}';
  const timestamp = String(Math.floor(Date.now() / 1000));
  const result = verifySignedRequest({
    rawBody: body,
    timestamp,
    signature: sign(body, timestamp),
    secret: SECRET,
    replayWindowSeconds: 300,
  });
  assert.deepEqual(result, { ok: true });
});

test('verifySignedRequest: rejects a tampered body', () => {
  const timestamp = String(Math.floor(Date.now() / 1000));
  const signature = sign('{"message":"original"}', timestamp);
  const result = verifySignedRequest({
    rawBody: '{"message":"tampered"}',
    timestamp,
    signature,
    secret: SECRET,
    replayWindowSeconds: 300,
  });
  assert.equal(result.ok, false);
});

test('verifySignedRequest: rejects a stale timestamp (replay defense)', () => {
  const body = '{"message":"hi"}';
  const staleTimestamp = String(Math.floor(Date.now() / 1000) - 3600);
  const result = verifySignedRequest({
    rawBody: body,
    timestamp: staleTimestamp,
    signature: sign(body, staleTimestamp),
    secret: SECRET,
    replayWindowSeconds: 300,
  });
  assert.equal(result.ok, false);
});

test('RateLimiter: allows up to the limit, then rejects within the window', () => {
  const limiter = new RateLimiter(2, 60_000);
  assert.equal(limiter.allow('k'), true);
  assert.equal(limiter.allow('k'), true);
  assert.equal(limiter.allow('k'), false);
  assert.equal(limiter.allow('other-key'), true);
  limiter.stop();
});

interface TestServer {
  port: number;
  close: () => Promise<void>;
}

async function startTestServer(opts: {
  storefrontBaseUrl: string;
  scriptedReplies: string[];
  rateLimitMax?: number;
}): Promise<TestServer> {
  const mcp = new McpToolClient({
    serverEntrypoint: mcpServerEntrypoint,
    env: { STOREFRONT_BASE_URL: opts.storefrontBaseUrl, RACKET_DATA_TTL_SECONDS: '600' },
  });
  const provider = new FakeProvider(
    opts.scriptedReplies.map((text) => ({ text, toolCalls: [], stopReason: 'end' as const, raw: null }))
  );
  const sessions = new ChatSessionStore(60_000, 40);
  const rateLimiter = new RateLimiter(opts.rateLimitMax ?? 20, 60_000);

  const server = createChatHttpServer({
    provider,
    mcp,
    sessions,
    rateLimiter,
    sharedSecret: SECRET,
    hmacReplayWindowSeconds: 300,
    maxToolIterations: 5,
  });

  await new Promise<void>((resolve) => server.listen(0, resolve));
  const address = server.address();
  if (!address || typeof address === 'string') throw new Error('failed to bind test server');

  return {
    port: address.port,
    close: async () => {
      sessions.stop();
      rateLimiter.stop();
      await mcp.close();
      await new Promise<void>((resolve) => (server as Server).close(() => resolve()));
    },
  };
}

function signedHeaders(body: string) {
  const timestamp = String(Math.floor(Date.now() / 1000));
  return { 'x-chatbot-timestamp': timestamp, 'x-chatbot-signature': sign(body, timestamp) };
}

test('POST /chat: happy path returns the reply and echoes conversation_id', async () => {
  const fixture = await startFixtureStorefront();
  const server = await startTestServer({ storefrontBaseUrl: fixture.baseUrl, scriptedReplies: ['Hello there!'] });
  try {
    const body = JSON.stringify({ message: 'hi', conversation_id: 'conv-1' });
    const res = await fetch(`http://127.0.0.1:${server.port}/chat`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', ...signedHeaders(body) },
      body,
    });
    assert.equal(res.status, 200);
    const json = (await res.json()) as { reply: string; conversation_id: string; suggested_products: unknown[] };
    assert.equal(json.reply, 'Hello there!');
    assert.equal(json.conversation_id, 'conv-1');
    assert.deepEqual(json.suggested_products, []);
  } finally {
    await server.close();
    await fixture.close();
  }
});

test('POST /chat: wrong signature is rejected with 401', async () => {
  const fixture = await startFixtureStorefront();
  const server = await startTestServer({ storefrontBaseUrl: fixture.baseUrl, scriptedReplies: ['unused'] });
  try {
    const body = JSON.stringify({ message: 'hi', conversation_id: 'conv-1' });
    const res = await fetch(`http://127.0.0.1:${server.port}/chat`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'x-chatbot-timestamp': String(Math.floor(Date.now() / 1000)),
        'x-chatbot-signature': 'deadbeef',
      },
      body,
    });
    assert.equal(res.status, 401);
  } finally {
    await server.close();
    await fixture.close();
  }
});

test('POST /chat: missing conversation_id is rejected with 400', async () => {
  const fixture = await startFixtureStorefront();
  const server = await startTestServer({ storefrontBaseUrl: fixture.baseUrl, scriptedReplies: ['unused'] });
  try {
    const body = JSON.stringify({ message: 'hi' });
    const res = await fetch(`http://127.0.0.1:${server.port}/chat`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', ...signedHeaders(body) },
      body,
    });
    assert.equal(res.status, 400);
  } finally {
    await server.close();
    await fixture.close();
  }
});

test('POST /chat: rate limit kicks in per conversation_id', async () => {
  const fixture = await startFixtureStorefront();
  const server = await startTestServer({
    storefrontBaseUrl: fixture.baseUrl,
    scriptedReplies: ['first reply'],
    rateLimitMax: 1,
  });
  try {
    const body = JSON.stringify({ message: 'hi', conversation_id: 'conv-rl' });
    const first = await fetch(`http://127.0.0.1:${server.port}/chat`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', ...signedHeaders(body) },
      body,
    });
    assert.equal(first.status, 200);

    const second = await fetch(`http://127.0.0.1:${server.port}/chat`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', ...signedHeaders(body) },
      body,
    });
    assert.equal(second.status, 429);
  } finally {
    await server.close();
    await fixture.close();
  }
});

test('GET /healthz returns ok', async () => {
  const fixture = await startFixtureStorefront();
  const server = await startTestServer({ storefrontBaseUrl: fixture.baseUrl, scriptedReplies: [] });
  try {
    const res = await fetch(`http://127.0.0.1:${server.port}/healthz`);
    assert.equal(res.status, 200);
  } finally {
    await server.close();
    await fixture.close();
  }
});
