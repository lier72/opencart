import type { LiveDataMap, RacketSpec, RawFinderData } from './mapping.js';
import { mapItem } from './mapping.js';

export interface DataSourceConfig {
  storefrontBaseUrl: string;
  ttlSeconds: number;
}

interface Cache {
  rackets: RacketSpec[];
  fetchedAt: number;
}

/**
 * Reads racket data from the two URLs the deterministic racket_finder widget
 * already serves publicly — no new PHP endpoint needed:
 *   - finder-data.json: static specs, one record per racket SKU
 *   - extension/module/racket_finder/data: live price/stock, keyed by product id
 * See upload/catalog/controller/extension/module/racket_finder.php (data(),
 * liveDataCached()) and the matching <script src> tags in
 * upload/catalog/view/theme/default/template/extension/module/racket_finder.twig.
 */
export class RacketDataSource {
  private cache: Cache | null = null;
  private refreshing: Promise<RacketSpec[]> | null = null;

  constructor(private readonly config: DataSourceConfig) {}

  private get finderDataUrl(): string {
    return `${this.config.storefrontBaseUrl}/catalog/view/javascript/racket_finder/finder-data.json`;
  }

  private get liveDataUrl(): string {
    return `${this.config.storefrontBaseUrl}/index.php?route=extension/module/racket_finder/data`;
  }

  private isFresh(cache: Cache): boolean {
    return Date.now() - cache.fetchedAt < this.config.ttlSeconds * 1000;
  }

  async getRackets(options: { forceRefresh?: boolean } = {}): Promise<RacketSpec[]> {
    if (!options.forceRefresh && this.cache && this.isFresh(this.cache)) {
      return this.cache.rackets;
    }
    if (this.refreshing) {
      return this.refreshing;
    }
    this.refreshing = this.refresh();
    try {
      return await this.refreshing;
    } finally {
      this.refreshing = null;
    }
  }

  async getRacketById(id: number): Promise<RacketSpec | undefined> {
    const rackets = await this.getRackets();
    return rackets.find((r) => r.product_id === id);
  }

  private async refresh(): Promise<RacketSpec[]> {
    try {
      const [finderData, liveData] = await Promise.all([this.fetchFinderData(), this.fetchLiveData()]);
      const rackets = finderData.items.map((item) =>
        mapItem(item, finderData.tech, liveData, this.config.storefrontBaseUrl)
      );
      this.cache = { rackets, fetchedAt: Date.now() };
      return rackets;
    } catch (err) {
      if (this.cache) {
        console.error('[dataSource] refresh failed, serving stale cache:', err);
        return this.cache.rackets;
      }
      throw err;
    }
  }

  private async fetchFinderData(): Promise<RawFinderData> {
    const res = await fetch(this.finderDataUrl);
    if (!res.ok) {
      throw new Error(`finder-data.json fetch failed: HTTP ${res.status}`);
    }
    const json = (await res.json()) as unknown;
    if (!isRawFinderData(json)) {
      throw new Error('finder-data.json has an unexpected shape');
    }
    return json;
  }

  private async fetchLiveData(): Promise<LiveDataMap> {
    const res = await fetch(this.liveDataUrl);
    if (!res.ok) {
      throw new Error(`racket_finder/data fetch failed: HTTP ${res.status}`);
    }
    const json = (await res.json()) as unknown;
    if (!isLiveDataMap(json)) {
      throw new Error('racket_finder/data has an unexpected shape');
    }
    return json;
  }
}

function isRawFinderData(value: unknown): value is RawFinderData {
  if (!value || typeof value !== 'object') return false;
  const v = value as Record<string, unknown>;
  return Array.isArray(v.items) && Array.isArray(v.tech);
}

function isLiveDataMap(value: unknown): value is LiveDataMap {
  if (!value || typeof value !== 'object' || Array.isArray(value)) return false;
  return Object.values(value as Record<string, unknown>).every(
    (entry) =>
      entry !== null &&
      typeof entry === 'object' &&
      typeof (entry as Record<string, unknown>).p === 'number' &&
      ((entry as Record<string, unknown>).q === 0 || (entry as Record<string, unknown>).q === 1)
  );
}
