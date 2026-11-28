import { z } from 'zod';
import type { RacketDataSource } from '../dataSource.js';
import type { RacketSpec } from '../mapping.js';

export const compareRacketsInputShape = {
  product_ids: z
    .array(z.number().int())
    .min(2)
    .max(5)
    .describe('2 to 5 racket product_ids to compare side by side'),
};

export interface CompareRacketsResult {
  items: RacketSpec[];
  not_found: number[];
}

export async function compareRackets(
  args: { product_ids: number[] },
  dataSource: RacketDataSource
): Promise<CompareRacketsResult> {
  const results = await Promise.all(args.product_ids.map((id) => dataSource.getRacketById(id)));

  const items: RacketSpec[] = [];
  const not_found: number[] = [];
  results.forEach((racket, i) => {
    if (racket) {
      items.push(racket);
    } else {
      not_found.push(args.product_ids[i]);
    }
  });

  return { items, not_found };
}
