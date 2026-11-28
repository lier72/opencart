interface Bucket {
  count: number;
  windowStart: number;
}

/**
 * Simple fixed-window counter, keyed by conversation_id. Note: IP-based
 * limiting isn't meaningful at this layer — every request arrives from the
 * OpenCart server's address (it's a server-to-server proxy call), not the
 * visitor's browser. The visitor-facing IP/session rate limit lives in the
 * PHP module (upload/catalog/controller/extension/module/ai_chatbot.php);
 * this is the second, cost-control layer per conversation.
 */
export class RateLimiter {
  private buckets = new Map<string, Bucket>();
  private sweepTimer: NodeJS.Timeout;

  constructor(
    private readonly maxRequests: number,
    private readonly windowMs: number
  ) {
    this.sweepTimer = setInterval(() => this.sweep(), windowMs * 2);
    this.sweepTimer.unref();
  }

  allow(key: string): boolean {
    const now = Date.now();
    const bucket = this.buckets.get(key);

    if (!bucket || now - bucket.windowStart > this.windowMs) {
      this.buckets.set(key, { count: 1, windowStart: now });
      return true;
    }

    if (bucket.count >= this.maxRequests) {
      return false;
    }

    bucket.count++;
    return true;
  }

  private sweep(): void {
    const now = Date.now();
    for (const [key, bucket] of this.buckets) {
      if (now - bucket.windowStart > this.windowMs * 2) {
        this.buckets.delete(key);
      }
    }
  }

  stop(): void {
    clearInterval(this.sweepTimer);
  }
}
