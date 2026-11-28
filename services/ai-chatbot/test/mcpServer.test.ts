import { test } from 'node:test';
import assert from 'node:assert/strict';
import { RacketDataSource } from '../mcp-server/src/dataSource.js';
import { searchRackets } from '../mcp-server/src/tools/searchRackets.js';
import { getRacketDetails } from '../mcp-server/src/tools/getRacketDetails.js';
import { compareRackets } from '../mcp-server/src/tools/compareRackets.js';
import { startFixtureStorefront } from './helpers/fixtureServer.js';

async function withDataSource(fn: (ds: RacketDataSource) => Promise<void>) {
  const fixture = await startFixtureStorefront();
  try {
    const ds = new RacketDataSource({ storefrontBaseUrl: fixture.baseUrl, ttlSeconds: 600 });
    await fn(ds);
  } finally {
    await fixture.close();
  }
}

test('dataSource merges static specs with live price/stock and maps fields', async () => {
  await withDataSource(async (ds) => {
    const rackets = await ds.getRackets();
    assert.equal(rackets.length, 4);

    const calibar = rackets.find((r) => r.product_id === 9101);
    assert.ok(calibar);
    assert.equal(calibar?.brand, 'Li-Ning');
    assert.equal(calibar?.display_name, 'Li-Ning Calibar 900 (3D Calibar 900)');
    assert.equal(calibar?.weight_class, '4U');
    assert.equal(calibar?.balance_type, 'head_heavy'); // bt:"K"
    assert.equal(calibar?.level_class, 4);
    assert.equal(calibar?.level_label, 'professional');
    assert.deepEqual(calibar?.technology_names, ['DYNAMIC OPTIMUM FRAME', 'AERO TEC-BEAM SYSTEM']);
    assert.equal(calibar?.in_store, true);
    assert.equal(calibar?.price, 10990);
    assert.equal(calibar?.in_stock, true);
    assert.ok(calibar?.product_url?.includes('product_id=9101'));
  });
});

test('catalog-only (site:false) items have no price/stock/url', async () => {
  await withDataSource(async (ds) => {
    const beta = await ds.getRacketById(5678);
    assert.ok(beta);
    assert.equal(beta?.in_store, false);
    assert.equal(beta?.price, null);
    assert.equal(beta?.in_stock, null);
    assert.equal(beta?.product_url, null);
    assert.equal(beta?.balance_type, 'even'); // bt:"N"
  });
});

test('an in-store item missing from the live price map degrades to null, not a crash', async () => {
  await withDataSource(async (ds) => {
    const gamma = await ds.getRacketById(9999);
    assert.ok(gamma);
    assert.equal(gamma?.in_store, true);
    assert.equal(gamma?.price, null);
    assert.equal(gamma?.in_stock, null);
    assert.equal(gamma?.balance_type, 'unknown'); // bt:"?"
  });
});

test('search_rackets excludes catalog-only items by default', async () => {
  await withDataSource(async (ds) => {
    const { results, total_matches } = await searchRackets(
      { in_stock_only: false, include_catalog_only: false, limit: 10, sort_by: 'relevance' },
      ds
    );
    assert.equal(total_matches, 3); // 9101, 1234, 9999 — not 5678 (site:false)
    assert.ok(!results.some((r) => r.product_id === 5678));
  });
});

test('search_rackets include_catalog_only surfaces the reference-only item', async () => {
  await withDataSource(async (ds) => {
    const { results } = await searchRackets(
      { in_stock_only: false, include_catalog_only: true, limit: 10, sort_by: 'relevance' },
      ds
    );
    assert.ok(results.some((r) => r.product_id === 5678));
  });
});

test('search_rackets filters by level_class and price range', async () => {
  await withDataSource(async (ds) => {
    const { results } = await searchRackets(
      {
        level_class: 1,
        price_max: 5000,
        in_stock_only: true,
        include_catalog_only: false,
        limit: 10,
        sort_by: 'relevance',
      },
      ds
    );
    assert.equal(results.length, 1);
    assert.equal(results[0].product_id, 1234);
  });
});

test('search_rackets in_stock_only excludes in-store items with unknown/no stock', async () => {
  await withDataSource(async (ds) => {
    const { results } = await searchRackets(
      { in_stock_only: true, include_catalog_only: false, limit: 10, sort_by: 'relevance' },
      ds
    );
    // 9999 is in_store but absent from the live map, so in_stock is null -> excluded
    assert.ok(!results.some((r) => r.product_id === 9999));
    assert.ok(results.some((r) => r.product_id === 9101));
  });
});

test('search_rackets sorts by price', async () => {
  await withDataSource(async (ds) => {
    const { results } = await searchRackets(
      { in_stock_only: true, include_catalog_only: false, limit: 10, sort_by: 'price_asc' },
      ds
    );
    assert.equal(results[0].product_id, 1234); // 3990
    assert.equal(results[1].product_id, 9101); // 10990
  });
});

test('get_racket_details returns an error payload for an unknown id', async () => {
  await withDataSource(async (ds) => {
    const result = await getRacketDetails({ product_id: 404404 }, ds);
    assert.ok('error' in result);
  });
});

test('compare_rackets splits found vs not_found', async () => {
  await withDataSource(async (ds) => {
    const result = await compareRackets({ product_ids: [9101, 1234, 404404] }, ds);
    assert.equal(result.items.length, 2);
    assert.deepEqual(result.not_found, [404404]);
  });
});
