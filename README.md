# WordPress MCP Abilities Pack

> A collection of WordPress abilities plugins that extend the [MCP Adapter](https://github.com/WordPress/mcp-adapter) with **90+ powerful abilities** for database access, content management, Elementor page building, FacetWP filtering, and SEO management.

Part of the [**AI Building Blocks for WordPress**](https://make.wordpress.org/ai/2025/07/17/ai-building-blocks) ecosystem.

---

## What is MCP?

The [Model Context Protocol (MCP)](https://modelcontextprotocol.io/) is an open standard that enables AI agents (Claude, GPT, Cursor, VS Code Copilot, etc.) to interact with external systems. The **MCP Adapter** bridges WordPress's Abilities API with MCP, allowing AI agents to discover and execute WordPress functionality as tools.

**This abilities pack** extends that functionality with specialized abilities for:
- 🗄️ **Database** - Direct database queries, meta management, options
- 📝 **Content** - Posts, pages, categories, tags, and media management
- 🎨 **Elementor** - Page builder integration, widget management, templates
- 🔎 **FacetWP** - Faceted search and filtering management
- 🔍 **SEO** - Yoast SEO and RankMath metadata management

---

## Prerequisites

- **PHP** >= 7.4
- **WordPress** >= 6.0
- **[WordPress Abilities API](https://github.com/WordPress/abilities-api)** - Core ability registration system
- **[MCP Adapter](https://github.com/WordPress/mcp-adapter)** - MCP protocol bridge

---

## Installation

### Option 1: With Composer (Recommended)

Install the MCP Adapter and Abilities API as dependencies:

```bash
composer require wordpress/abilities-api wordpress/mcp-adapter
```

Then install the abilities pack plugins by downloading and placing them in your `/wp-content/plugins/` directory.

### Option 2: As WordPress Plugins

1. **Install required plugins:**
   - Download and install [Abilities API](https://github.com/WordPress/abilities-api/releases/latest)
   - Download and install [MCP Adapter](https://github.com/WordPress/mcp-adapter/releases/latest)

2. **Install abilities pack plugins:**
   - Download or clone this repository
   - Copy the desired plugin folders to `/wp-content/plugins/`:
     - `wordpress-database-mcp-abilities/`
     - `wordpress-content-mcp-abilities/`
     - `wordpress-elementor-mcp-abilities/`
     - `wordpress-yoast-mcp-abilities/`

3. **Activate** all plugins from WordPress admin → Plugins

```
wp-content/plugins/
├── abilities-api/                      # Required: Core API
├── mcp-adapter/                        # Required: MCP Protocol Bridge
├── wordpress-database-mcp-abilities/   # Database abilities
├── wordpress-content-mcp-abilities/    # Content abilities (posts, media, taxonomies)
├── wordpress-elementor-mcp-abilities/  # Elementor abilities
├── wordpress-facetwp-mcp-abilities/    # FacetWP abilities
└── wordpress-yoast-mcp-abilities/      # SEO abilities
```

---

## Configuration

### Step 1: Create Application Password

WordPress Application Passwords are used for authentication:

1. Navigate to **Users → Profile** in WordPress admin
2. Scroll to **Application Passwords** section
3. Enter a name (e.g., "Claude Desktop", "VS Code Copilot")
4. Click **Add New Application Password**
5. **Copy the generated password** (you won't see it again!)

> **Note**: HTTPS is required for Application Passwords (except localhost).

### Step 2: Configure Your MCP Client

The MCP Adapter creates a default server at:
- **HTTP**: `/wp-json/mcp/mcp-adapter-default-server`
- **STDIO**: `wp mcp-adapter serve --server=mcp-adapter-default-server`

---

### STDIO Transport (Recommended for Local Development)

For local WordPress installations, use STDIO transport with WP-CLI:

#### Claude Desktop / Claude Code / Cursor

Add to your MCP configuration file:

```json
{
  "mcpServers": {
    "wordpress": {
      "command": "wp",
      "args": [
        "--path=/path/to/your/wordpress/site",
        "mcp-adapter",
        "serve",
        "--server=mcp-adapter-default-server",
        "--user=admin"
      ]
    }
  }
}
```

#### VS Code (`.vscode/mcp.json`)

```json
{
  "servers": {
    "wordpress": {
      "command": "wp",
      "args": [
        "--path=/path/to/your/wordpress/site",
        "mcp-adapter",
        "serve",
        "--server=mcp-adapter-default-server",
        "--user=admin"
      ]
    }
  }
}
```

---

### HTTP Transport (Remote Sites)

For remote WordPress sites, use the HTTP transport with the `@automattic/mcp-wordpress-remote` proxy:

#### Claude Desktop / Claude Code / Cursor

```json
{
  "mcpServers": {
    "wordpress": {
      "command": "npx",
      "args": [
        "-y",
        "@automattic/mcp-wordpress-remote@latest"
      ],
      "env": {
        "WP_API_URL": "https://yoursite.com/wp-json/mcp/mcp-adapter-default-server",
        "WP_API_USERNAME": "your-username",
        "WP_API_PASSWORD": "your-application-password"
      }
    }
  }
}
```

#### VS Code (`.vscode/mcp.json`)

```json
{
  "servers": {
    "wordpress": {
      "command": "npx",
      "args": [
        "-y",
        "@automattic/mcp-wordpress-remote@latest"
      ],
      "env": {
        "WP_API_URL": "https://yoursite.com/wp-json/mcp/mcp-adapter-default-server",
        "WP_API_USERNAME": "your-username",
        "WP_API_PASSWORD": "your-application-password"
      }
    }
  }
}
```

---

### Step 3: Verify Connection

Test the connection using WP-CLI:

```bash
# List all available MCP servers
wp mcp-adapter list

# Test discovering abilities
echo '{"jsonrpc":"2.0","id":1,"method":"tools/call","params":{"name":"mcp-adapter-discover-abilities","arguments":{}}}' | wp mcp-adapter serve --user=admin --server=mcp-adapter-default-server
```

Or ask your AI agent:
> "What WordPress abilities are available?"

---

## Enabling Abilities

Each abilities plugin is **automatically enabled** when activated in WordPress. All abilities are exposed via MCP with `mcp.public = true` by default.

| Plugin | Activate If You Need... |
|--------|------------------------|
| `wordpress-database-mcp-abilities` | Direct database access, meta management, options |
| `wordpress-content-mcp-abilities` | Posts, pages, categories, tags, media management |
| `wordpress-elementor-mcp-abilities` | Elementor page builder integration (requires Elementor) |
| `wordpress-facetwp-mcp-abilities` | Faceted search/filtering management (requires FacetWP) |
| `wordpress-yoast-mcp-abilities` | SEO management (requires Yoast SEO or RankMath) |

---

## Available Abilities

### 🗄️ Database Abilities (18 abilities)

Direct database access for querying, searching, and managing WordPress data.

| Ability | Description |
|---------|-------------|
| `database/list-tables` | List all database tables with row counts |
| `database/get-table-structure` | Get column structure for a specific table |
| `database/get-table-sample` | Get sample rows from a table |
| `database/get-table-indexes` | Get index information for a table |
| `database/query` | Execute SELECT queries (read-only) |
| `database/search` | Search across multiple tables |
| `database/info` | Get database info (version, size, stats) |
| `database/get-posts` | Query posts with full meta data |
| `database/get-post-types` | Get summary of all post types |
| `database/get-meta-keys` | Get all unique meta keys in use |
| `database/get-options` | Query the options table |
| `database/get-option` | Get a single option value |
| `database/set-option` | Set or update an option value |
| `database/get-users` | Query users with their meta data |
| `database/get-post-meta` | Get all meta for a specific post |
| `database/set-post-meta` | Set a post meta value |
| `database/delete-post-meta` | Delete a post meta entry |
| `database/analyze-meta` | Analyze meta statistics and usage |

---

### 📝 WordPress Content Abilities (20+ abilities)

Native WordPress content management for posts, pages, categories, tags, and media.

#### Taxonomy Management

| Ability | Description |
|---------|-------------|
| `wordpress/get-categories` | Get WordPress categories |
| `wordpress/create-category` | Create a new category |
| `wordpress/update-category` | Update an existing category |
| `wordpress/delete-category` | Delete a category |
| `wordpress/get-tags` | Get WordPress tags |
| `wordpress/create-tag` | Create a new tag |
| `wordpress/update-tag` | Update an existing tag |
| `wordpress/delete-tag` | Delete a tag |

#### Media Management

| Ability | Description |
|---------|-------------|
| `wordpress/get-media` | Get media library items |
| `wordpress/get-media-item` | Get single media item details |
| `wordpress/upload-media-url` | Upload media from URL |
| `wordpress/update-media` | Update media metadata |
| `wordpress/delete-media` | Delete a media item |
| `wordpress/set-featured-image` | Set post featured image |
| `wordpress/remove-featured-image` | Remove post featured image |

#### Post & Page Management

| Ability | Description |
|---------|-------------|
| `wordpress/create-post` | Create a WordPress post |
| `wordpress/get-post` | Get a post by ID |
| `wordpress/list-posts` | List posts with filtering |
| `wordpress/update-post` | Update an existing post |
| `wordpress/delete-post` | Delete a post |
| `wordpress/create-page` | Create a WordPress page |
| `wordpress/convert-markdown` | Convert Markdown to HTML |

---

### 🎨 Elementor Abilities (27 abilities)

Complete Elementor page builder integration for creating and managing pages, widgets, and templates.

#### Page & Document Management

| Ability | Description |
|---------|-------------|
| `elementor/create-page` | Create a new Elementor page |
| `elementor/get-document` | Get document data by ID |
| `elementor/save-document` | Save document changes |
| `elementor/get-elementor-data` | Get raw Elementor JSON data |
| `elementor/update-elementor-data` | Update raw Elementor data |
| `elementor/list-pages` | List all Elementor pages |
| `elementor/get-page-structure` | Get page structure as a tree |

#### Widget Management

| Ability | Description |
|---------|-------------|
| `elementor/find-widget` | Find a widget by its ID |
| `elementor/update-widget` | Update widget settings |
| `elementor/add-widget` | Add a widget to a page |
| `elementor/remove-widget` | Remove a widget from page |
| `elementor/duplicate-widget` | Duplicate an existing widget |
| `elementor/move-widget` | Move widget to new position |
| `elementor/bulk-update-widgets` | Bulk update multiple widgets |
| `elementor/find-widgets-by-type` | Find all widgets of a type |
| `elementor/list-widgets` | List all available widget types |
| `elementor/get-widget-schema` | Get widget configuration schema |
| `elementor/create-widget-instance` | Create a new widget instance |
| `elementor/get-widget-controls` | Get widget control definitions |

#### Container & Section Management

| Ability | Description |
|---------|-------------|
| `elementor/create-container` | Create a new container element |
| `elementor/add-section` | Add a section to page |

#### Controls & Configuration

| Ability | Description |
|---------|-------------|
| `elementor/list-control-types` | List all available control types |
| `elementor/get-control-schema` | Get control configuration schema |

#### Templates & Cache

| Ability | Description |
|---------|-------------|
| `elementor/list-templates` | List saved templates |
| `elementor/upload-template` | Upload/import a template |
| `elementor/get-template` | Get template data |
| `elementor/delete-template` | Delete a template |
| `elementor/update-template` | Update a template |
| `elementor/duplicate-template` | Duplicate a template |
| `elementor/export-template` | Export template as JSON |
| `elementor/clear-cache` | Clear Elementor cache |
| `elementor/get-info` | Get Elementor system info |

---

### � FacetWP Abilities (17 abilities)

Complete FacetWP integration for managing faceted search, filtering, and indexing.

#### Facet Management

| Ability | Description |
|---------|-------------|
| `facetwp/list-facets` | List all configured facets with settings |
| `facetwp/get-facet` | Get detailed info about a specific facet |
| `facetwp/create-facet` | Create a new facet (checkboxes, dropdown, slider, etc.) |
| `facetwp/update-facet` | Update an existing facet configuration |
| `facetwp/delete-facet` | Delete a facet by name |
| `facetwp/list-facet-types` | List all 18 available facet types |

#### Template Management

| Ability | Description |
|---------|-------------|
| `facetwp/list-templates` | List all FacetWP listing templates |
| `facetwp/get-template` | Get template details and layout |
| `facetwp/create-template` | Create a new listing template |
| `facetwp/update-template` | Update an existing template |
| `facetwp/delete-template` | Delete a template |

#### Index Management

| Ability | Description |
|---------|-------------|
| `facetwp/get-index-status` | Get index stats, row counts, last indexed time |
| `facetwp/rebuild-index` | Trigger full or single-post reindex |
| `facetwp/get-index-progress` | Get current indexing progress percentage |
| `facetwp/purge-index` | Purge the entire index table (use with caution) |

#### Settings & Configuration

| Ability | Description |
|---------|-------------|
| `facetwp/get-settings` | Get current FacetWP settings |
| `facetwp/update-settings` | Update general settings |
| `facetwp/get-info` | Get version, license status, facet/template counts |
| `facetwp/export-settings` | Export all settings as JSON |
| `facetwp/import-settings` | Import settings (merge or overwrite) |
| `facetwp/list-data-sources` | List available data sources (taxonomies, ACF, custom fields) |
| `facetwp/list-indexable-types` | List post types that can be indexed |

---

### �🔍 SEO Abilities (9 abilities)

Manage SEO metadata for both **Yoast SEO** and **RankMath** plugins.

| Ability | Description |
|---------|-------------|
| `seo/set-yoast-seo` | Set Yoast SEO metadata for a post |
| `seo/get-yoast-seo` | Get Yoast SEO metadata for a post |
| `seo/bulk-set-yoast-seo` | Bulk set SEO for multiple posts |
| `seo/get-posts-missing-seo` | Find posts missing SEO metadata |
| `seo/set-rankmath-seo` | Set RankMath SEO metadata |
| `seo/get-rankmath-seo` | Get RankMath SEO metadata |
| `seo/verify-seo-structure` | Validate SEO quality and structure |
| `seo/analyze-keyword-density` | Analyze keyword density in content |
| `seo/check-active-plugin` | Check which SEO plugin is active |

---

## Usage Examples

### Discover Available Abilities

```
AI Agent: "What WordPress abilities are available?"
→ Uses: mcp-adapter-discover-abilities
```

### Query the Database

```
AI Agent: "Show me all posts from the last week with their SEO metadata"
→ Uses: database/get-posts, seo/get-yoast-seo
```

### Create an Elementor Page

```
AI Agent: "Create a new landing page called 'Summer Sale' with Elementor"
→ Uses: elementor/create-page
```

### Bulk Update SEO

```
AI Agent: "Set SEO titles for all posts that are missing them"
→ Uses: seo/get-posts-missing-seo, seo/bulk-set-yoast-seo
```

### Create a FacetWP Facet

```
AI Agent: "Create a category filter facet for my products"
→ Uses: facetwp/create-facet with type=checkboxes, source=tax/category
```

### Check FacetWP Index Status

```
AI Agent: "What's the status of my FacetWP index?"
→ Uses: facetwp/get-index-status, facetwp/get-info
```

---

## Security Considerations

- **Application Passwords**: Secure and revocable from Users → Profile
- **HTTPS Required**: Application Passwords only work over HTTPS (or localhost)
- **Permission Callbacks**: All abilities respect WordPress user capabilities
- **Read-Only Database**: The `database/query` ability only allows SELECT queries
- **Revoke Access**: Revoke application passwords anytime from your user profile

---

## Built-in Abilities

The MCP Adapter includes three built-in abilities for system introspection:

| Ability | Description |
|---------|-------------|
| `mcp-adapter-discover-abilities` | List all available WordPress abilities |
| `mcp-adapter-get-ability-info` | Get detailed info about a specific ability |
| `mcp-adapter-execute-ability` | Execute any registered ability |

---

## Development

Want to create your own abilities? See the [Development Guide](DEVELOPMENT-GUIDE.md) for comprehensive documentation on:

- Ability registration with `wp_register_ability()`
- Input/output JSON schema definitions
- Permission callbacks
- MCP exposure configuration
- Best practices

---

## Resources

- [MCP Adapter Documentation](https://github.com/WordPress/mcp-adapter)
- [WordPress Abilities API](https://github.com/WordPress/abilities-api)
- [Model Context Protocol Specification](https://modelcontextprotocol.io/specification/2025-06-18/)
- [AI Building Blocks for WordPress](https://make.wordpress.org/ai/2025/07/17/ai-building-blocks)

---

## License

GPL-2.0-or-later

---

## Contributing

Contributions are welcome! Please:

1. Fork the repository
2. Create a feature branch
3. Submit a pull request
