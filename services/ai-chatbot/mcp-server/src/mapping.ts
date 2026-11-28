/**
 * Translates finder-data.json's internal short field codes into a friendly,
 * self-describing schema for LLM consumption. Field meanings were confirmed
 * by reading the scoring logic in catalog/view/javascript/racket_finder/widget.js
 * (not re-derived here) — see the comments below for exactly where.
 */

export interface RawTech {
  n: string; // technology name, e.g. "DYNAMIC OPTIMUM FRAME"
  b?: string; // brand the technology belongs to
  c?: string; // category, e.g. "Рама и аэродинамика"
  w?: string;
  y?: string;
  h?: string;
  e?: number[]; // effect vector on [power, speed, control, ease, comfort] axes
}

export interface RawFinderItem {
  id: number;
  b: string; // brand
  m: string; // model
  s?: string; // series
  u?: number; // weight class, U-system: 2U (heaviest) .. 6U (lightest)
  bp?: number; // balance point, mm — the authoritative value shown on the product card
  cardbp?: number;
  bpmeas?: number; // in-store measured balance point (provenance; not surfaced)
  f?: number; // flex, 1-6
  fe?: number; // feel/forgiveness, 1-5
  mw?: number; // frame weight, g
  sw?: number; // swingweight
  swmeas?: number; // in-store measured swingweight (provenance; not surfaced)
  hd?: number; // head diameter, mm
  lv?: number; // level class, 1 (entry-level) .. 4 (professional) — ascending, confirmed via LV_BAND/LV_RU in widget.js
  bt?: 'K' | 'N' | 'H' | '?'; // balance type: K=head-heavy ("в голову"), N=even/neutral, H=head-light ("в ручку") — confirmed via man_bt legend in widget.js
  t?: number[]; // technology ids, indexes into the sibling `tech` array
  mname?: string; // secondary/variant name appended after brand+model, e.g. "3D Calibar 900"
  site?: boolean; // true if this racket is actually sold in this store (vs. a catalog-only reference entry)
  [key: string]: unknown; // *src/cx/cy/lnx/lny/tl/csrc/dsrc provenance+UI-only fields, intentionally untyped and dropped
}

export interface RawFinderData {
  items: RawFinderItem[];
  tech: RawTech[];
  built?: string;
  nmeas?: number;
  nsite?: number;
  ncat?: number;
}

export interface LiveEntry {
  p: number; // price customer pays now, store currency, integer
  q: 0 | 1; // in stock
}

export type LiveDataMap = Record<string, LiveEntry>;

export type LevelClass = 1 | 2 | 3 | 4;

const LEVEL_LABELS: Record<LevelClass, string> = {
  1: 'entry-level',
  2: 'intermediate',
  3: 'advanced',
  4: 'professional',
};

const BALANCE_TYPE_LABELS: Record<string, RacketSpec['balance_type']> = {
  K: 'head_heavy',
  N: 'even',
  H: 'head_light',
};

export interface RacketSpec {
  product_id: number;
  brand: string;
  model: string;
  series: string | null;
  display_name: string;
  weight_class: string | null; // e.g. "4U" — lower number is heavier
  balance_point_mm: number | null;
  balance_type: 'head_heavy' | 'even' | 'head_light' | 'unknown';
  flex_rating: number | null; // 1 (softest) .. 6 (stiffest)
  feel_rating: number | null; // 1 (least forgiving) .. 5 (most forgiving)
  frame_weight_g: number | null;
  swingweight: number | null;
  head_diameter_mm: number | null;
  level_class: LevelClass | null;
  level_label: string | null;
  technology_names: string[];
  in_store: boolean; // sold in this store, vs. a manufacturer-catalog reference entry
  price: number | null; // store currency; null when not in_store or price unknown
  in_stock: boolean | null; // null when not in_store
  product_url: string | null; // null when not in_store
}

function displayName(item: RawFinderItem): string {
  const base = `${item.b} ${item.m}`.trim();
  return item.mname && item.mname !== item.m ? `${base} (${item.mname})` : base;
}

function weightClassLabel(u: number | undefined): string | null {
  return typeof u === 'number' ? `${u}U` : null;
}

export function mapTechNames(ids: number[] | undefined, tech: RawTech[]): string[] {
  if (!ids) return [];
  return ids
    .map((i) => tech[i]?.n)
    .filter((n): n is string => typeof n === 'string');
}

export function mapItem(
  item: RawFinderItem,
  tech: RawTech[],
  live: LiveDataMap,
  storefrontBaseUrl: string
): RacketSpec {
  const liveEntry = live[String(item.id)];
  const inStore = Boolean(item.site);
  const level = (item.lv && item.lv >= 1 && item.lv <= 4 ? (item.lv as LevelClass) : null);

  return {
    product_id: item.id,
    brand: item.b,
    model: item.m,
    series: item.s ?? null,
    display_name: displayName(item),
    weight_class: weightClassLabel(item.u),
    balance_point_mm: item.bp ?? item.cardbp ?? null,
    balance_type: item.bt ? (BALANCE_TYPE_LABELS[item.bt] ?? 'unknown') : 'unknown',
    flex_rating: item.f ?? null,
    feel_rating: item.fe ?? null,
    frame_weight_g: item.mw ?? null,
    swingweight: item.sw ?? null,
    head_diameter_mm: item.hd ?? null,
    level_class: level,
    level_label: level ? LEVEL_LABELS[level] : null,
    technology_names: mapTechNames(item.t, tech),
    in_store: inStore,
    price: inStore && liveEntry ? liveEntry.p : null,
    in_stock: inStore && liveEntry ? Boolean(liveEntry.q) : null,
    product_url: inStore
      ? `${storefrontBaseUrl}/index.php?route=product/product&product_id=${item.id}`
      : null,
  };
}
