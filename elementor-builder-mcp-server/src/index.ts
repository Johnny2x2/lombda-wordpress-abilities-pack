#!/usr/bin/env node
import { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import { StdioServerTransport } from "@modelcontextprotocol/sdk/server/stdio.js";
import { z } from "zod";

import { loadConfig } from "./config.js";
import { WordPressClient, type ElementorElement } from "./wordpress-client.js";
import { SessionManager } from "./session-manager.js";
import {
  ScreenshotService,
  MAX_IMAGE_HEIGHT,
  type Viewport,
  type CaptureResult,
} from "./screenshot-service.js";

// ── Bootstrap ───────────────────────────────────────────────────────────

const config = loadConfig();
const wp = new WordPressClient(config);
const session = new SessionManager(config.sessionTimeoutMinutes);
const screenshots = new ScreenshotService(config);

const server = new McpServer({
  name: "elementor-builder",
  version: "1.0.0",
});

// ── Helper: resolve post_id with session fallback ───────────────────────

function resolvePostId(provided?: number): number {
  const id = session.resolvePostId(provided);
  if (!id) {
    throw new Error(
      "No post_id provided and no active page in session. Call view_page first."
    );
  }
  return id;
}

// ── Helper: shared scroll_section Zod schema ────────────────────────────

const scrollSectionField = z
  .number()
  .int()
  .min(0)
  .optional()
  .describe(
    `0-based vertical section index for the returned screenshot. Each section is ${MAX_IMAGE_HEIGHT} px tall (the max returned image height). Pass 0 for the top of the page (default), 1 for the next ${MAX_IMAGE_HEIGHT} px down, etc. The response includes a section_count so you know how many sections exist.`
  );

// ── Helper: format capture metadata + image into MCP content items ──────

function pushCaptureContent(
  content: Array<
    | { type: "text"; text: string }
    | { type: "image"; data: string; mimeType: string }
  >,
  capture: CaptureResult,
  label?: string
): void {
  const prefix = label ? `${label} ` : "";
  const next =
    capture.sectionIndex + 1 < capture.sectionCount
      ? ` Pass scroll_section=${capture.sectionIndex + 1} to view the next section.`
      : "";
  content.push({
    type: "text" as const,
    text:
      `${prefix}Screenshot: section ${capture.sectionIndex} of ${capture.sectionCount} ` +
      `(y=${capture.yOffset}, captured ${capture.capturedWidth}x${capture.capturedHeight} px, ` +
      `total page height=${capture.totalHeight} px, max per-section height=${MAX_IMAGE_HEIGHT} px).${next}`,
  });
  content.push({
    type: "image" as const,
    data: capture.base64,
    mimeType: "image/png",
  });
}

// ── Tool: list_pages ────────────────────────────────────────────────────

server.tool(
  "list_pages",
  "List all Elementor-built pages with their status, URL, and type. Use this to discover which pages exist before viewing or editing them.",
  {
    per_page: z
      .number()
      .optional()
      .describe("Number of results to return (default: 50)."),
    post_type: z
      .enum(["page", "post", "any"])
      .optional()
      .describe("Filter by post type."),
  },
  async ({ per_page, post_type }) => {
    const pages = await wp.listPages(per_page ?? 50, post_type);
    return {
      content: [
        {
          type: "text" as const,
          text: JSON.stringify(pages, null, 2),
        },
      ],
    };
  }
);

// ── Tool: view_page ─────────────────────────────────────────────────────

server.tool(
  "view_page",
  `Get a page summary and a screenshot of the page. Sets this page as the active page for subsequent calls. Returns: section layout, widget types, text previews, and a rendered screenshot.
Screenshots are capped at ${MAX_IMAGE_HEIGHT} px tall — use scroll_section to view lower portions of long pages.`,
  {
    post_id: z.number().describe("The WordPress post/page ID."),
    viewport: z
      .enum(["desktop", "tablet", "mobile"])
      .optional()
      .describe("Viewport size for screenshot (default: desktop)."),
    include_screenshot: z
      .boolean()
      .optional()
      .describe("Include a rendered screenshot (default: true)."),
    scroll_section: scrollSectionField,
  },
  async ({ post_id, viewport, include_screenshot, scroll_section }) => {
    const [summary, containerType] = await Promise.all([
      wp.getPageSummary(post_id),
      wp.getContainerType(),
    ]);

    session.setActivePage(post_id, summary, containerType);

    const content: Array<
      | { type: "text"; text: string }
      | { type: "image"; data: string; mimeType: string }
    > = [
      {
        type: "text" as const,
        text: JSON.stringify(summary, null, 2),
      },
    ];

    if (include_screenshot !== false) {
      try {
        const vp = (viewport ?? "desktop") as Viewport;
        const ctx = session.getPageContext(post_id);
        const capture = await screenshots.captureFullPage(
          post_id,
          vp,
          ctx?.contentHash ?? "",
          scroll_section ?? 0
        );
        pushCaptureContent(content, capture);
      } catch (err) {
        content.push({
          type: "text" as const,
          text: `[Screenshot unavailable: ${err instanceof Error ? err.message : String(err)}]`,
        });
      }
    }

    return { content };
  }
);

// ── Tool: view_section ──────────────────────────────────────────────────

server.tool(
  "view_section",
  `Screenshot a specific section or element by its Elementor ID. Useful for inspecting just one part of a page.
Screenshots are capped at ${MAX_IMAGE_HEIGHT} px tall — if the section is taller, use scroll_section to view lower slices.`,
  {
    element_id: z.string().describe("The Elementor element ID to capture."),
    post_id: z
      .number()
      .optional()
      .describe(
        "The post ID (defaults to active page if set via view_page)."
      ),
    viewport: z
      .enum(["desktop", "tablet", "mobile"])
      .optional()
      .describe("Viewport size (default: desktop)."),
    scroll_section: scrollSectionField,
  },
  async ({ element_id, post_id, viewport, scroll_section }) => {
    const resolvedId = resolvePostId(post_id);
    const vp = (viewport ?? "desktop") as Viewport;
    const ctx = session.getPageContext(resolvedId);

    const content: Array<
      | { type: "text"; text: string }
      | { type: "image"; data: string; mimeType: string }
    > = [];

    try {
      const capture = await screenshots.captureSection(
        resolvedId,
        element_id,
        vp,
        ctx?.contentHash ?? "",
        scroll_section ?? 0
      );
      pushCaptureContent(content, capture);
    } catch (err) {
      content.push({
        type: "text" as const,
        text: `[Screenshot unavailable: ${err instanceof Error ? err.message : String(err)}]`,
      });
    }

    return { content };
  }
);

// ── Tool: build_page ────────────────────────────────────────────────────

server.tool(
  "build_page",
  `Build or rebuild an entire Elementor page from a blueprint in ONE call.
Provide the full element tree (containers with nested widgets and all settings).
Replaces all existing page content. Auto-wraps root-level widgets in containers.
Returns the result plus a screenshot of the built page.`,
  {
    post_id: z
      .number()
      .optional()
      .describe("Target post ID (defaults to active page)."),
    elements: z
      .array(z.record(z.unknown()))
      .describe(
        "Full array of Elementor elements. Root elements should be containers. Widgets go nested inside."
      ),
    settings: z
      .record(z.unknown())
      .optional()
      .describe("Optional document-level page settings."),
    auto_wrap_widgets: z
      .boolean()
      .optional()
      .describe("Auto-wrap root widgets in containers (default: true)."),
    generate_ids: z
      .boolean()
      .optional()
      .describe(
        "Generate fresh IDs for all elements (default: false)."
      ),
    take_screenshot: z
      .boolean()
      .optional()
      .describe("Capture screenshot after building (default: true)."),
    scroll_section: scrollSectionField,
  },
  async ({
    post_id,
    elements,
    settings,
    auto_wrap_widgets,
    generate_ids,
    take_screenshot,
    scroll_section,
  }) => {
    const resolvedId = resolvePostId(post_id);

    const result = await wp.applyBlueprint(
      resolvedId,
      elements as unknown as ElementorElement[],
      {
        settings: settings as Record<string, unknown> | undefined,
        autoWrapWidgets: auto_wrap_widgets,
        generateIds: generate_ids,
      }
    );

    // Refresh session context.
    const summary = await wp.getPageSummary(resolvedId);
    const containerType = await wp.getContainerType();
    session.setActivePage(resolvedId, summary, containerType);
    session.recordChange(
      `Built page with ${result.element_count} elements`,
      "blueprint",
      []
    );
    screenshots.invalidatePost(resolvedId);

    const content: Array<
      | { type: "text"; text: string }
      | { type: "image"; data: string; mimeType: string }
    > = [
      {
        type: "text" as const,
        text: JSON.stringify(result, null, 2),
      },
    ];

    if (take_screenshot !== false) {
      try {
        const ctx = session.getPageContext(resolvedId);
        const capture = await screenshots.captureFullPage(
          resolvedId,
          "desktop",
          ctx?.contentHash ?? "",
          scroll_section ?? 0
        );
        pushCaptureContent(content, capture);
      } catch (err) {
        content.push({
          type: "text" as const,
          text: `[Screenshot unavailable: ${err instanceof Error ? err.message : String(err)}]`,
        });
      }
    }

    return { content };
  }
);

// ── Tool: edit_section ──────────────────────────────────────────────────

server.tool(
  "edit_section",
  `Replace an entire section/container by ID with a new element definition.
Useful when you want to redesign one section without rebuilding the whole page.`,
  {
    element_id: z
      .string()
      .describe("The element ID of the section to replace."),
    element: z
      .record(z.unknown())
      .describe(
        "The new element definition (container/section with nested widgets)."
      ),
    post_id: z
      .number()
      .optional()
      .describe("Post ID (defaults to active page)."),
    take_screenshot: z
      .boolean()
      .optional()
      .describe("Capture after-screenshot (default: true)."),
    scroll_section: scrollSectionField,
  },
  async ({ element_id, element, post_id, take_screenshot, scroll_section }) => {
    const resolvedId = resolvePostId(post_id);

    const result = await wp.applyBatch(resolvedId, [
      {
        op: "replace",
        element_id,
        element,
      },
    ]);

    // Refresh session.
    const summary = await wp.getPageSummary(resolvedId);
    session.updateSummary(resolvedId, summary);
    session.recordChange(
      `Replaced section ${element_id}`,
      "batch",
      result.affected_ids
    );
    screenshots.invalidatePost(resolvedId);

    const content: Array<
      | { type: "text"; text: string }
      | { type: "image"; data: string; mimeType: string }
    > = [
      {
        type: "text" as const,
        text: JSON.stringify(result, null, 2),
      },
    ];

    if (take_screenshot !== false) {
      try {
        const ctx = session.getPageContext(resolvedId);
        const capture = await screenshots.captureFullPage(
          resolvedId,
          "desktop",
          ctx?.contentHash ?? "",
          scroll_section ?? 0
        );
        pushCaptureContent(content, capture);
      } catch (err) {
        content.push({
          type: "text" as const,
          text: `[Screenshot unavailable: ${err instanceof Error ? err.message : String(err)}]`,
        });
      }
    }

    return { content };
  }
);

// ── Tool: apply_changes ─────────────────────────────────────────────────

server.tool(
  "apply_changes",
  `Apply multiple element operations atomically in a single call.
All operations succeed or all are rolled back.
Supported operations: add, update, remove, move, duplicate, replace.
Returns the result plus an optional screenshot.`,
  {
    post_id: z
      .number()
      .optional()
      .describe("Post ID (defaults to active page)."),
    operations: z
      .array(z.record(z.unknown()))
      .describe(
        `Array of operations. Each needs an "op" field.
Examples:
  {"op":"update", "element_id":"abc123", "settings":{"title":"New Title"}}
  {"op":"add", "container_id":"def456", "widget_type":"heading", "settings":{"title":"Hello"}}
  {"op":"remove", "element_id":"ghi789"}
  {"op":"move", "element_id":"abc123", "target_container_id":"def456", "position":0}
  {"op":"duplicate", "element_id":"abc123"}
  {"op":"replace", "element_id":"abc123", "element":{...full element...}}`
      ),
    take_screenshot: z
      .boolean()
      .optional()
      .describe("Capture screenshot after changes (default: true)."),
    scroll_section: scrollSectionField,
  },
  async ({ post_id, operations, take_screenshot, scroll_section }) => {
    const resolvedId = resolvePostId(post_id);

    const result = await wp.applyBatch(resolvedId, operations);

    // Refresh session.
    const summary = await wp.getPageSummary(resolvedId);
    session.updateSummary(resolvedId, summary);
    session.recordChange(
      `Applied ${result.operations_count} operations`,
      "batch",
      result.affected_ids
    );
    screenshots.invalidatePost(resolvedId);

    const content: Array<
      | { type: "text"; text: string }
      | { type: "image"; data: string; mimeType: string }
    > = [
      {
        type: "text" as const,
        text: JSON.stringify(result, null, 2),
      },
    ];

    if (take_screenshot !== false) {
      try {
        const ctx = session.getPageContext(resolvedId);
        const capture = await screenshots.captureFullPage(
          resolvedId,
          "desktop",
          ctx?.contentHash ?? "",
          scroll_section ?? 0
        );
        pushCaptureContent(content, capture);
      } catch (err) {
        content.push({
          type: "text" as const,
          text: `[Screenshot unavailable: ${err instanceof Error ? err.message : String(err)}]`,
        });
      }
    }

    return { content };
  }
);

// ── Tool: preview_changes ───────────────────────────────────────────────

server.tool(
  "preview_changes",
  `Apply changes to a page and take a screenshot to preview the result.
If you don't like the result, the changes are already saved — use apply_changes
to further modify. Combines apply + screenshot in one call.`,
  {
    post_id: z
      .number()
      .optional()
      .describe("Post ID (defaults to active page)."),
    operations: z
      .array(z.record(z.unknown()))
      .describe("Array of batch operations (same format as apply_changes)."),
    viewports: z
      .array(z.enum(["desktop", "tablet", "mobile"]))
      .optional()
      .describe(
        "Viewports to capture (default: [desktop]). Use multiple to see responsive behavior."
      ),
    scroll_section: scrollSectionField,
  },
  async ({ post_id, operations, viewports, scroll_section }) => {
    const resolvedId = resolvePostId(post_id);

    // Capture before screenshot.
    let beforeCapture: CaptureResult | null = null;
    try {
      beforeCapture = await screenshots.captureFullPage(
        resolvedId,
        "desktop",
        "", // force fresh capture
        scroll_section ?? 0
      );
    } catch {
      // Non-fatal — we'll just skip the before image.
    }

    // Apply the changes.
    const result = await wp.applyBatch(resolvedId, operations);

    // Refresh session context.
    const summary = await wp.getPageSummary(resolvedId);
    session.updateSummary(resolvedId, summary);
    session.recordChange(
      `Preview: ${result.operations_count} operations`,
      "batch",
      result.affected_ids
    );
    screenshots.invalidatePost(resolvedId);

    const content: Array<
      | { type: "text"; text: string }
      | { type: "image"; data: string; mimeType: string }
    > = [
      {
        type: "text" as const,
        text: JSON.stringify(
          { ...result, note: "Changes applied. Screenshots below." },
          null,
          2
        ),
      },
    ];

    // Add before screenshot.
    if (beforeCapture) {
      pushCaptureContent(content, beforeCapture, "--- BEFORE ---");
    }

    // Capture after screenshots for each viewport.
    const vps = viewports ?? ["desktop"];
    for (const vp of vps) {
      try {
        const ctx = session.getPageContext(resolvedId);
        const capture = await screenshots.captureFullPage(
          resolvedId,
          vp as Viewport,
          ctx?.contentHash ?? "",
          scroll_section ?? 0
        );
        pushCaptureContent(content, capture, `--- AFTER (${vp}) ---`);
      } catch (err) {
        content.push({
          type: "text" as const,
          text: `[${vp} screenshot unavailable: ${err instanceof Error ? err.message : String(err)}]`,
        });
      }
    }

    return { content };
  }
);

// ── Tool: get_widget_palette ────────────────────────────────────────────

server.tool(
  "get_widget_palette",
  `List all available Elementor widgets with their names, icons, categories, and keywords.
Optionally filter by search term or category. Use this to discover what widgets
are available before building pages.`,
  {
    search: z
      .string()
      .optional()
      .describe("Search by name, title, or keywords."),
    category: z
      .string()
      .optional()
      .describe(
        "Filter by category (basic, general, pro-elements, etc.)."
      ),
    get_schema_for: z
      .string()
      .optional()
      .describe(
        "Also fetch the full control schema for this widget (e.g. 'button')."
      ),
  },
  async ({ search, category, get_schema_for }) => {
    const widgetList = await wp.listWidgets(search, category);

    const content: Array<{ type: "text"; text: string }> = [
      {
        type: "text" as const,
        text: JSON.stringify(widgetList, null, 2),
      },
    ];

    if (get_schema_for) {
      try {
        const schema = await wp.getWidgetSchema(get_schema_for);
        content.push({
          type: "text" as const,
          text: `\n--- Widget Schema: ${get_schema_for} ---\n${JSON.stringify(schema, null, 2)}`,
        });
      } catch (err) {
        content.push({
          type: "text" as const,
          text: `[Schema fetch failed: ${err instanceof Error ? err.message : String(err)}]`,
        });
      }
    }

    return { content };
  }
);

// ── Start ───────────────────────────────────────────────────────────────

async function main() {
  const transport = new StdioServerTransport();
  await server.connect(transport);
  console.error("Elementor Builder MCP Server started (stdio).");
}

main().catch((err) => {
  console.error("Fatal:", err);
  process.exit(1);
});

// Clean shutdown.
process.on("SIGINT", async () => {
  await screenshots.shutdown();
  process.exit(0);
});

process.on("SIGTERM", async () => {
  await screenshots.shutdown();
  process.exit(0);
});
