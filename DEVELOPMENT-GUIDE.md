# WordPress Abilities Plugin Development Guide

> A comprehensive guide for creating WordPress Abilities plugins that integrate with the MCP Adapter for AI agent interaction.

## Table of Contents

1. [Overview](#overview)
2. [Architecture](#architecture)
3. [Step-by-Step Implementation](#step-by-step-implementation)
4. [Common Issues & Solutions](#common-issues--solutions)
5. [Debugging Techniques](#debugging-techniques)
6. [Best Practices](#best-practices)
7. [Reference Examples](#reference-examples)

---

## Overview

### What are WordPress Abilities?

WordPress Abilities are a standardized way to expose functionality to AI agents and external tools. The Abilities API provides a registry system where plugins can register "abilities" - discrete units of functionality with defined inputs, outputs, and permissions.

### Key Components

| Component | Purpose |
|-----------|---------|
| **Abilities API Plugin** | Core plugin providing `wp_register_ability()` and registry |
| **MCP Adapter Plugin** | Exposes abilities via Model Context Protocol for AI agents |
| **Your Abilities Plugin** | Registers custom abilities for your specific use case |

### How It Works

```
AI Agent (Claude, etc.)
    ↓
MCP Protocol
    ↓
MCP Adapter Plugin (filters abilities with mcp.public = true)
    ↓
WordPress Abilities API Registry
    ↓
Your Custom Abilities Plugin
    ↓
WordPress/Plugin Functionality
```

---

## Architecture

### Plugin Structure

```
your-abilities-plugin/
├── your-abilities-plugin.php    # Main plugin file
├── includes/                     # Optional: separate ability classes
│   ├── class-page-abilities.php
│   └── class-template-abilities.php
└── DEVELOPMENT-GUIDE.md         # This documentation
```

### Required Hooks

Your plugin MUST hook into these actions in the correct order:

1. **`wp_abilities_api_categories_init`** - Register your ability category
2. **`wp_abilities_api_init`** - Register your abilities

```php
add_action( 'wp_abilities_api_categories_init', 'register_my_category' );
add_action( 'wp_abilities_api_init', 'register_my_abilities' );
```

### Ability Registration Flow

```
WordPress Boot
    ↓
Plugin Loads → Hooks registered
    ↓
WP_Abilities_Registry::get_instance() called (by REST API or MCP)
    ↓
do_action('wp_abilities_api_categories_init') ← Register categories here
    ↓
do_action('wp_abilities_api_init') ← Register abilities here
    ↓
Registry populated, abilities available
```

---

## Step-by-Step Implementation

### Step 1: Create Plugin Header

```php
<?php
/**
 * Plugin Name: Your Abilities Plugin
 * Description: Exposes [Your Feature] as WordPress Abilities for AI agents via MCP.
 * Version: 1.0.0
 * Author: Your Name
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
```

### Step 2: Create Plugin Class

```php
class Your_Abilities_Plugin {
    private static $instance = null;
    
    // Category slug - must be lowercase with hyphens only
    private static $category = 'your-plugin';
    
    // Meta configuration for MCP exposure
    private static $mcp_meta = array(
        'show_in_rest' => true,
        'mcp'          => array( 'public' => true ),
    );

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // CRITICAL: Hook into BOTH actions
        add_action( 'wp_abilities_api_categories_init', array( $this, 'register_category' ) );
        add_action( 'wp_abilities_api_init', array( $this, 'register_abilities' ) );
    }
}

// Initialize the plugin
Your_Abilities_Plugin::instance();
```

### Step 3: Register Category

```php
public function register_category() {
    wp_register_ability_category(
        self::$category,  // Slug: lowercase, alphanumeric, hyphens only
        array(
            'label'       => 'Your Plugin',
            'description' => 'Abilities for managing Your Plugin features.',
        )
    );
}
```

### Step 4: Register Abilities

```php
public function register_abilities() {
    wp_register_ability( 'your-plugin/your-ability', array(
        // REQUIRED: Human-readable label
        'label' => 'Your Ability Name',
        
        // REQUIRED: Detailed description for AI agents
        'description' => 'What this ability does in detail.',
        
        // REQUIRED: Must match a registered category
        'category' => self::$category,
        
        // REQUIRED: JSON Schema for input validation
        'input_schema' => array(
            'type' => 'object',
            'properties' => array(
                'param_name' => array(
                    'type' => 'string',
                    'description' => 'What this parameter does.',
                ),
            ),
            'required' => array( 'param_name' ),
        ),
        
        // REQUIRED: JSON Schema for output
        'output_schema' => array(
            'type' => 'object',
            'properties' => array(
                'result' => array( 'type' => 'string' ),
            ),
        ),
        
        // REQUIRED: The function that executes the ability
        'execute_callback' => function( $input ) {
            // Your logic here
            return array( 'result' => 'success' );
        },
        
        // REQUIRED: Permission check
        'permission_callback' => function() {
            return current_user_can( 'edit_posts' );
        },
        
        // REQUIRED for MCP: Meta with mcp.public = true
        'meta' => self::$mcp_meta,
    ) );
}
```

---

## Common Issues & Solutions

### Issue 1: Abilities Not Appearing in MCP Discovery

**Symptoms:**
- `mcp-adapter-discover-abilities` returns empty or missing your abilities
- Other abilities (like SCF) appear but yours don't

**Root Cause:**
The MCP Adapter filters abilities using the `is_ability_mcp_public()` method which checks:
```php
return isset( $meta['mcp']['public'] ) && true === $meta['mcp']['public'];
```

**Solution:**
Ensure your meta includes the exact structure:
```php
'meta' => array(
    'mcp' => array( 'public' => true ),
),
```

**Location of check:** `mcp-adapter-0.4.1/includes/Traits/McpAbilityHelperTrait.php` lines 59-62

---

### Issue 2: `wp_register_ability()` Returns NULL

**Symptoms:**
- Registration callback runs but abilities aren't registered
- No error messages in logs
- `_doing_it_wrong` notices may appear

**Possible Causes & Solutions:**

#### Cause A: Missing Category

The Abilities API requires every ability to have a valid category:

```php
// This will FAIL (returns NULL)
wp_register_ability( 'my/ability', array(
    'label' => 'My Ability',
    'description' => 'Does something',
    // Missing 'category' key!
    ...
) );
```

**Solution:** Always include a category and register it first.

#### Cause B: Category Not Registered

If you reference a category that doesn't exist:

```php
// This will FAIL
'category' => 'nonexistent-category',
```

**Solution:** Register category on `wp_abilities_api_categories_init` (fires before `wp_abilities_api_init`).

#### Cause C: Not Registered During Action

```php
// This will FAIL - called too early
function my_plugin_init() {
    wp_register_ability(...); // Wrong! Not during the action
}
add_action( 'init', 'my_plugin_init' );
```

**Solution:** Only register during `wp_abilities_api_init`:
```php
add_action( 'wp_abilities_api_init', 'my_register_abilities' );
```

#### Cause D: Invalid Ability Name

Names must match pattern: `/^[a-z0-9-]+\/[a-z0-9-]+$/`

```php
// INVALID names:
'MyPlugin/GetData'     // Uppercase not allowed
'my_plugin/get_data'   // Underscores not allowed
'my-ability'           // Missing namespace prefix

// VALID names:
'my-plugin/get-data'
'elementor/list-pages'
'scf/create-post-type'
```

---

### Issue 3: File Encoding Corrupts Plugin

**Symptoms:**
- PHP Fatal Error: "Namespace declaration has to be the very first statement"
- File appears correct but PHP can't parse it
- BOM (Byte Order Mark) at file start

**Root Cause:**
PowerShell's default encoding or redirects can add BOM characters.

**Solution:**
When creating files via PowerShell, explicitly set encoding:

```powershell
# BAD - may add BOM
$content | Out-File -FilePath "file.php"

# GOOD - ASCII encoding, no BOM
$content | Out-File -FilePath "file.php" -Encoding ASCII

# ALTERNATIVE - UTF8 without BOM
[System.IO.File]::WriteAllText("file.php", $content)
```

**Detection:**
```powershell
# Check for BOM
$bytes = [System.IO.File]::ReadAllBytes("file.php")
$bytes[0..2]  # BOM is: 239, 187, 191 (EF BB BF)
```

---

### Issue 4: Input Schema Validation Errors

**Symptoms:**
- "Ability has invalid input. Reason: input is not of type object"
- Ability executes for some inputs but not others

**Root Cause:**
Empty PHP arrays serialize as `[]` instead of `{}` in JSON.

```php
// This becomes "properties": [] in JSON (invalid)
'input_schema' => array(
    'type' => 'object',
    'properties' => array(),  // Empty array
),
```

**Solution:**
Use `(object)` cast for empty objects:

```php
'input_schema' => array(
    'type' => 'object',
    'properties' => (object) array(),  // Becomes {} in JSON
),
```

Or use `stdClass`:
```php
'properties' => new stdClass(),
```

---

### Issue 5: Permission Denied Errors

**Symptoms:**
- "Permission denied for MCP API access. User ID 0"
- Works in browser but not via MCP

**Root Cause:**
MCP requests use Application Password authentication. If credentials are wrong or user lacks capability, User ID is 0.

**Solution:**
1. Verify Application Password is correct in MCP config
2. Check user has required capabilities
3. Use appropriate capability checks:

```php
// Common capabilities:
'permission_callback' => function() {
    return current_user_can( 'read' );           // Any logged-in user
    return current_user_can( 'edit_posts' );     // Authors+
    return current_user_can( 'manage_options' ); // Admins only
},
```

---

### Issue 6: Callbacks Not Callable

**Symptoms:**
- "The ability properties must contain a valid `execute_callback` function"
- Works with closures but not method references

**Root Cause:**
Method references may not be callable if class isn't instantiated.

**Solution:**
Use proper callable syntax:

```php
// Class method (instance)
'execute_callback' => array( $this, 'my_method' ),

// Class method (static)
'execute_callback' => array( 'MyClass', 'my_static_method' ),
'execute_callback' => 'MyClass::my_static_method',

// Closure (always works)
'execute_callback' => function( $input ) {
    return $this->do_something( $input );
},

// Function name
'execute_callback' => 'my_global_function',
```

---

## Debugging Techniques

### 1. Add Logging to Registration

```php
public function register_abilities() {
    error_log( 'MyPlugin: register_abilities() called' );
    error_log( 'MyPlugin: wp_register_ability exists: ' . 
        ( function_exists( 'wp_register_ability' ) ? 'yes' : 'no' ) );
    
    $result = wp_register_ability( 'my/ability', array(...) );
    
    error_log( 'MyPlugin: registration result: ' . 
        ( $result instanceof WP_Ability ? 'success' : 
            ( is_wp_error( $result ) ? $result->get_error_message() : 
                gettype( $result ) ) ) );
}
```

### 2. Check PHP Error Log Location

```powershell
# Local by Flywheel
Get-Content "C:\Users\[USER]\Local Sites\[SITE]\logs\php\error.log" -Tail 30

# Standard WordPress (check wp-config.php for WP_DEBUG_LOG path)
Get-Content "C:\path\to\wordpress\wp-content\debug.log" -Tail 30
```

### 3. Verify Ability Registration

```php
// Add temporary endpoint to check registry
add_action( 'rest_api_init', function() {
    register_rest_route( 'debug/v1', '/abilities', array(
        'methods' => 'GET',
        'callback' => function() {
            $registry = WP_Abilities_Registry::get_instance();
            return $registry->get_all_registered();
        },
        'permission_callback' => '__return_true',
    ) );
} );
```

### 4. Check MCP Adapter Filtering

The MCP Adapter's `DiscoverAbilitiesAbility` filters abilities:

```php
// In mcp-adapter/includes/Abilities/DiscoverAbilitiesAbility.php
$abilities = array_filter(
    wp_get_all_abilities(),
    fn( $ability ) => $this->is_ability_mcp_public( $ability )
);
```

### 5. PHP Syntax Check

```powershell
php -l "path\to\your-plugin.php"
```

---

## Best Practices

### 1. Naming Conventions

```php
// Ability names: namespace/action-object
'elementor/get-page-data'
'elementor/list-templates'
'my-plugin/create-item'

// Category slugs: lowercase with hyphens
'elementor'
'my-plugin'
'scf-post-types'
```

### 2. Comprehensive Descriptions

AI agents rely on descriptions to understand when to use abilities:

```php
// BAD - too vague
'description' => 'Gets page data',

// GOOD - specific and actionable
'description' => 'Retrieves the Elementor JSON data structure for a specific page or post. Returns the complete widget tree including all settings, styles, and nested elements. Use this to analyze or modify page layouts programmatically.',
```

### 3. Input Validation

```php
'execute_callback' => function( $input ) {
    // Validate and sanitize
    $post_id = isset( $input['post_id'] ) ? absint( $input['post_id'] ) : 0;
    
    if ( ! $post_id ) {
        return new WP_Error( 
            'invalid_input', 
            'post_id is required and must be a positive integer.',
            array( 'status' => 400 )
        );
    }
    
    $post = get_post( $post_id );
    if ( ! $post ) {
        return new WP_Error( 
            'not_found', 
            'Post not found.',
            array( 'status' => 404 )
        );
    }
    
    // Process...
},
```

### 4. Consistent Error Handling

```php
// Return WP_Error for failures
return new WP_Error( 
    'error_code',           // Machine-readable code
    'Human-readable message',
    array( 'status' => 400 ) // HTTP status code
);

// Common status codes:
// 400 - Bad Request (invalid input)
// 401 - Unauthorized (not logged in)
// 403 - Forbidden (lacks permission)
// 404 - Not Found (resource doesn't exist)
// 500 - Internal Server Error
```

### 5. Semantic Annotations (Optional but Recommended)

```php
'meta' => array(
    'show_in_rest' => true,
    'mcp' => array( 'public' => true ),
    'annotations' => array(
        'readonly'    => true,   // Doesn't modify data
        'destructive' => false,  // Doesn't delete data
        'idempotent'  => true,   // Same result if called multiple times
    ),
),
```

---

## Reference Examples

### Example 1: Read-Only List Ability

```php
wp_register_ability( 'my-plugin/list-items', array(
    'label'       => 'List Items',
    'description' => 'Retrieves a list of all items with optional filtering.',
    'category'    => 'my-plugin',
    'input_schema' => array(
        'type' => 'object',
        'properties' => array(
            'per_page' => array(
                'type' => 'integer',
                'description' => 'Number of items per page. Default: 20',
                'default' => 20,
            ),
            'status' => array(
                'type' => 'string',
                'description' => 'Filter by status: publish, draft, or all.',
                'enum' => array( 'publish', 'draft', 'all' ),
            ),
        ),
    ),
    'output_schema' => array(
        'type' => 'array',
        'items' => array(
            'type' => 'object',
            'properties' => array(
                'id' => array( 'type' => 'integer' ),
                'title' => array( 'type' => 'string' ),
                'status' => array( 'type' => 'string' ),
            ),
        ),
    ),
    'execute_callback' => function( $input ) {
        $per_page = isset( $input['per_page'] ) ? absint( $input['per_page'] ) : 20;
        $status = isset( $input['status'] ) ? $input['status'] : 'all';
        
        $args = array(
            'post_type' => 'my_item',
            'posts_per_page' => min( $per_page, 100 ),
        );
        
        if ( 'all' !== $status ) {
            $args['post_status'] = $status;
        }
        
        $query = new WP_Query( $args );
        $items = array();
        
        foreach ( $query->posts as $post ) {
            $items[] = array(
                'id' => $post->ID,
                'title' => $post->post_title,
                'status' => $post->post_status,
            );
        }
        
        return $items;
    },
    'permission_callback' => function() {
        return current_user_can( 'read' );
    },
    'meta' => array(
        'show_in_rest' => true,
        'mcp' => array( 'public' => true ),
        'annotations' => array(
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ),
    ),
) );
```

### Example 2: Write/Update Ability

```php
wp_register_ability( 'my-plugin/update-item', array(
    'label'       => 'Update Item',
    'description' => 'Updates an existing item with new data.',
    'category'    => 'my-plugin',
    'input_schema' => array(
        'type' => 'object',
        'properties' => array(
            'id' => array(
                'type' => 'integer',
                'description' => 'The ID of the item to update.',
            ),
            'title' => array(
                'type' => 'string',
                'description' => 'New title for the item.',
            ),
            'content' => array(
                'type' => 'string',
                'description' => 'New content for the item.',
            ),
        ),
        'required' => array( 'id' ),
    ),
    'output_schema' => array(
        'type' => 'object',
        'properties' => array(
            'success' => array( 'type' => 'boolean' ),
            'id' => array( 'type' => 'integer' ),
            'message' => array( 'type' => 'string' ),
        ),
    ),
    'execute_callback' => function( $input ) {
        $id = absint( $input['id'] );
        
        $post = get_post( $id );
        if ( ! $post || 'my_item' !== $post->post_type ) {
            return new WP_Error( 'not_found', 'Item not found.', array( 'status' => 404 ) );
        }
        
        $update_args = array( 'ID' => $id );
        
        if ( isset( $input['title'] ) ) {
            $update_args['post_title'] = sanitize_text_field( $input['title'] );
        }
        
        if ( isset( $input['content'] ) ) {
            $update_args['post_content'] = wp_kses_post( $input['content'] );
        }
        
        $result = wp_update_post( $update_args, true );
        
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        
        return array(
            'success' => true,
            'id' => $id,
            'message' => 'Item updated successfully.',
        );
    },
    'permission_callback' => function() {
        return current_user_can( 'edit_posts' );
    },
    'meta' => array(
        'show_in_rest' => true,
        'mcp' => array( 'public' => true ),
        'annotations' => array(
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ),
    ),
) );
```

### Example 3: Destructive Delete Ability

```php
wp_register_ability( 'my-plugin/delete-item', array(
    'label'       => 'Delete Item',
    'description' => 'Permanently deletes an item. This action cannot be undone.',
    'category'    => 'my-plugin',
    'input_schema' => array(
        'type' => 'object',
        'properties' => array(
            'id' => array(
                'type' => 'integer',
                'description' => 'The ID of the item to delete.',
            ),
            'force' => array(
                'type' => 'boolean',
                'description' => 'Skip trash and permanently delete. Default: false',
                'default' => false,
            ),
        ),
        'required' => array( 'id' ),
    ),
    'output_schema' => array(
        'type' => 'object',
        'properties' => array(
            'success' => array( 'type' => 'boolean' ),
            'deleted_id' => array( 'type' => 'integer' ),
        ),
    ),
    'execute_callback' => function( $input ) {
        $id = absint( $input['id'] );
        $force = isset( $input['force'] ) ? (bool) $input['force'] : false;
        
        $post = get_post( $id );
        if ( ! $post || 'my_item' !== $post->post_type ) {
            return new WP_Error( 'not_found', 'Item not found.', array( 'status' => 404 ) );
        }
        
        $result = wp_delete_post( $id, $force );
        
        if ( ! $result ) {
            return new WP_Error( 'delete_failed', 'Failed to delete item.', array( 'status' => 500 ) );
        }
        
        return array(
            'success' => true,
            'deleted_id' => $id,
        );
    },
    'permission_callback' => function() {
        return current_user_can( 'delete_posts' );
    },
    'meta' => array(
        'show_in_rest' => true,
        'mcp' => array( 'public' => true ),
        'annotations' => array(
            'readonly' => false,
            'destructive' => true,  // Important: marks as destructive
            'idempotent' => false,
        ),
    ),
) );
```

---

## Quick Reference Card

### Minimum Required Properties

```php
wp_register_ability( 'namespace/ability-name', array(
    'label'               => 'Required: Human label',
    'description'         => 'Required: Detailed description',
    'category'            => 'required-category-slug',
    'execute_callback'    => function($input) { return array(); },
    'permission_callback' => function() { return true; },
    'meta'                => array( 'mcp' => array( 'public' => true ) ),
) );
```

### Common Hooks

```php
// Category registration (fires first)
add_action( 'wp_abilities_api_categories_init', 'my_register_category' );

// Ability registration (fires second)
add_action( 'wp_abilities_api_init', 'my_register_abilities' );
```

### File Locations

| Component | Path |
|-----------|------|
| Abilities API | `wp-content/plugins/abilities-api/` |
| MCP Adapter | `wp-content/plugins/mcp-adapter-0.4.1/` |
| MCP Public Check | `mcp-adapter/includes/Traits/McpAbilityHelperTrait.php` |
| Ability Class | `abilities-api/includes/abilities-api/class-wp-ability.php` |
| Registry Class | `abilities-api/includes/abilities-api/class-wp-abilities-registry.php` |
| PHP Error Log | `[site]/logs/php/error.log` (Local by Flywheel) |

---

## Version History

- **2024-12-27**: Initial documentation created based on Elementor Abilities plugin development experience.

## Contributing

When adding new abilities or discovering new issues, please update this document with:
1. Clear problem description
2. Root cause analysis
3. Solution with code examples
4. Prevention tips for future development
