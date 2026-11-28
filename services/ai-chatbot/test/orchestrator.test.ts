import { test } from 'node:test';
import assert from 'node:assert/strict';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { McpToolClient } from '../src/mcpClient/client.js';
import { FakeProvider } from '../src/llm/fakeProvider.js';
import { runTurn } from '../src/orchestrator/loop.js';
import { ChatSessionStore } from '../src/orchestrator/chatSession.js';
import { startFixtureStorefront } from './helpers/fixtureServer.js';

const here = path.dirname(fileURLToPath(import.meta.url));
// This test file runs compiled from dist/test/orchestrator.test.js, so the
// compiled MCP server sits alongside at ../mcp-server/src/index.js — but we
// run tests via tsx directly against the TS sources, so resolve from the
// repo's dist/ build output instead (built by `npm run build` beforehand).
const mcpServerEntrypoint = path.join(here, '..', 'dist', 'mcp-server', 'src', 'index.js');

async function withMcpClient(storefrontBaseUrl: string, fn: (mcp: McpToolClient) => Promise<void>) {
  const mcp = new McpToolClient({
    serverEntrypoint: mcpServerEntrypoint,
    env: { STOREFRONT_BASE_URL: storefrontBaseUrl, RACKET_DATA_TTL_SECONDS: '600' },
  });
  try {
    await fn(mcp);
  } finally {
    await mcp.close();
  }
}

test('McpToolClient lists the three registered tools with real JSON Schemas', async () => {
  const fixture = await startFixtureStorefront();
  try {
    await withMcpClient(fixture.baseUrl, async (mcp) => {
      const tools = await mcp.listToolsAsDefinitions();
      const names = tools.map((t) => t.name).sort();
      assert.deepEqual(names, ['compare_rackets', 'get_racket_details', 'search_rackets']);
      const details = tools.find((t) => t.name === 'get_racket_details')!;
      assert.equal(details.inputSchema.type, 'object');
    });
  } finally {
    await fixture.close();
  }
});

test('McpToolClient.callTool round-trips through the real stdio protocol', async () => {
  const fixture = await startFixtureStorefront();
  try {
    await withMcpClient(fixture.baseUrl, async (mcp) => {
      const result = (await mcp.callTool('get_racket_details', { product_id: 9101 })) as { brand?: string };
      assert.equal(result.brand, 'Li-Ning');
    });
  } finally {
    await fixture.close();
  }
});

test('runTurn: single-shot final answer with no tool calls', async () => {
  const fixture = await startFixtureStorefront();
  try {
    await withMcpClient(fixture.baseUrl, async (mcp) => {
      const provider = new FakeProvider([
        { text: 'Hi! What kind of player are you?', toolCalls: [], stopReason: 'end', raw: null },
      ]);
      const result = await runTurn(provider, mcp, [{ role: 'user', content: 'hi' }], 'system', 5);
      assert.equal(result.finalText, 'Hi! What kind of player are you?');
      assert.equal(result.suggestedProducts.length, 0);
      assert.equal(result.messages.length, 2); // user + assistant
    });
  } finally {
    await fixture.close();
  }
});

test('runTurn: calls a real MCP tool and surfaces it as a suggested product', async () => {
  const fixture = await startFixtureStorefront();
  try {
    await withMcpClient(fixture.baseUrl, async (mcp) => {
      const provider = new FakeProvider([
        {
          text: null,
          toolCalls: [{ id: 'call_1', name: 'get_racket_details', arguments: { product_id: 9101 } }],
          stopReason: 'tool_use',
          raw: null,
        },
        { text: 'The Calibar 900 is a professional, head-heavy racket.', toolCalls: [], stopReason: 'end', raw: null },
      ]);

      const result = await runTurn(provider, mcp, [{ role: 'user', content: 'tell me about 9101' }], 'system', 5);

      assert.equal(result.finalText, 'The Calibar 900 is a professional, head-heavy racket.');
      assert.equal(result.suggestedProducts.length, 1);
      assert.equal(result.suggestedProducts[0].product_id, 9101);
      assert.equal(result.suggestedProducts[0].price, 10990);
      // user, assistant(tool_use), user(tool_result), assistant(final)
      assert.equal(result.messages.length, 4);
    });
  } finally {
    await fixture.close();
  }
});

test('runTurn: a failing tool call is reported back as an error result, not thrown', async () => {
  const fixture = await startFixtureStorefront();
  try {
    await withMcpClient(fixture.baseUrl, async (mcp) => {
      const provider = new FakeProvider([
        {
          text: null,
          toolCalls: [{ id: 'call_1', name: 'not_a_real_tool', arguments: {} }],
          stopReason: 'tool_use',
          raw: null,
        },
        { text: 'Sorry, something went wrong.', toolCalls: [], stopReason: 'end', raw: null },
      ]);

      const result = await runTurn(provider, mcp, [{ role: 'user', content: 'hi' }], 'system', 5);
      assert.equal(result.finalText, 'Sorry, something went wrong.');

      const toolResultTurn = result.messages.find((m) => m.toolResults?.length);
      assert.ok(toolResultTurn?.toolResults?.[0].isError);
    });
  } finally {
    await fixture.close();
  }
});

test('runTurn: throws once the tool-call budget is exhausted (no infinite loop)', async () => {
  const fixture = await startFixtureStorefront();
  try {
    await withMcpClient(fixture.baseUrl, async (mcp) => {
      const alwaysToolUse = Array.from({ length: 5 }, (_, i) => ({
        text: null,
        toolCalls: [{ id: `call_${i}`, name: 'get_racket_details', arguments: { product_id: 9101 } }],
        stopReason: 'tool_use' as const,
        raw: null,
      }));
      const provider = new FakeProvider(alwaysToolUse);

      await assert.rejects(
        () => runTurn(provider, mcp, [{ role: 'user', content: 'hi' }], 'system', 3),
        /exceeded MAX_TOOL_ITERATIONS/
      );
    });
  } finally {
    await fixture.close();
  }
});

test('ChatSessionStore trims history to maxMessages and round-trips within TTL', () => {
  const store = new ChatSessionStore(60_000, 3);
  store.save('conv-1', [
    { role: 'user', content: 'a' },
    { role: 'assistant', content: 'b' },
    { role: 'user', content: 'c' },
    { role: 'assistant', content: 'd' },
  ]);
  const saved = store.get('conv-1');
  assert.equal(saved.length, 3);
  assert.deepEqual(saved.map((m) => m.content), ['b', 'c', 'd']);
  assert.deepEqual(store.get('unknown-conv'), []);
  store.stop();
});
