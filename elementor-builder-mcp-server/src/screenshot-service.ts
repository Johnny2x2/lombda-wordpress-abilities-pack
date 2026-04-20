import { chromium, type Browser, type BrowserContext, type Page } from "playwright";
import type { Config } from "./config.js";

export type Viewport = "desktop" | "tablet" | "mobile";

const VIEWPORTS: Record<Viewport, { width: number; height: number }> = {
  desktop: { width: 1920, height: 1080 },
  tablet: { width: 768, height: 1024 },
  mobile: { width: 375, height: 812 },
};

/** Maximum height (in CSS pixels) of any screenshot returned by the service. */
export const MAX_IMAGE_HEIGHT = 2048;

/** Result returned for every screenshot capture. */
export interface CaptureResult {
  /** Base64-encoded PNG. */
  base64: string;
  /** Total scrollable height of the page (or element) in pixels. */
  totalHeight: number;
  /** Number of scroll sections (each MAX_IMAGE_HEIGHT tall) that cover totalHeight. */
  sectionCount: number;
  /** 0-based index of the section that was actually captured. */
  sectionIndex: number;
  /** Pixel height of the returned PNG. */
  capturedHeight: number;
  /** Pixel width of the returned PNG. */
  capturedWidth: number;
  /** Y offset (px) at which capture started. */
  yOffset: number;
}

/**
 * Persistent Playwright browser that stays alive across MCP calls.
 * Auto-logs into WordPress on first use and re-uses the session.
 */
export class ScreenshotService {
  private browser: Browser | null = null;
  private context: BrowserContext | null = null;
  private loggedIn = false;
  private config: Config;

  /** Simple in-memory cache: url+viewport+contentHash+section → CaptureResult. */
  private cache = new Map<string, { result: CaptureResult; ts: number }>();
  private cacheTtlMs = 5 * 60 * 1000; // 5 min

  constructor(config: Config) {
    this.config = config;
  }

  // ── Lifecycle ─────────────────────────────────────────────────────

  private async ensureBrowser(): Promise<BrowserContext> {
    if (!this.browser || !this.browser.isConnected()) {
      this.browser = await chromium.launch({ headless: true });
      this.context = await this.browser.newContext({
        viewport: {
          width: this.config.screenshot.viewportWidth,
          height: this.config.screenshot.viewportHeight,
        },
        ignoreHTTPSErrors: true,
      });
      this.loggedIn = false;
    }
    return this.context!;
  }

  private async ensureLoggedIn(): Promise<void> {
    if (this.loggedIn) return;

    const ctx = await this.ensureBrowser();
    const page = await ctx.newPage();

    try {
      const loginUrl = `${this.config.wordpressUrl}/wp-login.php`;
      await page.goto(loginUrl, {
        waitUntil: "networkidle",
        timeout: this.config.screenshot.timeout,
      });

      await page.fill("#user_login", this.config.username);
      await page.fill("#user_pass", this.config.appPassword);
      await page.click("#wp-submit");
      await page.waitForURL("**/wp-admin/**", {
        timeout: this.config.screenshot.timeout,
      });

      this.loggedIn = true;
    } finally {
      await page.close();
    }
  }

  async shutdown(): Promise<void> {
    if (this.context) {
      await this.context.close().catch(() => {});
      this.context = null;
    }
    if (this.browser) {
      await this.browser.close().catch(() => {});
      this.browser = null;
    }
    this.loggedIn = false;
  }

  // ── Public API ────────────────────────────────────────────────────

  /**
   * Capture a section (capped at MAX_IMAGE_HEIGHT) of a post's frontend.
   * scrollSection is a 0-based index; each section is MAX_IMAGE_HEIGHT tall.
   */
  async captureFullPage(
    postId: number,
    viewport: Viewport = "desktop",
    contentHash = "",
    scrollSection = 0
  ): Promise<CaptureResult> {
    const section = Math.max(0, Math.floor(scrollSection));
    const cacheKey = `full-${postId}-${viewport}-${contentHash}-${section}`;
    const cached = this.getCache(cacheKey);
    if (cached) return cached;

    await this.ensureLoggedIn();
    const ctx = await this.ensureBrowser();
    const vp = VIEWPORTS[viewport];

    const page = await ctx.newPage();
    await page.setViewportSize(vp);

    try {
      const permalink = `${this.config.wordpressUrl}/?p=${postId}`;
      await page.goto(permalink, {
        waitUntil: "networkidle",
        timeout: this.config.screenshot.timeout,
      });

      // Wait a tiny bit for any lazy-loaded content
      await page.waitForTimeout(500);

      const result = await this.clipPage(page, vp.width, section);
      this.setCache(cacheKey, result);
      return result;
    } finally {
      await page.close();
    }
  }

  /**
   * Screenshot a specific section/element by its Elementor data-id attribute.
   * If the element is taller than MAX_IMAGE_HEIGHT, scrollSection selects which
   * vertical slice of it to return. Falls back to a clipped full-page capture
   * if the element is not found.
   */
  async captureSection(
    postId: number,
    elementId: string,
    viewport: Viewport = "desktop",
    contentHash = "",
    scrollSection = 0
  ): Promise<CaptureResult> {
    const section = Math.max(0, Math.floor(scrollSection));
    const cacheKey = `section-${postId}-${elementId}-${viewport}-${contentHash}-${section}`;
    const cached = this.getCache(cacheKey);
    if (cached) return cached;

    await this.ensureLoggedIn();
    const ctx = await this.ensureBrowser();
    const vp = VIEWPORTS[viewport];

    const page = await ctx.newPage();
    await page.setViewportSize(vp);

    try {
      const permalink = `${this.config.wordpressUrl}/?p=${postId}`;
      await page.goto(permalink, {
        waitUntil: "networkidle",
        timeout: this.config.screenshot.timeout,
      });
      await page.waitForTimeout(500);

      const selector = `[data-id="${elementId}"]`;
      const element = await page.$(selector);

      if (element) {
        const box = await element.boundingBox();
        if (box) {
          const totalHeight = Math.max(1, Math.ceil(box.height));
          const sectionCount = Math.max(
            1,
            Math.ceil(totalHeight / MAX_IMAGE_HEIGHT)
          );
          const clampedSection = Math.min(section, sectionCount - 1);
          const yWithinElement = clampedSection * MAX_IMAGE_HEIGHT;
          const sliceHeight = Math.max(
            1,
            Math.min(MAX_IMAGE_HEIGHT, totalHeight - yWithinElement)
          );
          const width = Math.max(1, Math.ceil(box.width));
          const buffer = await page.screenshot({
            type: "png",
            clip: {
              x: Math.max(0, Math.floor(box.x)),
              y: Math.max(0, Math.floor(box.y + yWithinElement)),
              width,
              height: sliceHeight,
            },
          });
          const result: CaptureResult = {
            base64: buffer.toString("base64"),
            totalHeight,
            sectionCount,
            sectionIndex: clampedSection,
            capturedHeight: sliceHeight,
            capturedWidth: width,
            yOffset: yWithinElement,
          };
          this.setCache(cacheKey, result);
          return result;
        }
      }

      // Fallback: clipped full page
      const result = await this.clipPage(page, vp.width, section);
      this.setCache(cacheKey, result);
      return result;
    } finally {
      await page.close();
    }
  }

  /**
   * Multi-viewport capture: desktop + tablet + mobile (always section 0).
   */
  async captureMultiViewport(
    postId: number,
    contentHash = ""
  ): Promise<Record<Viewport, CaptureResult>> {
    const [desktop, tablet, mobile] = await Promise.all([
      this.captureFullPage(postId, "desktop", contentHash, 0),
      this.captureFullPage(postId, "tablet", contentHash, 0),
      this.captureFullPage(postId, "mobile", contentHash, 0),
    ]);
    return { desktop, tablet, mobile };
  }

  /** Invalidate cached screenshots for a post. */
  invalidatePost(postId: number): void {
    for (const key of this.cache.keys()) {
      if (key.includes(`-${postId}-`)) {
        this.cache.delete(key);
      }
    }
  }

  // ── Internals ─────────────────────────────────────────────────────

  /** Capture a 2048-tall slice of the current page at the given section index. */
  private async clipPage(
    page: Page,
    viewportWidth: number,
    section: number
  ): Promise<CaptureResult> {
    const totalHeight = await page.evaluate(() => {
      const d = document.documentElement;
      const b = document.body;
      return Math.max(
        d.scrollHeight,
        d.offsetHeight,
        b ? b.scrollHeight : 0,
        b ? b.offsetHeight : 0
      );
    });

    const safeTotal = Math.max(1, Math.ceil(totalHeight));
    const sectionCount = Math.max(1, Math.ceil(safeTotal / MAX_IMAGE_HEIGHT));
    const clampedSection = Math.min(section, sectionCount - 1);
    const y = clampedSection * MAX_IMAGE_HEIGHT;
    const height = Math.max(1, Math.min(MAX_IMAGE_HEIGHT, safeTotal - y));

    const buffer = await page.screenshot({
      type: "png",
      clip: {
        x: 0,
        y,
        width: viewportWidth,
        height,
      },
    });

    return {
      base64: buffer.toString("base64"),
      totalHeight: safeTotal,
      sectionCount,
      sectionIndex: clampedSection,
      capturedHeight: height,
      capturedWidth: viewportWidth,
      yOffset: y,
    };
  }

  // ── Cache helpers ─────────────────────────────────────────────────

  private getCache(key: string): CaptureResult | null {
    const entry = this.cache.get(key);
    if (!entry) return null;
    if (Date.now() - entry.ts > this.cacheTtlMs) {
      this.cache.delete(key);
      return null;
    }
    return entry.result;
  }

  private setCache(key: string, result: CaptureResult): void {
    this.cache.set(key, { result, ts: Date.now() });
  }
}
