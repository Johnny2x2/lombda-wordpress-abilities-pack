import type {
  PageSummary,
  ContainerTypeInfo,
  PageStructure,
  ElementorElement,
} from "./wordpress-client.js";

export interface PageContext {
  postId: number;
  summary: PageSummary;
  structure?: PageStructure;
  containerType?: ContainerTypeInfo;
  /** Content hash for screenshot cache invalidation. */
  contentHash: string;
  lastAccessed: number;
}

export interface ChangeRecord {
  timestamp: number;
  description: string;
  operationType: "blueprint" | "batch" | "single";
  affectedIds: string[];
  /** Snapshot of _elementor_data before the change (for undo). */
  previousElements?: ElementorElement[];
}

/**
 * Tracks active-page context so the AI doesn't need to re-fetch
 * page data between consecutive tool calls.
 */
export class SessionManager {
  private pages = new Map<number, PageContext>();
  private activePageId: number | null = null;
  private changeHistory: ChangeRecord[] = [];
  private maxHistory = 20;
  private timeoutMs: number;

  constructor(timeoutMinutes = 30) {
    this.timeoutMs = timeoutMinutes * 60 * 1000;
  }

  // ── Active page ─────────────────────────────────────────────────────

  getActivePageId(): number | null {
    if (this.activePageId !== null) {
      const ctx = this.pages.get(this.activePageId);
      if (ctx && Date.now() - ctx.lastAccessed < this.timeoutMs) {
        return this.activePageId;
      }
      // Expired
      this.activePageId = null;
    }
    return null;
  }

  setActivePage(
    postId: number,
    summary: PageSummary,
    containerType?: ContainerTypeInfo,
    structure?: PageStructure
  ): void {
    this.pages.set(postId, {
      postId,
      summary,
      structure,
      containerType,
      contentHash: this.hashSummary(summary),
      lastAccessed: Date.now(),
    });
    this.activePageId = postId;
  }

  getPageContext(postId?: number): PageContext | null {
    const id = postId ?? this.activePageId;
    if (id === null) return null;
    const ctx = this.pages.get(id);
    if (!ctx) return null;
    if (Date.now() - ctx.lastAccessed > this.timeoutMs) {
      this.pages.delete(id);
      if (this.activePageId === id) this.activePageId = null;
      return null;
    }
    ctx.lastAccessed = Date.now();
    return ctx;
  }

  /** Resolve a post_id: use the provided value, fall back to active page. */
  resolvePostId(provided?: number): number | null {
    if (provided && provided > 0) return provided;
    return this.getActivePageId();
  }

  // ── Cache updates ───────────────────────────────────────────────────

  updateSummary(postId: number, summary: PageSummary): void {
    const ctx = this.pages.get(postId);
    if (ctx) {
      ctx.summary = summary;
      ctx.contentHash = this.hashSummary(summary);
      ctx.lastAccessed = Date.now();
    }
  }

  updateStructure(postId: number, structure: PageStructure): void {
    const ctx = this.pages.get(postId);
    if (ctx) {
      ctx.structure = structure;
      ctx.lastAccessed = Date.now();
    }
  }

  invalidatePage(postId: number): void {
    const ctx = this.pages.get(postId);
    if (ctx) {
      ctx.contentHash = "";
      ctx.structure = undefined;
    }
  }

  // ── Change history ──────────────────────────────────────────────────

  recordChange(
    description: string,
    operationType: ChangeRecord["operationType"],
    affectedIds: string[],
    previousElements?: ElementorElement[]
  ): void {
    this.changeHistory.push({
      timestamp: Date.now(),
      description,
      operationType,
      affectedIds,
      previousElements,
    });
    if (this.changeHistory.length > this.maxHistory) {
      this.changeHistory.shift();
    }
  }

  getRecentChanges(count = 5): ChangeRecord[] {
    return this.changeHistory.slice(-count);
  }

  // ── Helpers ─────────────────────────────────────────────────────────

  private hashSummary(summary: PageSummary): string {
    // Simple hash based on element count + widget types — good enough
    // for screenshot cache invalidation.
    return `${summary.total_elements}-${summary.root_sections}-${Object.keys(summary.widget_summary).sort().join(",")}`;
  }
}
