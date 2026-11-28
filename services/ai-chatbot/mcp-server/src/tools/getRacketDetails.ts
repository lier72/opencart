import { z } from 'zod';
import type { RacketDataSource } from '../dataSource.js';
import type { RacketSpec } from '../mapping.js';

export const getRacketDetailsInputShape = {
  product_id: z.number().int().describe('The racket product_id, as returned by search_rackets or compare_rackets'),
};

export async function getRacketDetails(
  args: { product_id: number },
  dataSource: RacketDataSource
): Promise<RacketSpec | { error: string }> {
  const racket = await dataSource.getRacketById(args.product_id);
  if (!racket) {
    return { error: `No racket found with product_id ${args.product_id}` };
  }
  return racket;
}
