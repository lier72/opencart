import { createServer, type Server } from 'node:http';
import { readFile } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';
import path from 'node:path';

const here = path.dirname(fileURLToPath(import.meta.url));
const fixturesDir = path.join(here, '..', 'fixtures');

/**
 * Serves the two fixture files at the exact paths the real storefront exposes
 * them at, so RacketDataSource can be tested against a real HTTP round-trip
 * without a live OpenCart install.
 */
export async function startFixtureStorefront(): Promise<{ baseUrl: string; close: () => Promise<void> }> {
  const finderData = await readFile(path.join(fixturesDir, 'sample-finder-data.json'));
  const liveData = await readFile(path.join(fixturesDir, 'sample-live-data.json'));

  const server: Server = createServer((req, res) => {
    if (req.url === '/catalog/view/javascript/racket_finder/finder-data.json') {
      res.writeHead(200, { 'Content-Type': 'application/json' }).end(finderData);
      return;
    }
    if (req.url === '/index.php?route=extension/module/racket_finder/data') {
      res.writeHead(200, { 'Content-Type': 'application/json' }).end(liveData);
      return;
    }
    res.writeHead(404).end();
  });

  await new Promise<void>((resolve) => server.listen(0, resolve));
  const address = server.address();
  if (!address || typeof address === 'string') {
    throw new Error('failed to bind fixture server');
  }

  return {
    baseUrl: `http://127.0.0.1:${address.port}`,
    close: () => new Promise((resolve) => server.close(() => resolve())),
  };
}
