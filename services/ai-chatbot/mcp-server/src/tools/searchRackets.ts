import { z } from 'zod';
import type { RacketDataSource } from '../dataSource.js';
import type { RacketSpec } from '../mapping.js';

export const searchRacketsInputShape = {
  query: z.string().optional().describe('Free-text match over brand, series and model name'),
  brand: z.string().optional(),
  series: z.string().optional(),
  level_class: z
    .number()
    .int()
    .min(1)
    .max(4)
    .optional()
    .describe('Player level: 1=entry-level, 2=intermediate, 3=advanced, 4=professional'),
  flex_min: z.number().int().min(1).max(6).optional().describe('1=softest .. 6=stiffest'),
  flex_max: z.number().int().min(1).max(6).optional(),
  feel_min: z.number().int().min(1).max(5).optional().describe('1=least forgiving .. 5=most forgiving'),
  feel_max: z.number().int().min(1).max(5).optional(),
  frame_weight_min_g: z.number().optional(),
  frame_weight_max_g: z.number().optional(),
  balance_point_min_mm: z.number().optional(),
  balance_point_max_mm: z.number().optional(),
  head_diameter_min_mm: z.number().optional(),
  head_diameter_max_mm: z.number().optional(),
  weight_class: z
    .number()
    .int()
    .min(2)
    .max(6)
    .optional()
    .describe('U-system weight class number, e.g. 4 for "4U". Lower number = heavier racket.'),
  price_min: z.number().optional(),
  price_max: z.number().optional(),
  in_stock_only: z.boolean().optional().default(true),
  include_catalog_only: z
    .boolean()
    .optional()
    .default(false)
    .describe('Also include rackets not sold in this store (reference specs only, no price or link)'),
  limit: z.number().int().min(1).max(25).optional().default(10),
  sort_by: z.enum(['relevance', 'price_asc', 'price_desc', 'balance_point']).optional().default('relevance'),
};

export interface SearchRacketsResult {
  results: RacketSpec[];
  total_matches: number;
  truncated: boolean;
}

function matchesText(r: RacketSpec, needle: string): boolean {
  const haystack = `${r.brand} ${r.series ?? ''} ${r.model} ${r.display_name}`.toLowerCase();
  return haystack.includes(needle.toLowerCase());
}

export async function searchRackets(
  args: {
    query?: string;
    brand?: string;
    series?: string;
    level_class?: number;
    flex_min?: number;
    flex_max?: number;
    feel_min?: number;
    feel_max?: number;
    frame_weight_min_g?: number;
    frame_weight_max_g?: number;
    balance_point_min_mm?: number;
    balance_point_max_mm?: number;
    head_diameter_min_mm?: number;
    head_diameter_max_mm?: number;
    weight_class?: number;
    price_min?: number;
    price_max?: number;
    in_stock_only: boolean;
    include_catalog_only: boolean;
    limit: number;
    sort_by: 'relevance' | 'price_asc' | 'price_desc' | 'balance_point';
  },
  dataSource: RacketDataSource
): Promise<SearchRacketsResult> {
  const all = await dataSource.getRackets();

  let pool = args.include_catalog_only ? all : all.filter((r) => r.in_store);

  if (args.in_stock_only) {
    // The in-stock filter only has meaning for rackets this store actually sells;
    // catalog-only reference entries pass through regardless.
    pool = pool.filter((r) => !r.in_store || r.in_stock === true);
  }

  if (args.query) pool = pool.filter((r) => matchesText(r, args.query as string));
  if (args.brand) pool = pool.filter((r) => r.brand.toLowerCase() === (args.brand as string).toLowerCase());
  if (args.series) pool = pool.filter((r) => (r.series ?? '').toLowerCase() === (args.series as string).toLowerCase());
  if (args.level_class !== undefined) pool = pool.filter((r) => r.level_class === args.level_class);
  if (args.weight_class !== undefined) pool = pool.filter((r) => r.weight_class === `${args.weight_class}U`);

  const numericRange = (
    get: (r: RacketSpec) => number | null,
    min: number | undefined,
    max: number | undefined
  ) => {
    if (min === undefined && max === undefined) return;
    pool = pool.filter((r) => {
      const v = get(r);
      if (v === null) return false;
      if (min !== undefined && v < min) return false;
      if (max !== undefined && v > max) return false;
      return true;
    });
  };

  numericRange((r) => r.flex_rating, args.flex_min, args.flex_max);
  numericRange((r) => r.feel_rating, args.feel_min, args.feel_max);
  numericRange((r) => r.frame_weight_g, args.frame_weight_min_g, args.frame_weight_max_g);
  numericRange((r) => r.balance_point_mm, args.balance_point_min_mm, args.balance_point_max_mm);
  numericRange((r) => r.head_diameter_mm, args.head_diameter_min_mm, args.head_diameter_max_mm);
  numericRange((r) => r.price, args.price_min, args.price_max);

  switch (args.sort_by) {
    case 'price_asc':
      pool = [...pool].sort((a, b) => (a.price ?? Infinity) - (b.price ?? Infinity));
      break;
    case 'price_desc':
      pool = [...pool].sort((a, b) => (b.price ?? -Infinity) - (a.price ?? -Infinity));
      break;
    case 'balance_point':
      pool = [...pool].sort((a, b) => (a.balance_point_mm ?? Infinity) - (b.balance_point_mm ?? Infinity));
      break;
    case 'relevance':
    default:
      break;
  }

  const total_matches = pool.length;
  const results = pool.slice(0, args.limit);
  return { results, total_matches, truncated: total_matches > results.length };
}
