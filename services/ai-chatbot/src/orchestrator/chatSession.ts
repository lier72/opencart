import type { ChatMessage } from '../llm/types.js';

interface Session {
  messages: ChatMessage[];
  lastAccess: number;
}

/**
 * In-memory conversation history, keyed by the browser-generated conversation_id.
 * No DB table for v1 (per the build plan) — a lost session just starts fresh,
 * which is an acceptable trade-off for a stateless, horizontally-restartable
 * service at this scale.
 */
export class ChatSessionStore {
  private sessions = new Map<string, Session>();
  private sweepTimer: NodeJS.Timeout;

  constructor(
    private readonly ttlMs: number,
    private readonly maxMessages: number
  ) {
    this.sweepTimer = setInterval(() => this.sweep(), Math.min(ttlMs, 5 * 60 * 1000));
    this.sweepTimer.unref();
  }

  get(conversationId: string): ChatMessage[] {
    const session = this.sessions.get(conversationId);
    if (!session || Date.now() - session.lastAccess > this.ttlMs) {
      return [];
    }
    return session.messages;
  }

  save(conversationId: string, messages: ChatMessage[]): void {
    const trimmed = messages.length > this.maxMessages ? messages.slice(-this.maxMessages) : messages;
    this.sessions.set(conversationId, { messages: trimmed, lastAccess: Date.now() });
  }

  private sweep(): void {
    const now = Date.now();
    for (const [id, session] of this.sessions) {
      if (now - session.lastAccess > this.ttlMs) {
        this.sessions.delete(id);
      }
    }
  }

  stop(): void {
    clearInterval(this.sweepTimer);
  }
}
