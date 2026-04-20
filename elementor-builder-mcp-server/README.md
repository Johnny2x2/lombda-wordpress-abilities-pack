# Elementor Builder MCP Server

A high-level MCP server for building Elementor pages with visual feedback. Replaces 27 granular tools with 8 purpose-built tools that include screenshot previews of changes.

## Architecture

```
AI Agent (Claude, Copilot, etc.)
    ↓ MCP Protocol (stdio)
[This Node.js Server]         ← orchestration + screenshots
    ↓ WordPress REST API
[Elementor Abilities Plugin]  ← PHP endpoints in WordPress
    ↓ Elementor Document API
WordPress / Elementor
```

## Tools

| Tool | Description |
|------|-------------|
| `list_pages` | List all Elementor-built pages |
| `view_page` | Page summary + full screenshot (sets active page) |
| `view_section` | Screenshot a specific section by ID |
| `build_page` | Build entire page from blueprint in ONE call |
| `edit_section` | Replace a section with a new definition |
| `apply_changes` | Batch operations (add/update/remove/move/duplicate/replace) |
| `preview_changes` | Apply changes + before/after screenshots |
| `get_widget_palette` | List available widgets with optional schema |

## Setup

### 1. Prerequisites

- Node.js 18+
- WordPress site with:
  - [Elementor](https://elementor.com/) installed
  - [MCP Adapter](https://wordpress.org/plugins/mcp-adapter/) plugin (0.4.1+)
  - [Elementor Abilities](../wordpress-elementor-mcp-abilities/) plugin with the Phase 1 builder endpoints
- A WordPress Application Password (Settings → Users → Application Passwords)

### 2. Install

```bash
cd elementor-builder-mcp-server
npm install
npx playwright install chromium
npm run build
```

### 3. Configure

Copy `.env.example` to `.env` and fill in your WordPress credentials:

```bash
cp .env.example .env
```

```env
WORDPRESS_URL=http://santron.local
WORDPRESS_USERNAME=admin
WORDPRESS_APP_PASSWORD=xxxx xxxx xxxx xxxx xxxx xxxx
```

### 4. Add to VS Code MCP settings

Add to your VS Code `settings.json` or `.vscode/mcp.json`:

```json
{
  "mcpServers": {
    "elementor-builder": {
      "command": "node",
      "args": ["d:/Repos/lombda-wordpress-abilities-pack/elementor-builder-mcp-server/dist/index.js"],
      "env": {
        "WORDPRESS_URL": "http://website.local",
        "WORDPRESS_USERNAME": "admin",
        "WORDPRESS_APP_PASSWORD": "xxxx xxxx xxxx xxxx xxxx xxxx"
      }
    }
  }
}
```

Or use the dev script with tsx (no build step):

```json
{
  "mcpServers": {
    "elementor-builder": {
      "command": "npx",
      "args": ["tsx", "d:/Repos/lombda-wordpress-abilities-pack/elementor-builder-mcp-server/src/index.ts"]
    }
  }
}
```

## Usage Examples

### Build a page from scratch

```
"Build me a product listing page with a hero banner, a filter sidebar, and a 3-column product grid"
```

The AI will:
1. Call `view_page` to see what's currently there
2. Call `get_widget_palette` to find available widgets
3. Call `build_page` with the full blueprint → gets screenshot back
4. Done in 3 calls instead of 15+

### Edit a specific section

```
"Change the hero section heading to 'Welcome to Santron' and make the background dark blue"
```

The AI will:
1. Call `view_page` (if not already active) → sees current layout + screenshot
2. Call `apply_changes` with update operations → sees screenshot of result

### Preview responsive layout

```
"Show me how the page looks on mobile"
```

The AI will:
1. Call `view_page` with `viewport: "mobile"` → gets mobile screenshot

## Session Context

Once you call `view_page(post_id)`, that page becomes the "active page". Subsequent calls to `build_page`, `apply_changes`, etc. will default to that page without needing to specify `post_id` again. The session expires after 30 minutes of inactivity.

## Development

```bash
npm run dev     # Run with tsx (no build step, hot reload)
npm run build   # Build to dist/
npm start       # Run built version
```
