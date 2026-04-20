import axios, { type AxiosInstance } from "axios";
import type { Config } from "./config.js";

// ── WordPress REST-API types ────────────────────────────────────────────

export interface ElementorElement {
  id: string;
  elType: "container" | "section" | "column" | "widget";
  widgetType?: string;
  settings?: Record<string, unknown>;
  elements?: ElementorElement[];
  isInner?: boolean;
}

export interface PageSummary {
  post_id: number;
  post_title: string;
  post_status: string;
  permalink: string;
  edit_url: string;
  total_elements: number;
  root_sections: number;
  max_depth: number;
  widget_summary: Record<string, number>;
  sections: SectionSummary[];
  container_type: string;
}

export interface SectionSummary {
  index: number;
  id: string;
  elType: string;
  childCount: number;
  containerType?: string;
  text_preview?: { widget_type: string; key: string; text: string }[];
  widget_types?: string[];
}

export interface ContainerTypeInfo {
  container_type: "flexbox" | "legacy";
  supports_flexbox: boolean;
  supports_grid: boolean;
  recommended_root_element: string;
  capabilities: Record<string, unknown>;
  elementor_version: string;
}

export interface PageListItem {
  id: number;
  title: string;
  type: string;
  status: string;
  permalink: string;
  edit_url: string;
}

export interface BlueprintResult {
  success: boolean;
  post_id: number;
  element_count: number;
  root_sections: number;
  preview_url: string;
  edit_url: string;
}

export interface BatchResult {
  success: boolean;
  operations_count: number;
  results: { op: string; affected_id: string }[];
  affected_ids: string[];
  element_count: number;
}

export interface WidgetInfo {
  name: string;
  title: string;
  icon: string;
  categories: string[];
  keywords: string[];
}

export interface DocumentData {
  post_id: number;
  post_title: string;
  post_type: string;
  post_status: string;
  elements: ElementorElement[];
  settings: Record<string, unknown>;
  edit_url: string;
  preview_url: string;
  permalink: string;
}

export interface PageStructure {
  post_id: number;
  post_title: string;
  structure: StructureNode[];
  element_count: number;
}

export interface StructureNode {
  id: string;
  elType: string;
  widgetType?: string | null;
  isInner?: boolean;
  depth: number;
  index: number;
  containerType?: string;
  settings?: Record<string, unknown>;
  children?: StructureNode[];
  childCount: number;
}

// ── Client ──────────────────────────────────────────────────────────────

export class WordPressClient {
  private http: AxiosInstance;
  private abilitiesBase: string;

  constructor(private config: Config) {
    const auth = config.username && config.appPassword
      ? {
          username: config.username,
          password: config.appPassword,
        }
      : undefined;

    this.http = axios.create({
      baseURL: config.wordpressUrl,
      auth,
      timeout: 30_000,
      headers: { "Content-Type": "application/json" },
    });

    this.abilitiesBase = `${config.wordpressUrl}/wp-json/wp-abilities/v1`;
  }

  // ── Low-level ability executor ──────────────────────────────────────

  private async executeAbility<T = unknown>(
    abilityName: string,
    input: Record<string, unknown> = {}
  ): Promise<T> {
    const resp = await this.http.post<T>(
      `${this.abilitiesBase}/abilities/${abilityName}/run`,
      { input }
    );
    return resp.data;
  }

  // ── Document-level ──────────────────────────────────────────────────

  async getDocument(postId: number): Promise<DocumentData> {
    return this.executeAbility<DocumentData>("elementor/get-document", {
      post_id: postId,
    });
  }

  async getPageStructure(
    postId: number,
    includeSettings = false
  ): Promise<PageStructure> {
    return this.executeAbility<PageStructure>(
      "elementor/get-page-structure",
      { post_id: postId, include_settings: includeSettings }
    );
  }

  async listPages(
    perPage = 50,
    postType?: string
  ): Promise<PageListItem[]> {
    const input: Record<string, unknown> = { per_page: perPage };
    if (postType) input.post_type = postType;
    return this.executeAbility<PageListItem[]>(
      "elementor/list-pages",
      input
    );
  }

  // ── Builder endpoints (new Phase-1 abilities) ─────────────────────

  async getContainerType(): Promise<ContainerTypeInfo> {
    return this.executeAbility<ContainerTypeInfo>(
      "elementor/get-container-type"
    );
  }

  async getPageSummary(postId: number): Promise<PageSummary> {
    return this.executeAbility<PageSummary>(
      "elementor/get-page-summary",
      { post_id: postId }
    );
  }

  async applyBlueprint(
    postId: number,
    elements: ElementorElement[],
    options?: {
      settings?: Record<string, unknown>;
      autoWrapWidgets?: boolean;
      generateIds?: boolean;
    }
  ): Promise<BlueprintResult> {
    return this.executeAbility<BlueprintResult>(
      "elementor/apply-blueprint",
      {
        post_id: postId,
        elements,
        settings: options?.settings,
        auto_wrap_widgets: options?.autoWrapWidgets ?? true,
        generate_ids: options?.generateIds ?? false,
      }
    );
  }

  async applyBatch(
    postId: number,
    operations: Record<string, unknown>[]
  ): Promise<BatchResult> {
    return this.executeAbility<BatchResult>("elementor/apply-batch", {
      post_id: postId,
      operations,
    });
  }

  // ── Widget discovery ────────────────────────────────────────────────

  async listWidgets(
    search?: string,
    category?: string
  ): Promise<{ widgets: Record<string, WidgetInfo>; count: number }> {
    const input: Record<string, unknown> = {};
    if (search) input.search = search;
    if (category) input.category = category;
    return this.executeAbility("elementor/list-widgets", input);
  }

  async getWidgetSchema(
    widgetName: string
  ): Promise<Record<string, unknown>> {
    return this.executeAbility("elementor/get-widget-schema", {
      widget_name: widgetName,
    });
  }

  // ── Misc ────────────────────────────────────────────────────────────

  async createPage(
    title: string,
    options?: { postType?: string; status?: string; template?: string }
  ): Promise<{ post_id: number; edit_url: string; preview_url: string }> {
    return this.executeAbility("elementor/create-page", {
      title,
      post_type: options?.postType ?? "page",
      status: options?.status ?? "draft",
      template: options?.template,
    });
  }

  async clearCache(): Promise<{ success: boolean }> {
    return this.executeAbility("elementor/clear-cache");
  }

  async getInfo(): Promise<Record<string, unknown>> {
    return this.executeAbility("elementor/get-info", {
      include_widgets: true,
      include_settings: true,
    });
  }

  /** Quick connectivity check. */
  async ping(): Promise<boolean> {
    try {
      await this.http.get("/wp-json/");
      return true;
    } catch {
      return false;
    }
  }
}
