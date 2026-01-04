<?php
/**
 * Plugin Name: Elementor Abilities
 * Description: Exposes comprehensive Elementor functionality as WordPress Abilities for AI agents via MCP. Includes document, widget, and control management tools.
 * Version: 2.0.0
 * Author: Lombda LLC
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Elementor_Abilities_Plugin {
    private static $instance = null;
    private static $mcp_meta = array(
        'show_in_rest' => true,
        'mcp'          => array( 'public' => true ),
    );
    private static $category = 'elementor';

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'wp_abilities_api_categories_init', array( $this, 'register_category' ) );
        add_action( 'wp_abilities_api_init', array( $this, 'register_abilities' ) );
    }

    public function register_category() {
        wp_register_ability_category(
            self::$category,
            array(
                'label'       => 'Elementor',
                'description' => 'Comprehensive abilities for managing Elementor documents, widgets, controls, and settings.',
            )
        );
    }

    public function register_abilities() {
        $this->register_document_abilities();
        $this->register_widget_abilities();
        $this->register_control_abilities();
        $this->register_template_abilities();
        $this->register_global_abilities();
        $this->register_taxonomy_abilities();
        $this->register_media_abilities();
        $this->register_content_abilities();
    }

    // =========================================================================
    // DOCUMENT MANAGEMENT ABILITIES
    // =========================================================================

    private function register_document_abilities() {
        // Create Elementor Page
        wp_register_ability( 'elementor/create-page', array(
            'label' => 'Create Elementor Page',
            'description' => 'Creates a new WordPress page/post with Elementor enabled. Returns post_id, edit_url, and preview_url.',
            'category' => self::$category,
            'input_schema' => array(
                'type' => 'object',
                'properties' => array(
                    'title' => array( 'type' => 'string', 'description' => 'The page/post title.' ),
                    'post_type' => array( 'type' => 'string', 'description' => 'Post type (page, post). Default: page.', 'default' => 'page' ),
                    'status' => array( 'type' => 'string', 'description' => 'Post status (draft, publish). Default: draft.', 'default' => 'draft' ),
                    'template' => array( 'type' => 'string', 'description' => 'Page template. Use "elementor_canvas" for blank canvas.' ),
                ),
                'required' => array( 'title' ),
            ),
            'output_schema' => array(
                'type' => 'object',
                'properties' => array(
                    'post_id' => array( 'type' => 'integer' ),
                    'edit_url' => array( 'type' => 'string' ),
                    'preview_url' => array( 'type' => 'string' ),
                ),
            ),
            'execute_callback' => array( $this, 'execute_create_page' ),
            'permission_callback' => function() { return current_user_can( 'edit_posts' ); },
            'meta' => self::$mcp_meta,
        ) );

        // Get Document (full document with elements, settings, metadata)
        wp_register_ability( 'elementor/get-document', array(
            'label' => 'Get Elementor Document',
            'description' => 'Retrieves complete Elementor document data including parsed elements, settings, metadata, edit_url, and preview_url.',
            'category' => self::$category,
            'input_schema' => array(
                'type' => 'object',
                'properties' => array(
                    'post_id' => array( 'type' => 'integer', 'description' => 'The ID of the page/post.' ),
                ),
                'required' => array( 'post_id' ),
            ),
            'output_schema' => array(
                'type' => 'object',
                'properties' => array(
                    'post_id' => array( 'type' => 'integer' ),
                    'post_title' => array( 'type' => 'string' ),
                    'elements' => array( 'type' => 'array' ),
                    'settings' => array( 'type' => 'object' ),
                    'edit_url' => array( 'type' => 'string' ),
                    'preview_url' => array( 'type' => 'string' ),
                ),
            ),
            'execute_callback' => array( $this, 'execute_get_document' ),
            'permission_callback' => function() { return current_user_can( 'edit_posts' ); },
            'meta' => self::$mcp_meta,
        ) );

        // Save Document (save elements and settings)
        wp_register_ability( 'elementor/save-document', array(
            'label' => 'Save Elementor Document',
            'description' => 'Saves elements and settings to an Elementor document. Clears cache automatically.',
            'category' => self::$category,
            'input_schema' => array(
                'type' => 'object',
                'properties' => array(
                    'post_id' => array( 'type' => 'integer', 'description' => 'The ID of the page/post.' ),
                    'elements' => array( 'type' => 'array', 'description' => 'Array of Elementor elements (sections, columns, widgets).' ),
                    'settings' => array( 'type' => 'object', 'description' => 'Optional document settings.' ),
                ),
                'required' => array( 'post_id', 'elements' ),
            ),
            'output_schema' => array(
                'type' => 'object',
                'properties' => array(
                    'success' => array( 'type' => 'boolean' ),
                    'post_id' => array( 'type' => 'integer' ),
                ),
            ),
            'execute_callback' => array( $this, 'execute_save_document' ),
            'permission_callback' => function() { return current_user_can( 'edit_posts' ); },
            'meta' => self::$mcp_meta,
        ) );

        // Get Raw Elementor Data (JSON string)
        wp_register_ability( 'elementor/get-elementor-data', array(
            'label' => 'Get Raw Elementor Data',
            'description' => 'Retrieves the raw _elementor_data meta value as JSON string.',
            'category' => self::$category,
            'input_schema' => array(
                'type' => 'object',
                'properties' => array(
                    'post_id' => array( 'type' => 'integer', 'description' => 'The ID of the page/post.' ),
                ),
                'required' => array( 'post_id' ),
            ),
            'output_schema' => array(
                'type' => 'object',
                'properties' => array(
                    'post_id' => array( 'type' => 'integer' ),
                    'post_title' => array( 'type' => 'string' ),
                    'elementor_data' => array( 'type' => 'string' ),
                ),
            ),
            'execute_callback' => function( $input ) {
                $post_id = absint( $input['post_id'] );
                $post = get_post( $post_id );
                if ( ! $post ) {
                    return new WP_Error( 'not_found', 'Post not found.', array( 'status' => 404 ) );
                }
                $elementor_data = get_post_meta( $post_id, '_elementor_data', true );
                return array(
                    'post_id' => $post_id,
                    'post_title' => $post->post_title,
                    'elementor_data' => $elementor_data ? $elementor_data : '[]',
                );
            },
            'permission_callback' => function() { return current_user_can( 'edit_posts' ); },
            'meta' => self::$mcp_meta,
        ) );

        // Update Raw Elementor Data
        wp_register_ability( 'elementor/update-elementor-data', array(
            'label' => 'Update Raw Elementor Data',
            'description' => 'Updates the raw _elementor_data meta value with a JSON string.',
            'category' => self::$category,
            'input_schema' => array(
                'type' => 'object',
                'properties' => array(
                    'post_id' => array( 'type' => 'integer', 'description' => 'The ID of the page/post.' ),
                    'elementor_data' => array( 'type' => 'string', 'description' => 'The Elementor data as JSON string.' ),
                ),
                'required' => array( 'post_id', 'elementor_data' ),
            ),
            'output_schema' => array(
                'type' => 'object',
                'properties' => array(
                    'success' => array( 'type' => 'boolean' ),
                    'post_id' => array( 'type' => 'integer' ),
                ),
            ),
            'execute_callback' => function( $input ) {
                $post_id = absint( $input['post_id'] );
                $post = get_post( $post_id );
                if ( ! $post ) {
                    return new WP_Error( 'not_found', 'Post not found.', array( 'status' => 404 ) );
                }
                update_post_meta( $post_id, '_elementor_data', wp_slash( $input['elementor_data'] ) );
                update_post_meta( $post_id, '_elementor_edit_mode', 'builder' );
                if ( class_exists( 'Elementor\Plugin' ) ) {
                    Elementor\Plugin::$instance->files_manager->clear_cache();
                }
                return array( 'success' => true, 'post_id' => $post_id );
            },
            'permission_callback' => function() { return current_user_can( 'edit_posts' ); },
            'meta' => self::$mcp_meta,
        ) );

        // List Elementor Pages
        wp_register_ability( 'elementor/list-pages', array(
            'label' => 'List Elementor Pages',
            'description' => 'Lists all pages/posts that are built with Elementor.',
            'category' => self::$category,
            'input_schema' => array(
                'type' => 'object',
                'properties' => array(
                    'per_page' => array( 'type' => 'integer', 'description' => 'Number of results. Default: 50.', 'default' => 50 ),
                    'post_type' => array( 'type' => 'string', 'description' => 'Filter by post type (page, post, any). Default: any.' ),
                ),
            ),
            'output_schema' => array( 'type' => 'array' ),
            'execute_callback' => function( $input ) {
                $post_types = array( 'page', 'post' );
                if ( isset( $input['post_type'] ) && in_array( $input['post_type'], array( 'page', 'post' ) ) ) {
                    $post_types = array( $input['post_type'] );
                }
                $query = new WP_Query( array(
                    'post_type' => $post_types,
                    'posts_per_page' => isset($input['per_page']) ? absint($input['per_page']) : 50,
                    'post_status' => array( 'publish', 'draft' ),
                    'meta_query' => array(
                        array( 'key' => '_elementor_edit_mode', 'value' => 'builder' ),
                    ),
                ) );
                $pages = array();
                foreach ( $query->posts as $post ) {
                    $pages[] = array(
                        'id' => $post->ID,
                        'title' => $post->post_title,
                        'type' => $post->post_type,
                        'status' => $post->post_status,
                        'permalink' => get_permalink( $post->ID ),
                        'edit_url' => admin_url( 'post.php?post=' . $post->ID . '&action=elementor' ),
                    );
                }
                return $pages;
            },
            'permission_callback' => function() { return current_user_can( 'edit_posts' ); },
            'meta' => self::$mcp_meta,
        ) );

        // Get Page Structure - tree view of elements with IDs
        wp_register_ability( 'elementor/get-page-structure', array(
            'label' => 'Get Page Structure',
            'description' => 'Get a hierarchical tree view of the page structure showing all elements with their IDs, types, and positions.',
            'category' => self::$category,
            'input_schema' => array(
                'type' => 'object',
                'properties' => array(
                    'post_id' => array( 'type' => 'integer', 'description' => 'The ID of the page/post.' ),
                    'include_settings' => array( 'type' => 'boolean', 'description' => 'Include element settings in output. Default: false.', 'default' => false ),
                ),
                'required' => array( 'post_id' ),
            ),
            'output_schema' => array( 'type' => 'object' ),
            'execute_callback' => array( $this, 'execute_get_page_structure' ),
            'permission_callback' => function() { return current_user_can( 'edit_posts' ); },
            'meta' => self::$mcp_meta,
        ) );

        // Find Widget by ID
        wp_register_ability( 'elementor/find-widget', array(
            'label' => 'Find Widget in Page',
            'description' => 'Find a specific widget or element by its ID in a page. Returns the element data and its path in the structure.',
            'category' => self::$category,
            'input_schema' => array(
                'type' => 'object',
                'properties' => array(
                    'post_id' => array( 'type' => 'integer', 'description' => 'The ID of the page/post.' ),
                    'element_id' => array( 'type' => 'string', 'description' => 'The element/widget ID to find.' ),
                ),
                'required' => array( 'post_id', 'element_id' ),
            ),
            'output_schema' => array( 'type' => 'object' ),
            'execute_callback' => array( $this, 'execute_find_widget' ),
            'permission_callback' => function() { return current_user_can( 'edit_posts' ); },
            'meta' => self::$mcp_meta,
        ) );

        // Update Widget
        wp_register_ability( 'elementor/update-widget', array(
            'label' => 'Update Widget Settings',
            'description' => 'Update a specific widget\'s settings by ID. Performs a partial merge, only updating specified settings.',
            'category' => self::$category,
            'input_schema' => array(
                'type' => 'object',
                'properties' => array(
                    'post_id' => array( 'type' => 'integer', 'description' => 'The ID of the page/post.' ),
                    'element_id' => array( 'type' => 'string', 'description' => 'The widget/element ID to update.' ),
                    'settings' => array( 'type' => 'object', 'description' => 'Settings to update (will be merged with existing).' ),
                ),
                'required' => array( 'post_id', 'element_id', 'settings' ),
            ),
            'output_schema' => array( 'type' => 'object' ),
            'execute_callback' => array( $this, 'execute_update_widget' ),
            'permission_callback' => function() { return current_user_can( 'edit_posts' ); },
            'meta' => self::$mcp_meta,
        ) );

        // Add Widget to Container
        wp_register_ability( 'elementor/add-widget', array(
            'label' => 'Add Widget to Page',
            'description' => 'Add a new widget to a specific container or section in a page. Specify position to control placement.',
            'category' => self::$category,
            'input_schema' => array(
                'type' => 'object',
                'properties' => array(
                    'post_id' => array( 'type' => 'integer', 'description' => 'The ID of the page/post.' ),
                    'container_id' => array( 'type' => 'string', 'description' => 'The ID of the container/section to add the widget to. If not specified, adds to root.' ),
                    'widget_type' => array( 'type' => 'string', 'description' => 'The widget type (e.g., "button", "heading", "image").' ),
                    'settings' => array( 'type' => 'object', 'description' => 'Widget settings.', 'default' => array() ),
                    'position' => array( 'type' => 'integer', 'description' => 'Position index within the container. Default: append at end.', 'default' => -1 ),
                ),
                'required' => array( 'post_id', 'widget_type' ),
            ),
            'output_schema' => array( 'type' => 'object' ),
            'execute_callback' => array( $this, 'execute_add_widget' ),
            'permission_callback' => function() { return current_user_can( 'edit_posts' ); },
            'meta' => self::$mcp_meta,
        ) );

        // Remove Widget
        wp_register_ability( 'elementor/remove-widget', array(
            'label' => 'Remove Widget from Page',
            'description' => 'Remove a widget or element by ID from a page.',
            'category' => self::$category,
            'input_schema' => array(
                'type' => 'object',
                'properties' => array(
                    'post_id' => array( 'type' => 'integer', 'description' => 'The ID of the page/post.' ),
                    'element_id' => array( 'type' => 'string', 'description' => 'The widget/element ID to remove.' ),
                ),
                'required' => array( 'post_id', 'element_id' ),
            ),
            'output_schema' => array( 'type' => 'object' ),
            'execute_callback' => array( $this, 'execute_remove_widget' ),
            'permission_callback' => function() { return current_user_can( 'edit_posts' ); },
            'meta' => self::$mcp_meta,
        ) );

        // Duplicate Widget
        wp_register_ability( 'elementor/duplicate-widget', array(
            'label' => 'Duplicate Widget',
            'description' => 'Create a copy of a widget in the same container, immediately after the original.',
            'category' => self::$category,
            'input_schema' => array(
                'type' => 'object',
                'properties' => array(
                    'post_id' => array( 'type' => 'integer', 'description' => 'The ID of the page/post.' ),
                    'element_id' => array( 'type' => 'string', 'description' => 'The widget/element ID to duplicate.' ),
                ),
                'required' => array( 'post_id', 'element_id' ),
            ),
            'output_schema' => array( 'type' => 'object' ),
            'execute_callback' => array( $this, 'execute_duplicate_widget' ),
            'permission_callback' => function() { return current_user_can( 'edit_posts' ); },
            'meta' => self::$mcp_meta,
        ) );

        // Move Widget
        wp_register_ability( 'elementor/move-widget', array(
            'label' => 'Move Widget',
            'description' => 'Move a widget to a different position or container within the page.',
            'category' => self::$category,
            'input_schema' => array(
                'type' => 'object',
                'properties' => array(
                    'post_id' => array( 'type' => 'integer', 'description' => 'The ID of the page/post.' ),
                    'element_id' => array( 'type' => 'string', 'description' => 'The widget/element ID to move.' ),
                    'target_container_id' => array( 'type' => 'string', 'description' => 'The target container ID. Use "root" for page root.' ),
                    'position' => array( 'type' => 'integer', 'description' => 'Position index in target container. Default: append at end.', 'default' => -1 ),
                ),
                'required' => array( 'post_id', 'element_id', 'target_container_id' ),
            ),
            'output_schema' => array( 'type' => 'object' ),
            'execute_callback' => array( $this, 'execute_move_widget' ),
            'permission_callback' => function() { return current_user_can( 'edit_posts' ); },
            'meta' => self::$mcp_meta,
        ) );

        // Add Section/Container to Page
        wp_register_ability( 'elementor/add-section', array(
            'label' => 'Add Section to Page',
            'description' => 'Add a new section or container to a page at the root level or inside another container.',
            'category' => self::$category,
            'input_schema' => array(
                'type' => 'object',
                'properties' => array(
                    'post_id' => array( 'type' => 'integer', 'description' => 'The ID of the page/post.' ),
                    'section_type' => array( 'type' => 'string', 'description' => 'Type: "container" (modern) or "section" (legacy). Default: container.', 'default' => 'container' ),
                    'layout' => array( 'type' => 'string', 'description' => 'For containers: "boxed" or "full_width". Default: boxed.', 'default' => 'boxed' ),
                    'settings' => array( 'type' => 'object', 'description' => 'Section/container settings.' ),
                    'widgets' => array( 'type' => 'array', 'description' => 'Array of widgets to add inside. Each: {widget_type, settings}.' ),
                    'parent_id' => array( 'type' => 'string', 'description' => 'Parent container ID for nesting. Omit for root level.' ),
                    'position' => array( 'type' => 'integer', 'description' => 'Position index. Default: append at end.', 'default' => -1 ),
                ),
                'required' => array( 'post_id' ),
            ),
            'output_schema' => array( 'type' => 'object' ),
            'execute_callback' => array( $this, 'execute_add_section' ),
            'permission_callback' => function() { return current_user_can( 'edit_posts' ); },
            'meta' => self::$mcp_meta,
        ) );

        // Bulk Update Widgets
        wp_register_ability( 'elementor/bulk-update-widgets', array(
            'label' => 'Bulk Update Widgets',
            'description' => 'Update multiple widgets at once. Useful for batch operations like updating all buttons or headings.',
            'category' => self::$category,
            'input_schema' => array(
                'type' => 'object',
                'properties' => array(
                    'post_id' => array( 'type' => 'integer', 'description' => 'The ID of the page/post.' ),
                    'updates' => array( 
                        'type' => 'array', 
                        'description' => 'Array of updates. Each: {element_id, settings}.',
                        'items' => array(
                            'type' => 'object',
                            'properties' => array(
                                'element_id' => array( 'type' => 'string' ),
                                'settings' => array( 'type' => 'object' ),
                            ),
                        ),
                    ),
                ),
                'required' => array( 'post_id', 'updates' ),
            ),
            'output_schema' => array( 'type' => 'object' ),
            'execute_callback' => array( $this, 'execute_bulk_update_widgets' ),
            'permission_callback' => function() { return current_user_can( 'edit_posts' ); },
            'meta' => self::$mcp_meta,
        ) );

        // Find Widgets by Type
        wp_register_ability( 'elementor/find-widgets-by-type', array(
            'label' => 'Find Widgets by Type',
            'description' => 'Find all widgets of a specific type in a page. Useful for bulk updates.',
            'category' => self::$category,
            'input_schema' => array(
                'type' => 'object',
                'properties' => array(
                    'post_id' => array( 'type' => 'integer', 'description' => 'The ID of the page/post.' ),
                    'widget_type' => array( 'type' => 'string', 'description' => 'The widget type to find (e.g., "button", "heading", "image").' ),
                ),
                'required' => array( 'post_id', 'widget_type' ),
            ),
            'output_schema' => array( 'type' => 'object' ),
            'execute_callback' => array( $this, 'execute_find_widgets_by_type' ),
            'permission_callback' => function() { return current_user_can( 'edit_posts' ); },
            'meta' => self::$mcp_meta,
        ) );
    }

    // =========================================================================
    // PAGE EDITING HELPER METHODS
    // =========================================================================

    /**
     * Recursively find an element by ID in the elements array
     */
    private function find_element_by_id( &$elements, $element_id, $path = array() ) {
        foreach ( $elements as $index => &$element ) {
            $current_path = array_merge( $path, array( $index ) );
            
            if ( isset( $element['id'] ) && $element['id'] === $element_id ) {
                return array(
                    'element' => &$element,
                    'path' => $current_path,
                    'index' => $index,
                );
            }
            
            if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
                $found = $this->find_element_by_id( $element['elements'], $element_id, $current_path );
                if ( $found ) {
                    return $found;
                }
            }
        }
        return null;
    }

    /**
     * Recursively find parent container of an element
     */
    private function find_parent_container( &$elements, $element_id, &$parent = null ) {
        foreach ( $elements as $index => &$element ) {
            if ( isset( $element['id'] ) && $element['id'] === $element_id ) {
                return array(
                    'parent' => &$parent,
                    'index' => $index,
                );
            }
            
            if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
                $found = $this->find_parent_container( $element['elements'], $element_id, $element );
                if ( $found ) {
                    return $found;
                }
            }
        }
        return null;
    }

    /**
     * Generate unique element ID
     */
    private function generate_element_id() {
        return dechex( mt_rand( 0x10000000, 0x7FFFFFFF ) );
    }

    /**
     * Deep clone an element with new IDs
     */
    private function clone_element( $element ) {
        $cloned = $element;
        $cloned['id'] = $this->generate_element_id();
        
        if ( ! empty( $cloned['elements'] ) && is_array( $cloned['elements'] ) ) {
            $cloned['elements'] = array_map( array( $this, 'clone_element' ), $cloned['elements'] );
        }
        
        return $cloned;
    }

    /**
     * Build structure tree from elements
     */
    private function build_structure_tree( $elements, $include_settings = false, $depth = 0 ) {
        $tree = array();
        foreach ( $elements as $index => $element ) {
            $node = array(
                'id' => $element['id'] ?? '',
                'elType' => $element['elType'] ?? '',
                'widgetType' => $element['widgetType'] ?? null,
                'depth' => $depth,
                'index' => $index,
            );
            
            if ( $include_settings && isset( $element['settings'] ) ) {
                $node['settings'] = $element['settings'];
            }
            
            if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
                $node['children'] = $this->build_structure_tree( $element['elements'], $include_settings, $depth + 1 );
                $node['childCount'] = count( $element['elements'] );
            } else {
                $node['childCount'] = 0;
            }
            
            $tree[] = $node;
        }
        return $tree;
    }

    /**
     * Find all widgets of a specific type
     */
    private function find_all_widgets_by_type( $elements, $widget_type, $path = array() ) {
        $found = array();
        foreach ( $elements as $index => $element ) {
            $current_path = array_merge( $path, array( $index ) );
            
            if ( isset( $element['widgetType'] ) && $element['widgetType'] === $widget_type ) {
                $found[] = array(
                    'id' => $element['id'],
                    'widgetType' => $element['widgetType'],
                    'settings' => $element['settings'] ?? array(),
                    'path' => $current_path,
                );
            }
            
            if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
                $found = array_merge( $found, $this->find_all_widgets_by_type( $element['elements'], $widget_type, $current_path ) );
            }
        }
        return $found;
    }

    /**
     * Save elements and clear cache
     */
    private function save_elements( $post_id, $elements ) {
        $json_data = wp_json_encode( $elements );
        update_post_meta( $post_id, '_elementor_data', wp_slash( $json_data ) );
        
        if ( class_exists( 'Elementor\Plugin' ) ) {
            Elementor\Plugin::$instance->files_manager->clear_cache();
        }
        
        return true;
    }

    // =========================================================================
    // PAGE EDITING EXECUTION METHODS
    // =========================================================================

    public function execute_get_page_structure( $input ) {
        $post_id = absint( $input['post_id'] );
        $include_settings = isset( $input['include_settings'] ) ? (bool) $input['include_settings'] : false;
        
        $post = get_post( $post_id );
        if ( ! $post ) {
            return new WP_Error( 'not_found', 'Post not found.', array( 'status' => 404 ) );
        }
        
        $elementor_data = get_post_meta( $post_id, '_elementor_data', true );
        $elements = $elementor_data ? json_decode( $elementor_data, true ) : array();
        
        if ( ! is_array( $elements ) ) {
            $elements = array();
        }
        
        $structure = $this->build_structure_tree( $elements, $include_settings );
        
        return array(
            'post_id' => $post_id,
            'post_title' => $post->post_title,
            'structure' => $structure,
            'element_count' => $this->count_elements( $elements ),
        );
    }

    private function count_elements( $elements ) {
        $count = count( $elements );
        foreach ( $elements as $element ) {
            if ( ! empty( $element['elements'] ) ) {
                $count += $this->count_elements( $element['elements'] );
            }
        }
        return $count;
    }

    public function execute_find_widget( $input ) {
        $post_id = absint( $input['post_id'] );
        $element_id = sanitize_text_field( $input['element_id'] );
        
        $post = get_post( $post_id );
        if ( ! $post ) {
            return new WP_Error( 'not_found', 'Post not found.', array( 'status' => 404 ) );
        }
        
        $elementor_data = get_post_meta( $post_id, '_elementor_data', true );
        $elements = $elementor_data ? json_decode( $elementor_data, true ) : array();
        
        if ( ! is_array( $elements ) ) {
            return new WP_Error( 'no_elements', 'No Elementor elements found.' );
        }
        
        $found = $this->find_element_by_id( $elements, $element_id );
        
        if ( ! $found ) {
            return new WP_Error( 'element_not_found', "Element with ID '$element_id' not found." );
        }
        
        return array(
            'found' => true,
            'element' => $found['element'],
            'path' => $found['path'],
            'elType' => $found['element']['elType'] ?? '',
            'widgetType' => $found['element']['widgetType'] ?? null,
        );
    }

    public function execute_update_widget( $input ) {
        $post_id = absint( $input['post_id'] );
        $element_id = sanitize_text_field( $input['element_id'] );
        $new_settings = $input['settings'];
        
        if ( ! is_array( $new_settings ) ) {
            return new WP_Error( 'invalid_settings', 'Settings must be an object.' );
        }
        
        $post = get_post( $post_id );
        if ( ! $post ) {
            return new WP_Error( 'not_found', 'Post not found.', array( 'status' => 404 ) );
        }
        
        $elementor_data = get_post_meta( $post_id, '_elementor_data', true );
        $elements = $elementor_data ? json_decode( $elementor_data, true ) : array();
        
        if ( ! is_array( $elements ) ) {
            return new WP_Error( 'no_elements', 'No Elementor elements found.' );
        }
        
        $found = $this->find_element_by_id( $elements, $element_id );
        
        if ( ! $found ) {
            return new WP_Error( 'element_not_found', "Element with ID '$element_id' not found." );
        }
        
        // Merge settings
        $current_settings = $found['element']['settings'] ?? array();
        $merged_settings = array_merge( $current_settings, $new_settings );
        
        // Update the element in place
        $found['element']['settings'] = $merged_settings;
        
        // Save
        $this->save_elements( $post_id, $elements );
        
        return array(
            'success' => true,
            'element_id' => $element_id,
            'updated_settings' => array_keys( $new_settings ),
            'message' => 'Widget updated successfully.',
        );
    }

    public function execute_add_widget( $input ) {
        $post_id = absint( $input['post_id'] );
        $container_id = isset( $input['container_id'] ) ? sanitize_text_field( $input['container_id'] ) : null;
        $widget_type = sanitize_text_field( $input['widget_type'] );
        $settings = isset( $input['settings'] ) && is_array( $input['settings'] ) ? $input['settings'] : array();
        $position = isset( $input['position'] ) ? intval( $input['position'] ) : -1;
        
        $post = get_post( $post_id );
        if ( ! $post ) {
            return new WP_Error( 'not_found', 'Post not found.', array( 'status' => 404 ) );
        }
        
        // Verify widget type exists
        if ( class_exists( 'Elementor\Plugin' ) ) {
            $widget = Elementor\Plugin::$instance->widgets_manager->get_widget_types( $widget_type );
            if ( ! $widget ) {
                return new WP_Error( 'widget_not_found', "Widget type '$widget_type' not found." );
            }
        }
        
        $elementor_data = get_post_meta( $post_id, '_elementor_data', true );
        $elements = $elementor_data ? json_decode( $elementor_data, true ) : array();
        
        if ( ! is_array( $elements ) ) {
            $elements = array();
        }
        
        // Create the widget
        $new_widget = array(
            'id' => $this->generate_element_id(),
            'elType' => 'widget',
            'widgetType' => $widget_type,
            'settings' => $settings,
        );
        
        if ( $container_id ) {
            // Find container and add widget to it
            $found = $this->find_element_by_id( $elements, $container_id );
            if ( ! $found ) {
                return new WP_Error( 'container_not_found', "Container with ID '$container_id' not found." );
            }
            
            if ( ! isset( $found['element']['elements'] ) ) {
                $found['element']['elements'] = array();
            }
            
            if ( $position >= 0 && $position < count( $found['element']['elements'] ) ) {
                array_splice( $found['element']['elements'], $position, 0, array( $new_widget ) );
            } else {
                $found['element']['elements'][] = $new_widget;
            }
        } else {
            // Add to root - need to wrap in a container
            $container = array(
                'id' => $this->generate_element_id(),
                'elType' => 'container',
                'settings' => array( 'content_width' => 'boxed' ),
                'elements' => array( $new_widget ),
            );
            
            if ( $position >= 0 && $position < count( $elements ) ) {
                array_splice( $elements, $position, 0, array( $container ) );
            } else {
                $elements[] = $container;
            }
        }
        
        $this->save_elements( $post_id, $elements );
        
        return array(
            'success' => true,
            'widget_id' => $new_widget['id'],
            'widget_type' => $widget_type,
            'container_id' => $container_id ?? 'new_container',
            'message' => 'Widget added successfully.',
        );
    }

    public function execute_remove_widget( $input ) {
        $post_id = absint( $input['post_id'] );
        $element_id = sanitize_text_field( $input['element_id'] );
        
        $post = get_post( $post_id );
        if ( ! $post ) {
            return new WP_Error( 'not_found', 'Post not found.', array( 'status' => 404 ) );
        }
        
        $elementor_data = get_post_meta( $post_id, '_elementor_data', true );
        $elements = $elementor_data ? json_decode( $elementor_data, true ) : array();
        
        if ( ! is_array( $elements ) ) {
            return new WP_Error( 'no_elements', 'No Elementor elements found.' );
        }
        
        // Find and remove the element
        $removed = $this->remove_element_by_id( $elements, $element_id );
        
        if ( ! $removed ) {
            return new WP_Error( 'element_not_found', "Element with ID '$element_id' not found." );
        }
        
        $this->save_elements( $post_id, $elements );
        
        return array(
            'success' => true,
            'removed_id' => $element_id,
            'message' => 'Element removed successfully.',
        );
    }

    private function remove_element_by_id( &$elements, $element_id ) {
        foreach ( $elements as $index => &$element ) {
            if ( isset( $element['id'] ) && $element['id'] === $element_id ) {
                array_splice( $elements, $index, 1 );
                return true;
            }
            
            if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
                if ( $this->remove_element_by_id( $element['elements'], $element_id ) ) {
                    return true;
                }
            }
        }
        return false;
    }

    public function execute_duplicate_widget( $input ) {
        $post_id = absint( $input['post_id'] );
        $element_id = sanitize_text_field( $input['element_id'] );
        
        $post = get_post( $post_id );
        if ( ! $post ) {
            return new WP_Error( 'not_found', 'Post not found.', array( 'status' => 404 ) );
        }
        
        $elementor_data = get_post_meta( $post_id, '_elementor_data', true );
        $elements = $elementor_data ? json_decode( $elementor_data, true ) : array();
        
        if ( ! is_array( $elements ) ) {
            return new WP_Error( 'no_elements', 'No Elementor elements found.' );
        }
        
        // Find element and its parent
        $result = $this->duplicate_element_by_id( $elements, $element_id );
        
        if ( ! $result ) {
            return new WP_Error( 'element_not_found', "Element with ID '$element_id' not found." );
        }
        
        $this->save_elements( $post_id, $elements );
        
        return array(
            'success' => true,
            'original_id' => $element_id,
            'new_id' => $result['new_id'],
            'message' => 'Element duplicated successfully.',
        );
    }

    private function duplicate_element_by_id( &$elements, $element_id ) {
        foreach ( $elements as $index => &$element ) {
            if ( isset( $element['id'] ) && $element['id'] === $element_id ) {
                $cloned = $this->clone_element( $element );
                array_splice( $elements, $index + 1, 0, array( $cloned ) );
                return array( 'new_id' => $cloned['id'] );
            }
            
            if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
                $result = $this->duplicate_element_by_id( $element['elements'], $element_id );
                if ( $result ) {
                    return $result;
                }
            }
        }
        return null;
    }

    public function execute_move_widget( $input ) {
        $post_id = absint( $input['post_id'] );
        $element_id = sanitize_text_field( $input['element_id'] );
        $target_container_id = sanitize_text_field( $input['target_container_id'] );
        $position = isset( $input['position'] ) ? intval( $input['position'] ) : -1;
        
        $post = get_post( $post_id );
        if ( ! $post ) {
            return new WP_Error( 'not_found', 'Post not found.', array( 'status' => 404 ) );
        }
        
        $elementor_data = get_post_meta( $post_id, '_elementor_data', true );
        $elements = $elementor_data ? json_decode( $elementor_data, true ) : array();
        
        if ( ! is_array( $elements ) ) {
            return new WP_Error( 'no_elements', 'No Elementor elements found.' );
        }
        
        // Find and extract the element
        $found = $this->find_element_by_id( $elements, $element_id );
        if ( ! $found ) {
            return new WP_Error( 'element_not_found', "Element with ID '$element_id' not found." );
        }
        
        $element_to_move = $found['element'];
        
        // Remove from current position
        $this->remove_element_by_id( $elements, $element_id );
        
        // Add to target
        if ( $target_container_id === 'root' ) {
            if ( $position >= 0 && $position < count( $elements ) ) {
                array_splice( $elements, $position, 0, array( $element_to_move ) );
            } else {
                $elements[] = $element_to_move;
            }
        } else {
            $target = $this->find_element_by_id( $elements, $target_container_id );
            if ( ! $target ) {
                return new WP_Error( 'target_not_found', "Target container with ID '$target_container_id' not found." );
            }
            
            if ( ! isset( $target['element']['elements'] ) ) {
                $target['element']['elements'] = array();
            }
            
            if ( $position >= 0 && $position < count( $target['element']['elements'] ) ) {
                array_splice( $target['element']['elements'], $position, 0, array( $element_to_move ) );
            } else {
                $target['element']['elements'][] = $element_to_move;
            }
        }
        
        $this->save_elements( $post_id, $elements );
        
        return array(
            'success' => true,
            'element_id' => $element_id,
            'target_container' => $target_container_id,
            'position' => $position,
            'message' => 'Element moved successfully.',
        );
    }

    public function execute_add_section( $input ) {
        $post_id = absint( $input['post_id'] );
        $section_type = isset( $input['section_type'] ) ? sanitize_text_field( $input['section_type'] ) : 'container';
        $layout = isset( $input['layout'] ) ? sanitize_text_field( $input['layout'] ) : 'boxed';
        $settings = isset( $input['settings'] ) && is_array( $input['settings'] ) ? $input['settings'] : array();
        $widgets = isset( $input['widgets'] ) && is_array( $input['widgets'] ) ? $input['widgets'] : array();
        $parent_id = isset( $input['parent_id'] ) ? sanitize_text_field( $input['parent_id'] ) : null;
        $position = isset( $input['position'] ) ? intval( $input['position'] ) : -1;
        
        $post = get_post( $post_id );
        if ( ! $post ) {
            return new WP_Error( 'not_found', 'Post not found.', array( 'status' => 404 ) );
        }
        
        $elementor_data = get_post_meta( $post_id, '_elementor_data', true );
        $elements = $elementor_data ? json_decode( $elementor_data, true ) : array();
        
        if ( ! is_array( $elements ) ) {
            $elements = array();
        }
        
        // Build child elements from widgets
        $child_elements = array();
        foreach ( $widgets as $widget_data ) {
            if ( isset( $widget_data['widget_type'] ) ) {
                $child_elements[] = array(
                    'id' => $this->generate_element_id(),
                    'elType' => 'widget',
                    'widgetType' => sanitize_text_field( $widget_data['widget_type'] ),
                    'settings' => isset( $widget_data['settings'] ) && is_array( $widget_data['settings'] ) ? $widget_data['settings'] : array(),
                );
            }
        }
        
        // Create the section/container
        $new_section = array(
            'id' => $this->generate_element_id(),
            'elType' => $section_type === 'section' ? 'section' : 'container',
            'settings' => array_merge( array( 'content_width' => $layout ), $settings ),
            'elements' => $child_elements,
        );
        
        // Legacy section needs columns
        if ( $section_type === 'section' ) {
            $new_section['elements'] = array(
                array(
                    'id' => $this->generate_element_id(),
                    'elType' => 'column',
                    'settings' => array( '_column_size' => 100 ),
                    'elements' => $child_elements,
                ),
            );
        }
        
        // Add to parent or root
        if ( $parent_id ) {
            $parent = $this->find_element_by_id( $elements, $parent_id );
            if ( ! $parent ) {
                return new WP_Error( 'parent_not_found', "Parent container with ID '$parent_id' not found." );
            }
            
            if ( ! isset( $parent['element']['elements'] ) ) {
                $parent['element']['elements'] = array();
            }
            
            if ( $position >= 0 && $position < count( $parent['element']['elements'] ) ) {
                array_splice( $parent['element']['elements'], $position, 0, array( $new_section ) );
            } else {
                $parent['element']['elements'][] = $new_section;
            }
        } else {
            if ( $position >= 0 && $position < count( $elements ) ) {
                array_splice( $elements, $position, 0, array( $new_section ) );
            } else {
                $elements[] = $new_section;
            }
        }
        
        $this->save_elements( $post_id, $elements );
        
        return array(
            'success' => true,
            'section_id' => $new_section['id'],
            'section_type' => $new_section['elType'],
            'widget_count' => count( $child_elements ),
            'message' => 'Section added successfully.',
        );
    }

    public function execute_bulk_update_widgets( $input ) {
        $post_id = absint( $input['post_id'] );
        $updates = $input['updates'];
        
        if ( ! is_array( $updates ) ) {
            return new WP_Error( 'invalid_updates', 'Updates must be an array.' );
        }
        
        $post = get_post( $post_id );
        if ( ! $post ) {
            return new WP_Error( 'not_found', 'Post not found.', array( 'status' => 404 ) );
        }
        
        $elementor_data = get_post_meta( $post_id, '_elementor_data', true );
        $elements = $elementor_data ? json_decode( $elementor_data, true ) : array();
        
        if ( ! is_array( $elements ) ) {
            return new WP_Error( 'no_elements', 'No Elementor elements found.' );
        }
        
        $results = array(
            'updated' => array(),
            'failed' => array(),
        );
        
        foreach ( $updates as $update ) {
            if ( ! isset( $update['element_id'] ) || ! isset( $update['settings'] ) ) {
                continue;
            }
            
            $element_id = sanitize_text_field( $update['element_id'] );
            $new_settings = $update['settings'];
            
            $found = $this->find_element_by_id( $elements, $element_id );
            
            if ( $found ) {
                $current_settings = $found['element']['settings'] ?? array();
                $found['element']['settings'] = array_merge( $current_settings, $new_settings );
                $results['updated'][] = $element_id;
            } else {
                $results['failed'][] = $element_id;
            }
        }
        
        $this->save_elements( $post_id, $elements );
        
        return array(
            'success' => true,
            'updated_count' => count( $results['updated'] ),
            'failed_count' => count( $results['failed'] ),
            'updated' => $results['updated'],
            'failed' => $results['failed'],
            'message' => sprintf( '%d widgets updated, %d failed.', count( $results['updated'] ), count( $results['failed'] ) ),
        );
    }

    public function execute_find_widgets_by_type( $input ) {
        $post_id = absint( $input['post_id'] );
        $widget_type = sanitize_text_field( $input['widget_type'] );
        
        $post = get_post( $post_id );
        if ( ! $post ) {
            return new WP_Error( 'not_found', 'Post not found.', array( 'status' => 404 ) );
        }
        
        $elementor_data = get_post_meta( $post_id, '_elementor_data', true );
        $elements = $elementor_data ? json_decode( $elementor_data, true ) : array();
        
        if ( ! is_array( $elements ) ) {
            return array( 'widgets' => array(), 'count' => 0 );
        }
        
        $widgets = $this->find_all_widgets_by_type( $elements, $widget_type );
        
        return array(
            'post_id' => $post_id,
            'widget_type' => $widget_type,
            'widgets' => $widgets,
            'count' => count( $widgets ),
        );
    }

    public function execute_create_page( $input ) {
        $title = sanitize_text_field( $input['title'] );
        $post_type = isset( $input['post_type'] ) ? sanitize_key( $input['post_type'] ) : 'page';
        $status = isset( $input['status'] ) ? sanitize_key( $input['status'] ) : 'draft';
        $template = isset( $input['template'] ) ? sanitize_text_field( $input['template'] ) : '';

        if ( ! in_array( $post_type, array( 'page', 'post' ) ) ) {
            $post_type = 'page';
        }
        if ( ! in_array( $status, array( 'draft', 'publish', 'pending' ) ) ) {
            $status = 'draft';
        }

        $post_id = wp_insert_post( array(
            'post_title' => $title,
            'post_type' => $post_type,
            'post_status' => $status,
            'post_content' => '',
        ) );

        if ( is_wp_error( $post_id ) ) {
            return $post_id;
        }

        // Enable Elementor
        update_post_meta( $post_id, '_elementor_edit_mode', 'builder' );
        update_post_meta( $post_id, '_elementor_data', '[]' );

        // Set template if specified
        if ( $template ) {
            update_post_meta( $post_id, '_wp_page_template', $template );
        }

        return array(
            'post_id' => $post_id,
            'edit_url' => admin_url( 'post.php?post=' . $post_id . '&action=elementor' ),
            'preview_url' => get_preview_post_link( $post_id ),
        );
    }

    public function execute_get_document( $input ) {
        $post_id = absint( $input['post_id'] );
        $post = get_post( $post_id );
        if ( ! $post ) {
            return new WP_Error( 'not_found', 'Post not found.', array( 'status' => 404 ) );
        }

        $elementor_data = get_post_meta( $post_id, '_elementor_data', true );
        $elements = $elementor_data ? json_decode( $elementor_data, true ) : array();

        // Get page settings if Elementor is active
        $settings = array();
        if ( class_exists( 'Elementor\Plugin' ) ) {
            $document = Elementor\Plugin::$instance->documents->get( $post_id );
            if ( $document ) {
                $settings = $document->get_settings();
            }
        }

        return array(
            'post_id' => $post_id,
            'post_title' => $post->post_title,
            'post_type' => $post->post_type,
            'post_status' => $post->post_status,
            'elements' => is_array( $elements ) ? $elements : array(),
            'settings' => $settings,
            'edit_url' => admin_url( 'post.php?post=' . $post_id . '&action=elementor' ),
            'preview_url' => get_preview_post_link( $post_id ),
            'permalink' => get_permalink( $post_id ),
        );
    }

    public function execute_save_document( $input ) {
        $post_id = absint( $input['post_id'] );
        $post = get_post( $post_id );
        if ( ! $post ) {
            return new WP_Error( 'not_found', 'Post not found.', array( 'status' => 404 ) );
        }

        $elements = $input['elements'];
        if ( ! is_array( $elements ) ) {
            return new WP_Error( 'invalid_elements', 'Elements must be an array.' );
        }

        // Save elements
        $json_data = wp_json_encode( $elements );
        update_post_meta( $post_id, '_elementor_data', wp_slash( $json_data ) );
        update_post_meta( $post_id, '_elementor_edit_mode', 'builder' );

        // Save settings if provided
        if ( isset( $input['settings'] ) && is_array( $input['settings'] ) && class_exists( 'Elementor\Plugin' ) ) {
            $document = Elementor\Plugin::$instance->documents->get( $post_id );
            if ( $document ) {
                $document->save( array( 'settings' => $input['settings'] ) );
            }
        }

        // Clear cache
        if ( class_exists( 'Elementor\Plugin' ) ) {
            Elementor\Plugin::$instance->files_manager->clear_cache();
        }

        return array( 'success' => true, 'post_id' => $post_id );
    }

    // =========================================================================
    // WIDGET MANAGEMENT ABILITIES
    // =========================================================================

    private function register_widget_abilities() {
        // List Widgets
        wp_register_ability( 'elementor/list-widgets', array(
            'label' => 'List Elementor Widgets',
            'description' => 'Get all available Elementor widget types with their properties (name, title, icon, categories, keywords).',
            'category' => self::$category,
            'input_schema' => array(
                'type' => 'object',
                'properties' => array(
                    'category' => array( 'type' => 'string', 'description' => 'Filter by category (basic, general, pro-elements, etc.).' ),
                    'search' => array( 'type' => 'string', 'description' => 'Search widgets by name, title, or keywords.' ),
                ),
            ),
            'output_schema' => array( 'type' => 'object' ),
            'execute_callback' => array( $this, 'execute_list_widgets' ),
            'permission_callback' => function() { return current_user_can( 'edit_posts' ); },
            'meta' => self::$mcp_meta,
        ) );

        // Get Widget Schema
        wp_register_ability( 'elementor/get-widget-schema', array(
            'label' => 'Get Widget Schema',
            'description' => 'Get the complete schema for a specific widget including all controls, defaults, types, and validation rules.',
            'category' => self::$category,
            'input_schema' => array(
                'type' => 'object',
                'properties' => array(
                    'widget_name' => array( 'type' => 'string', 'description' => 'The widget name (e.g., "button", "heading", "image").' ),
                    'include_common_controls' => array( 'type' => 'boolean', 'description' => 'Include common controls (advanced tab). Default: true.', 'default' => true ),
                ),
                'required' => array( 'widget_name' ),
            ),
            'output_schema' => array( 'type' => 'object' ),
            'execute_callback' => array( $this, 'execute_get_widget_schema' ),
            'permission_callback' => function() { return current_user_can( 'edit_posts' ); },
            'meta' => self::$mcp_meta,
        ) );

        // Create Widget Instance
        wp_register_ability( 'elementor/create-widget-instance', array(
            'label' => 'Create Widget Instance',
            'description' => 'Create a properly formatted Elementor widget instance that can be inserted into a page or section.',
            'category' => self::$category,
            'input_schema' => array(
                'type' => 'object',
                'properties' => array(
                    'widget_type' => array( 'type' => 'string', 'description' => 'The widget type (e.g., "button", "heading", "image").' ),
                    'settings' => array( 'type' => 'object', 'description' => 'Widget settings as key-value pairs.' ),
                ),
                'required' => array( 'widget_type' ),
            ),
            'output_schema' => array( 'type' => 'object' ),
            'execute_callback' => array( $this, 'execute_create_widget_instance' ),
            'permission_callback' => function() { return current_user_can( 'edit_posts' ); },
            'meta' => self::$mcp_meta,
        ) );

        // Get Widget Controls
        wp_register_ability( 'elementor/get-widget-controls', array(
            'label' => 'Get Widget Controls',
            'description' => 'Get detailed control (field) information for a widget including type, label, default, options, conditions, and selectors.',
            'category' => self::$category,
            'input_schema' => array(
                'type' => 'object',
                'properties' => array(
                    'widget_name' => array( 'type' => 'string', 'description' => 'The widget name (e.g., "button", "heading", "image").' ),
                    'control_name' => array( 'type' => 'string', 'description' => 'Optional: Get a specific control by name.' ),
                    'include_common' => array( 'type' => 'boolean', 'description' => 'Include common controls (advanced tab). Default: true.', 'default' => true ),
                    'tab' => array( 'type' => 'string', 'description' => 'Filter by tab (content, style, advanced).' ),
                ),
                'required' => array( 'widget_name' ),
            ),
            'output_schema' => array( 'type' => 'object' ),
            'execute_callback' => array( $this, 'execute_get_widget_controls' ),
            'permission_callback' => function() { return current_user_can( 'edit_posts' ); },
            'meta' => self::$mcp_meta,
        ) );

        // Create Container
        wp_register_ability( 'elementor/create-container', array(
            'label' => 'Create Container',
            'description' => 'Create a modern Elementor container with flexbox or grid layout. Supports nested containers and widgets.',
            'category' => self::$category,
            'input_schema' => array(
                'type' => 'object',
                'properties' => array(
                    'layout' => array( 'type' => 'string', 'description' => 'Layout type: flexbox or grid. Default: flexbox.', 'default' => 'flexbox' ),
                    'direction' => array( 'type' => 'string', 'description' => 'Flex direction: row, column. Default: column.', 'default' => 'column' ),
                    'elements' => array( 'type' => 'array', 'description' => 'Child elements (widgets or nested containers).' ),
                    'settings' => array( 'type' => 'object', 'description' => 'Container settings (gap, padding, etc.).' ),
                ),
            ),
            'output_schema' => array( 'type' => 'object' ),
            'execute_callback' => array( $this, 'execute_create_container' ),
            'permission_callback' => function() { return current_user_can( 'edit_posts' ); },
            'meta' => self::$mcp_meta,
        ) );
    }

    public function execute_list_widgets( $input ) {
        if ( ! class_exists( 'Elementor\Plugin' ) ) {
            return new WP_Error( 'elementor_not_active', 'Elementor is not active.' );
        }

        $widgets_manager = Elementor\Plugin::$instance->widgets_manager;
        $widget_types = $widgets_manager->get_widget_types();

        if ( empty( $widget_types ) ) {
            return array( 'widgets' => array(), 'count' => 0 );
        }

        $category_filter = isset( $input['category'] ) ? sanitize_text_field( $input['category'] ) : '';
        $search_filter = isset( $input['search'] ) ? strtolower( sanitize_text_field( $input['search'] ) ) : '';

        $widgets_data = array();
        foreach ( $widget_types as $widget_name => $widget ) {
            $categories = $widget->get_categories();
            $title = $widget->get_title();
            $keywords = $widget->get_keywords();

            // Apply category filter
            if ( $category_filter && ! in_array( $category_filter, $categories ) ) {
                continue;
            }

            // Apply search filter
            if ( $search_filter ) {
                $searchable = strtolower( $widget_name . ' ' . $title . ' ' . implode( ' ', $keywords ) );
                if ( strpos( $searchable, $search_filter ) === false ) {
                    continue;
                }
            }

            $widgets_data[ $widget_name ] = array(
                'name' => $widget->get_name(),
                'title' => $title,
                'icon' => $widget->get_icon(),
                'categories' => $categories,
                'keywords' => $keywords,
            );
        }

        return array( 'widgets' => $widgets_data, 'count' => count( $widgets_data ) );
    }

    public function execute_get_widget_schema( $input ) {
        if ( ! class_exists( 'Elementor\Plugin' ) ) {
            return new WP_Error( 'elementor_not_active', 'Elementor is not active.' );
        }

        $widget_name = sanitize_text_field( $input['widget_name'] );
        $include_common = isset( $input['include_common_controls'] ) ? (bool) $input['include_common_controls'] : true;

        $widget = Elementor\Plugin::$instance->widgets_manager->get_widget_types( $widget_name );
        if ( ! $widget ) {
            return new WP_Error( 'widget_not_found', "Widget '$widget_name' not found." );
        }

        $stack = $widget->get_stack( $include_common );

        $schema = array(
            'name' => $widget->get_name(),
            'title' => $widget->get_title(),
            'icon' => $widget->get_icon(),
            'categories' => $widget->get_categories(),
            'keywords' => $widget->get_keywords(),
        );

        // Extract controls
        $controls = array();
        $defaults = array();
        if ( isset( $stack['controls'] ) && is_array( $stack['controls'] ) ) {
            foreach ( $stack['controls'] as $control_name => $control ) {
                $controls[ $control_name ] = array(
                    'type' => $control['type'] ?? '',
                    'label' => $control['label'] ?? '',
                    'default' => $control['default'] ?? null,
                    'description' => $control['description'] ?? '',
                    'options' => $control['options'] ?? null,
                    'condition' => $control['condition'] ?? null,
                );
                if ( isset( $control['default'] ) ) {
                    $defaults[ $control_name ] = $control['default'];
                }
            }
        }

        $schema['controls'] = $controls;
        $schema['defaults'] = $defaults;
        $schema['tabs'] = $stack['tabs'] ?? array();

        return $schema;
    }

    public function execute_create_widget_instance( $input ) {
        if ( ! class_exists( 'Elementor\Plugin' ) ) {
            return new WP_Error( 'elementor_not_active', 'Elementor is not active.' );
        }

        $widget_type = sanitize_text_field( $input['widget_type'] );
        $settings = isset( $input['settings'] ) && is_array( $input['settings'] ) ? $input['settings'] : array();

        $widget = Elementor\Plugin::$instance->widgets_manager->get_widget_types( $widget_type );
        if ( ! $widget ) {
            return new WP_Error( 'widget_not_found', "Widget '$widget_type' not found." );
        }

        // Generate unique ID (hex string like Elementor uses)
        $element_id = dechex( mt_rand( 0x10000000, 0x7FFFFFFF ) );

        $widget_instance = array(
            'id' => $element_id,
            'elType' => 'widget',
            'widgetType' => $widget_type,
            'settings' => $settings,
        );

        return array(
            'element' => $widget_instance,
            'metadata' => array(
                'widget_name' => $widget->get_name(),
                'widget_title' => $widget->get_title(),
                'categories' => $widget->get_categories(),
            ),
        );
    }

    public function execute_get_widget_controls( $input ) {
        if ( ! class_exists( 'Elementor\Plugin' ) ) {
            return new WP_Error( 'elementor_not_active', 'Elementor is not active.' );
        }

        $widget_name = sanitize_text_field( $input['widget_name'] );
        $control_name = isset( $input['control_name'] ) ? sanitize_text_field( $input['control_name'] ) : '';
        $include_common = isset( $input['include_common'] ) ? (bool) $input['include_common'] : true;
        $tab_filter = isset( $input['tab'] ) ? sanitize_text_field( $input['tab'] ) : '';

        $widget = Elementor\Plugin::$instance->widgets_manager->get_widget_types( $widget_name );
        if ( ! $widget ) {
            return new WP_Error( 'widget_not_found', "Widget '$widget_name' not found." );
        }

        $stack = $widget->get_stack( $include_common );
        $controls = $stack['controls'] ?? array();

        if ( empty( $controls ) ) {
            return array( 'controls' => array(), 'count' => 0 );
        }

        // If specific control requested
        if ( $control_name ) {
            if ( ! isset( $controls[ $control_name ] ) ) {
                return new WP_Error( 'control_not_found', "Control '$control_name' not found in widget '$widget_name'." );
            }
            return array( 'control' => $this->format_control_info( $controls[ $control_name ], $control_name ) );
        }

        // Return all controls with optional tab filter
        $controls_data = array();
        foreach ( $controls as $key => $control ) {
            $control_tab = $control['tab'] ?? 'content';
            if ( $tab_filter && $control_tab !== $tab_filter ) {
                continue;
            }
            $controls_data[ $key ] = $this->format_control_info( $control, $key );
        }

        return array( 'controls' => $controls_data, 'count' => count( $controls_data ) );
    }

    private function format_control_info( $control, $control_key ) {
        $info = array(
            'name' => $control_key,
            'type' => $control['type'] ?? '',
            'label' => $control['label'] ?? '',
        );

        $properties = array(
            'default', 'placeholder', 'description', 'separator', 'show_label',
            'label_block', 'dynamic', 'responsive', 'selectors', 'condition',
            'conditions', 'tab', 'section', 'options', 'min', 'max', 'step',
        );

        foreach ( $properties as $prop ) {
            if ( isset( $control[ $prop ] ) ) {
                $info[ $prop ] = $control[ $prop ];
            }
        }

        return $info;
    }

    public function execute_create_container( $input ) {
        $layout = isset( $input['layout'] ) ? sanitize_text_field( $input['layout'] ) : 'flexbox';
        $direction = isset( $input['direction'] ) ? sanitize_text_field( $input['direction'] ) : 'column';
        $elements = isset( $input['elements'] ) && is_array( $input['elements'] ) ? $input['elements'] : array();
        $settings = isset( $input['settings'] ) && is_array( $input['settings'] ) ? $input['settings'] : array();

        // Generate unique ID
        $element_id = dechex( mt_rand( 0x10000000, 0x7FFFFFFF ) );

        // Build container settings
        $container_settings = array_merge( array(
            'container_type' => 'flex',
            'flex_direction' => $direction,
        ), $settings );

        $container = array(
            'id' => $element_id,
            'elType' => 'container',
            'settings' => $container_settings,
            'elements' => $elements,
        );

        return array(
            'element' => $container,
            'message' => 'Container created successfully.',
        );
    }

    // =========================================================================
    // CONTROL MANAGEMENT ABILITIES
    // =========================================================================

    private function register_control_abilities() {
        // List Control Types
        wp_register_ability( 'elementor/list-control-types', array(
            'label' => 'List Control Types',
            'description' => 'Get all available Elementor control types with their properties and capabilities.',
            'category' => self::$category,
            'input_schema' => array(
                'type' => 'object',
                'properties' => array(
                    'include_ui_controls' => array( 'type' => 'boolean', 'description' => 'Include UI-only controls (section, heading, divider). Default: true.', 'default' => true ),
                ),
            ),
            'output_schema' => array( 'type' => 'object' ),
            'execute_callback' => array( $this, 'execute_list_control_types' ),
            'permission_callback' => function() { return current_user_can( 'edit_posts' ); },
            'meta' => self::$mcp_meta,
        ) );

        // Get Control Schema
        wp_register_ability( 'elementor/get-control-schema', array(
            'label' => 'Get Control Schema',
            'description' => 'Get detailed schema and configuration for a specific Elementor control type.',
            'category' => self::$category,
            'input_schema' => array(
                'type' => 'object',
                'properties' => array(
                    'control_type' => array( 'type' => 'string', 'description' => 'The control type (e.g., "text", "color", "slider", "dimensions").' ),
                ),
                'required' => array( 'control_type' ),
            ),
            'output_schema' => array( 'type' => 'object' ),
            'execute_callback' => array( $this, 'execute_get_control_schema' ),
            'permission_callback' => function() { return current_user_can( 'edit_posts' ); },
            'meta' => self::$mcp_meta,
        ) );
    }

    public function execute_list_control_types( $input ) {
        if ( ! class_exists( 'Elementor\Plugin' ) ) {
            return new WP_Error( 'elementor_not_active', 'Elementor is not active.' );
        }

        $include_ui = isset( $input['include_ui_controls'] ) ? (bool) $input['include_ui_controls'] : true;
        $controls_manager = Elementor\Plugin::$instance->controls_manager;
        $controls = $controls_manager->get_controls();

        $ui_controls = array( 'section', 'tab', 'heading', 'divider', 'raw_html', 'button' );

        $control_types = array();
        foreach ( $controls as $control_type => $control ) {
            $is_ui = in_array( $control_type, $ui_controls );
            if ( ! $include_ui && $is_ui ) {
                continue;
            }

            $control_types[ $control_type ] = array(
                'type' => $control_type,
                'is_ui_control' => $is_ui,
                'default_value' => method_exists( $control, 'get_default_value' ) ? $control->get_default_value() : null,
            );
        }

        return array( 'control_types' => $control_types, 'count' => count( $control_types ) );
    }

    public function execute_get_control_schema( $input ) {
        if ( ! class_exists( 'Elementor\Plugin' ) ) {
            return new WP_Error( 'elementor_not_active', 'Elementor is not active.' );
        }

        $control_type = sanitize_text_field( $input['control_type'] );
        $controls_manager = Elementor\Plugin::$instance->controls_manager;
        $control = $controls_manager->get_control( $control_type );

        if ( ! $control ) {
            return new WP_Error( 'control_not_found', "Control type '$control_type' not found." );
        }

        $schema = array(
            'type' => $control_type,
            'default_value' => method_exists( $control, 'get_default_value' ) ? $control->get_default_value() : null,
        );

        // Add common properties
        $common_properties = array(
            'type' => 'Control type identifier',
            'label' => 'Control label shown in the panel',
            'description' => 'Help text shown below the control',
            'default' => 'Default value for the control',
            'separator' => 'Separator before/after control (before, after, none)',
            'show_label' => 'Whether to show the label (true/false)',
            'label_block' => 'Whether label should be on separate line (true/false)',
            'condition' => 'Conditions for showing this control',
            'tab' => 'Tab where control appears (content, style, advanced)',
            'section' => 'Section ID this control belongs to',
        );

        $schema['available_properties'] = $common_properties;

        return $schema;
    }

    // =========================================================================
    // TEMPLATE ABILITIES
    // =========================================================================

    private function register_template_abilities() {
        wp_register_ability( 'elementor/list-templates', array(
            'label' => 'List Elementor Templates',
            'description' => 'Retrieves a list of all Elementor templates with filtering options.',
            'category' => self::$category,
            'input_schema' => array(
                'type' => 'object',
                'properties' => array(
                    'type' => array( 'type' => 'string', 'description' => 'Template type filter (page, section, widget, all). Default: all.' ),
                    'per_page' => array( 'type' => 'integer', 'description' => 'Number of results. Default: 50.', 'default' => 50 ),
                ),
            ),
            'output_schema' => array( 'type' => 'array' ),
            'execute_callback' => function( $input ) {
                $query_args = array(
                    'post_type' => 'elementor_library',
                    'posts_per_page' => isset($input['per_page']) ? absint($input['per_page']) : 50,
                    'post_status' => array( 'publish', 'draft' ),
                );

                // Filter by template type
                if ( isset( $input['type'] ) && $input['type'] !== 'all' ) {
                    $query_args['meta_query'] = array(
                        array( 'key' => '_elementor_template_type', 'value' => sanitize_text_field( $input['type'] ) ),
                    );
                }

                $query = new WP_Query( $query_args );
                $templates = array();
                foreach ( $query->posts as $post ) {
                    $templates[] = array(
                        'id' => $post->ID,
                        'title' => $post->post_title,
                        'template_type' => get_post_meta( $post->ID, '_elementor_template_type', true ),
                        'status' => $post->post_status,
                        'edit_url' => admin_url( 'post.php?post=' . $post->ID . '&action=elementor' ),
                    );
                }
                return $templates;
            },
            'permission_callback' => function() { return current_user_can( 'edit_posts' ); },
            'meta' => self::$mcp_meta,
        ) );

        // Upload/Import Template
        wp_register_ability( 'elementor/upload-template', array(
            'label' => 'Upload Elementor Template',
            'description' => 'Uploads/imports a new Elementor template from JSON data. Creates a new template in the Elementor library.',
            'category' => self::$category,
            'input_schema' => array(
                'type' => 'object',
                'properties' => array(
                    'title' => array( 'type' => 'string', 'description' => 'Template title/name.' ),
                    'template_type' => array( 'type' => 'string', 'description' => 'Template type: page, section, container, header, footer, single, archive, loop-item, popup. Default: section.', 'default' => 'section' ),
                    'elements' => array( 'type' => 'array', 'description' => 'Array of Elementor elements (sections/containers, columns, widgets) that make up the template.' ),
                    'elementor_data' => array( 'type' => 'string', 'description' => 'Alternative: Raw Elementor JSON data string. Use either elements (array) or elementor_data (string), not both.' ),
                    'page_settings' => array( 'type' => 'object', 'description' => 'Optional page/template settings.' ),
                    'status' => array( 'type' => 'string', 'description' => 'Post status: publish, draft. Default: publish.', 'default' => 'publish' ),
                ),
                'required' => array( 'title' ),
            ),
            'output_schema' => array(
                'type' => 'object',
                'properties' => array(
                    'success' => array( 'type' => 'boolean' ),
                    'template_id' => array( 'type' => 'integer' ),
                    'title' => array( 'type' => 'string' ),
                    'template_type' => array( 'type' => 'string' ),
                    'edit_url' => array( 'type' => 'string' ),
                    'preview_url' => array( 'type' => 'string' ),
                ),
            ),
            'execute_callback' => array( $this, 'execute_upload_template' ),
            'permission_callback' => function() { return current_user_can( 'edit_posts' ); },
            'meta' => self::$mcp_meta,
        ) );

        // Get Template
        wp_register_ability( 'elementor/get-template', array(
            'label' => 'Get Elementor Template',
            'description' => 'Retrieves a specific Elementor template with its complete data, elements, and settings.',
            'category' => self::$category,
            'input_schema' => array(
                'type' => 'object',
                'properties' => array(
                    'template_id' => array( 'type' => 'integer', 'description' => 'The ID of the template to retrieve.' ),
                ),
                'required' => array( 'template_id' ),
            ),
            'output_schema' => array(
                'type' => 'object',
                'properties' => array(
                    'id' => array( 'type' => 'integer' ),
                    'title' => array( 'type' => 'string' ),
                    'template_type' => array( 'type' => 'string' ),
                    'elements' => array( 'type' => 'array' ),
                    'page_settings' => array( 'type' => 'object' ),
                    'status' => array( 'type' => 'string' ),
                    'edit_url' => array( 'type' => 'string' ),
                    'preview_url' => array( 'type' => 'string' ),
                ),
            ),
            'execute_callback' => function( $input ) {
                $template_id = absint( $input['template_id'] );
                $post = get_post( $template_id );
                
                if ( ! $post || $post->post_type !== 'elementor_library' ) {
                    return new WP_Error( 'not_found', 'Template not found.', array( 'status' => 404 ) );
                }
                
                $elementor_data = get_post_meta( $template_id, '_elementor_data', true );
                $elements = $elementor_data ? json_decode( $elementor_data, true ) : array();
                $page_settings = get_post_meta( $template_id, '_elementor_page_settings', true );
                
                return array(
                    'id' => $template_id,
                    'title' => $post->post_title,
                    'template_type' => get_post_meta( $template_id, '_elementor_template_type', true ),
                    'elements' => $elements,
                    'page_settings' => $page_settings ? $page_settings : array(),
                    'status' => $post->post_status,
                    'edit_url' => admin_url( 'post.php?post=' . $template_id . '&action=elementor' ),
                    'preview_url' => get_preview_post_link( $template_id ),
                );
            },
            'permission_callback' => function() { return current_user_can( 'edit_posts' ); },
            'meta' => self::$mcp_meta,
        ) );

        // Delete Template
        wp_register_ability( 'elementor/delete-template', array(
            'label' => 'Delete Elementor Template',
            'description' => 'Deletes an Elementor template from the library.',
            'category' => self::$category,
            'input_schema' => array(
                'type' => 'object',
                'properties' => array(
                    'template_id' => array( 'type' => 'integer', 'description' => 'The ID of the template to delete.' ),
                    'force' => array( 'type' => 'boolean', 'description' => 'Force delete (bypass trash). Default: false.', 'default' => false ),
                ),
                'required' => array( 'template_id' ),
            ),
            'output_schema' => array(
                'type' => 'object',
                'properties' => array(
                    'success' => array( 'type' => 'boolean' ),
                    'deleted_id' => array( 'type' => 'integer' ),
                ),
            ),
            'execute_callback' => function( $input ) {
                $template_id = absint( $input['template_id'] );
                $post = get_post( $template_id );
                
                if ( ! $post || $post->post_type !== 'elementor_library' ) {
                    return new WP_Error( 'not_found', 'Template not found.', array( 'status' => 404 ) );
                }
                
                $force = isset( $input['force'] ) && $input['force'];
                $result = wp_delete_post( $template_id, $force );
                
                if ( ! $result ) {
                    return new WP_Error( 'delete_failed', 'Failed to delete template.', array( 'status' => 500 ) );
                }
                
                return array(
                    'success' => true,
                    'deleted_id' => $template_id,
                );
            },
            'permission_callback' => function() { return current_user_can( 'delete_posts' ); },
            'meta' => self::$mcp_meta,
        ) );

        // Update Template
        wp_register_ability( 'elementor/update-template', array(
            'label' => 'Update Elementor Template',
            'description' => 'Updates an existing Elementor template with new elements, settings, or metadata.',
            'category' => self::$category,
            'input_schema' => array(
                'type' => 'object',
                'properties' => array(
                    'template_id' => array( 'type' => 'integer', 'description' => 'The ID of the template to update.' ),
                    'title' => array( 'type' => 'string', 'description' => 'New template title (optional).' ),
                    'elements' => array( 'type' => 'array', 'description' => 'New Elementor elements array (optional).' ),
                    'elementor_data' => array( 'type' => 'string', 'description' => 'Alternative: Raw Elementor JSON data string.' ),
                    'page_settings' => array( 'type' => 'object', 'description' => 'Updated page/template settings (optional).' ),
                    'status' => array( 'type' => 'string', 'description' => 'New post status (optional).' ),
                ),
                'required' => array( 'template_id' ),
            ),
            'output_schema' => array(
                'type' => 'object',
                'properties' => array(
                    'success' => array( 'type' => 'boolean' ),
                    'template_id' => array( 'type' => 'integer' ),
                    'edit_url' => array( 'type' => 'string' ),
                ),
            ),
            'execute_callback' => array( $this, 'execute_update_template' ),
            'permission_callback' => function() { return current_user_can( 'edit_posts' ); },
            'meta' => self::$mcp_meta,
        ) );

        // Duplicate Template
        wp_register_ability( 'elementor/duplicate-template', array(
            'label' => 'Duplicate Elementor Template',
            'description' => 'Creates a copy of an existing Elementor template with a new title.',
            'category' => self::$category,
            'input_schema' => array(
                'type' => 'object',
                'properties' => array(
                    'template_id' => array( 'type' => 'integer', 'description' => 'The ID of the template to duplicate.' ),
                    'new_title' => array( 'type' => 'string', 'description' => 'Title for the duplicated template. Default: "[Original Title] (Copy)".' ),
                ),
                'required' => array( 'template_id' ),
            ),
            'output_schema' => array(
                'type' => 'object',
                'properties' => array(
                    'success' => array( 'type' => 'boolean' ),
                    'original_id' => array( 'type' => 'integer' ),
                    'new_template_id' => array( 'type' => 'integer' ),
                    'title' => array( 'type' => 'string' ),
                    'edit_url' => array( 'type' => 'string' ),
                ),
            ),
            'execute_callback' => function( $input ) {
                $template_id = absint( $input['template_id'] );
                $post = get_post( $template_id );
                
                if ( ! $post || $post->post_type !== 'elementor_library' ) {
                    return new WP_Error( 'not_found', 'Template not found.', array( 'status' => 404 ) );
                }
                
                $new_title = isset( $input['new_title'] ) 
                    ? sanitize_text_field( $input['new_title'] ) 
                    : $post->post_title . ' (Copy)';
                
                // Create the duplicate post
                $new_post_id = wp_insert_post( array(
                    'post_title' => $new_title,
                    'post_type' => 'elementor_library',
                    'post_status' => 'publish',
                ), true );
                
                if ( is_wp_error( $new_post_id ) ) {
                    return $new_post_id;
                }
                
                // Copy all post meta
                $meta_keys = array(
                    '_elementor_data',
                    '_elementor_template_type',
                    '_elementor_edit_mode',
                    '_elementor_page_settings',
                    '_elementor_version',
                );
                
                foreach ( $meta_keys as $key ) {
                    $value = get_post_meta( $template_id, $key, true );
                    if ( $value ) {
                        update_post_meta( $new_post_id, $key, $value );
                    }
                }
                
                // Clear cache
                if ( class_exists( 'Elementor\Plugin' ) ) {
                    Elementor\Plugin::$instance->files_manager->clear_cache();
                }
                
                return array(
                    'success' => true,
                    'original_id' => $template_id,
                    'new_template_id' => $new_post_id,
                    'title' => $new_title,
                    'edit_url' => admin_url( 'post.php?post=' . $new_post_id . '&action=elementor' ),
                );
            },
            'permission_callback' => function() { return current_user_can( 'edit_posts' ); },
            'meta' => self::$mcp_meta,
        ) );

        // Export Template (as JSON)
        wp_register_ability( 'elementor/export-template', array(
            'label' => 'Export Elementor Template',
            'description' => 'Exports an Elementor template as JSON data that can be imported later or shared.',
            'category' => self::$category,
            'input_schema' => array(
                'type' => 'object',
                'properties' => array(
                    'template_id' => array( 'type' => 'integer', 'description' => 'The ID of the template to export.' ),
                ),
                'required' => array( 'template_id' ),
            ),
            'output_schema' => array(
                'type' => 'object',
                'properties' => array(
                    'success' => array( 'type' => 'boolean' ),
                    'template_id' => array( 'type' => 'integer' ),
                    'title' => array( 'type' => 'string' ),
                    'export_data' => array( 'type' => 'object', 'description' => 'Complete template export data including elements and metadata.' ),
                ),
            ),
            'execute_callback' => function( $input ) {
                $template_id = absint( $input['template_id'] );
                $post = get_post( $template_id );
                
                if ( ! $post || $post->post_type !== 'elementor_library' ) {
                    return new WP_Error( 'not_found', 'Template not found.', array( 'status' => 404 ) );
                }
                
                $elementor_data = get_post_meta( $template_id, '_elementor_data', true );
                $elements = $elementor_data ? json_decode( $elementor_data, true ) : array();
                $page_settings = get_post_meta( $template_id, '_elementor_page_settings', true );
                $template_type = get_post_meta( $template_id, '_elementor_template_type', true );
                
                $export_data = array(
                    'title' => $post->post_title,
                    'template_type' => $template_type ? $template_type : 'section',
                    'content' => $elements,
                    'page_settings' => $page_settings ? $page_settings : array(),
                    'version' => defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : '3.0.0',
                    'type' => 'elementor',
                );
                
                return array(
                    'success' => true,
                    'template_id' => $template_id,
                    'title' => $post->post_title,
                    'export_data' => $export_data,
                );
            },
            'permission_callback' => function() { return current_user_can( 'edit_posts' ); },
            'meta' => self::$mcp_meta,
        ) );
    }

    /**
     * Execute upload/import template
     */
    public function execute_upload_template( $input ) {
        $title = sanitize_text_field( $input['title'] );
        $template_type = isset( $input['template_type'] ) ? sanitize_key( $input['template_type'] ) : 'section';
        $status = isset( $input['status'] ) ? sanitize_key( $input['status'] ) : 'publish';
        
        // Validate template type
        $valid_types = array( 'page', 'section', 'container', 'header', 'footer', 'single', 'archive', 'loop-item', 'popup', 'widget' );
        if ( ! in_array( $template_type, $valid_types ) ) {
            $template_type = 'section';
        }
        
        // Determine elements data - either from elements array or raw elementor_data string
        $elements = array();
        if ( isset( $input['elements'] ) && is_array( $input['elements'] ) ) {
            $elements = $input['elements'];
            $elementor_data = wp_json_encode( $elements );
        } elseif ( isset( $input['elementor_data'] ) && is_string( $input['elementor_data'] ) ) {
            $elementor_data = $input['elementor_data'];
            $elements = json_decode( $elementor_data, true );
            if ( json_last_error() !== JSON_ERROR_NONE ) {
                return new WP_Error( 'invalid_json', 'Invalid JSON in elementor_data.', array( 'status' => 400 ) );
            }
        } else {
            $elementor_data = '[]';
        }
        
        // Create the template post
        $post_id = wp_insert_post( array(
            'post_title' => $title,
            'post_type' => 'elementor_library',
            'post_status' => $status,
        ), true );
        
        if ( is_wp_error( $post_id ) ) {
            return $post_id;
        }
        
        // Set Elementor metadata
        update_post_meta( $post_id, '_elementor_data', wp_slash( $elementor_data ) );
        update_post_meta( $post_id, '_elementor_template_type', $template_type );
        update_post_meta( $post_id, '_elementor_edit_mode', 'builder' );
        update_post_meta( $post_id, '_elementor_version', defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : '3.0.0' );
        
        // Set page settings if provided
        if ( isset( $input['page_settings'] ) && is_array( $input['page_settings'] ) ) {
            update_post_meta( $post_id, '_elementor_page_settings', $input['page_settings'] );
        }
        
        // Set the template type taxonomy term (used by Elementor for categorization)
        wp_set_object_terms( $post_id, $template_type, 'elementor_library_type' );
        
        // Clear Elementor cache
        if ( class_exists( 'Elementor\Plugin' ) ) {
            Elementor\Plugin::$instance->files_manager->clear_cache();
        }
        
        return array(
            'success' => true,
            'template_id' => $post_id,
            'title' => $title,
            'template_type' => $template_type,
            'edit_url' => admin_url( 'post.php?post=' . $post_id . '&action=elementor' ),
            'preview_url' => get_preview_post_link( $post_id ),
        );
    }

    /**
     * Execute update template
     */
    public function execute_update_template( $input ) {
        $template_id = absint( $input['template_id'] );
        $post = get_post( $template_id );
        
        if ( ! $post || $post->post_type !== 'elementor_library' ) {
            return new WP_Error( 'not_found', 'Template not found.', array( 'status' => 404 ) );
        }
        
        // Update post data if title or status provided
        $update_post = array( 'ID' => $template_id );
        $needs_update = false;
        
        if ( isset( $input['title'] ) ) {
            $update_post['post_title'] = sanitize_text_field( $input['title'] );
            $needs_update = true;
        }
        
        if ( isset( $input['status'] ) ) {
            $update_post['post_status'] = sanitize_key( $input['status'] );
            $needs_update = true;
        }
        
        if ( $needs_update ) {
            $result = wp_update_post( $update_post, true );
            if ( is_wp_error( $result ) ) {
                return $result;
            }
        }
        
        // Update elements data
        if ( isset( $input['elements'] ) && is_array( $input['elements'] ) ) {
            $elementor_data = wp_json_encode( $input['elements'] );
            update_post_meta( $template_id, '_elementor_data', wp_slash( $elementor_data ) );
        } elseif ( isset( $input['elementor_data'] ) && is_string( $input['elementor_data'] ) ) {
            $decoded = json_decode( $input['elementor_data'], true );
            if ( json_last_error() !== JSON_ERROR_NONE ) {
                return new WP_Error( 'invalid_json', 'Invalid JSON in elementor_data.', array( 'status' => 400 ) );
            }
            update_post_meta( $template_id, '_elementor_data', wp_slash( $input['elementor_data'] ) );
        }
        
        // Update page settings if provided
        if ( isset( $input['page_settings'] ) && is_array( $input['page_settings'] ) ) {
            $existing_settings = get_post_meta( $template_id, '_elementor_page_settings', true );
            if ( ! is_array( $existing_settings ) ) {
                $existing_settings = array();
            }
            $merged_settings = array_merge( $existing_settings, $input['page_settings'] );
            update_post_meta( $template_id, '_elementor_page_settings', $merged_settings );
        }
        
        // Clear Elementor cache
        if ( class_exists( 'Elementor\Plugin' ) ) {
            Elementor\Plugin::$instance->files_manager->clear_cache();
        }
        
        return array(
            'success' => true,
            'template_id' => $template_id,
            'edit_url' => admin_url( 'post.php?post=' . $template_id . '&action=elementor' ),
        );
    }

    // =========================================================================
    // GLOBAL ABILITIES
    // =========================================================================

    private function register_global_abilities() {
        wp_register_ability( 'elementor/clear-cache', array(
            'label' => 'Clear Elementor Cache',
            'description' => 'Clears all Elementor CSS and data caches.',
            'category' => self::$category,
            'input_schema' => array( 'type' => 'object', 'properties' => array() ),
            'output_schema' => array(
                'type' => 'object',
                'properties' => array( 'success' => array( 'type' => 'boolean' ) ),
            ),
            'execute_callback' => function( $input ) {
                if ( ! class_exists( 'Elementor\Plugin' ) ) {
                    return new WP_Error( 'elementor_not_active', 'Elementor is not active.' );
                }
                Elementor\Plugin::$instance->files_manager->clear_cache();
                return array( 'success' => true );
            },
            'permission_callback' => function() { return current_user_can( 'manage_options' ); },
            'meta' => self::$mcp_meta,
        ) );

        wp_register_ability( 'elementor/get-info', array(
            'label' => 'Get Elementor Info',
            'description' => 'Returns detailed information about the Elementor installation including version, widgets, and experiments.',
            'category' => self::$category,
            'input_schema' => array(
                'type' => 'object',
                'properties' => array(
                    'include_widgets' => array( 'type' => 'boolean', 'description' => 'Include list of registered widgets. Default: false.', 'default' => false ),
                    'include_settings' => array( 'type' => 'boolean', 'description' => 'Include Elementor settings. Default: false.', 'default' => false ),
                ),
            ),
            'output_schema' => array( 'type' => 'object' ),
            'execute_callback' => function( $input ) {
                if ( ! class_exists( 'Elementor\Plugin' ) ) {
                    return new WP_Error( 'elementor_not_active', 'Elementor is not active.' );
                }

                $info = array(
                    'version' => defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : 'unknown',
                    'is_pro' => defined( 'ELEMENTOR_PRO_VERSION' ),
                    'pro_version' => defined( 'ELEMENTOR_PRO_VERSION' ) ? ELEMENTOR_PRO_VERSION : null,
                );

                if ( isset( $input['include_widgets'] ) && $input['include_widgets'] ) {
                    $widgets = Elementor\Plugin::$instance->widgets_manager->get_widget_types();
                    $info['widgets'] = array(
                        'count' => count( $widgets ),
                        'names' => array_keys( $widgets ),
                    );
                }

                if ( isset( $input['include_settings'] ) && $input['include_settings'] ) {
                    $info['settings'] = array(
                        'css_print_method' => get_option( 'elementor_css_print_method', 'external' ),
                        'disable_color_schemes' => get_option( 'elementor_disable_color_schemes' ),
                        'disable_typography_schemes' => get_option( 'elementor_disable_typography_schemes' ),
                    );
                }

                return $info;
            },
            'permission_callback' => function() { return current_user_can( 'edit_posts' ); },
            'meta' => self::$mcp_meta,
        ) );
    }

    // =========================================================================
    // TAXONOMY ABILITIES (Categories & Tags)
    // =========================================================================

    private function register_taxonomy_abilities() {
        // Get Categories
        wp_register_ability( 'elementor/get-categories', array(
            'label' => 'Get WordPress Categories',
            'description' => 'Retrieve WordPress categories with optional search and filtering.',
            'category' => self::$category,
            'input_schema' => array(
                'type' => 'object',
                'properties' => array(
                    'search' => array( 'type' => 'string', 'description' => 'Search categories by name.' ),
                    'per_page' => array( 'type' => 'integer', 'description' => 'Number of results. Default: 100.', 'default' => 100 ),
                    'hide_empty' => array( 'type' => 'boolean', 'description' => 'Hide categories with no posts. Default: false.', 'default' => false ),
                ),
            ),
            'output_schema' => array( 'type' => 'object' ),
            'execute_callback' => function( $input ) {
                $args = array(
                    'taxonomy' => 'category',
                    'number' => isset( $input['per_page'] ) ? absint( $input['per_page'] ) : 100,
                    'hide_empty' => isset( $input['hide_empty'] ) ? (bool) $input['hide_empty'] : false,
                );
                if ( isset( $input['search'] ) ) {
                    $args['search'] = sanitize_text_field( $input['search'] );
                }
                $terms = get_terms( $args );
                $categories = array();
                foreach ( $terms as $term ) {
                    $categories[] = array(
                        'id' => $term->term_id,
                        'name' => $term->name,
                        'slug' => $term->slug,
                        'description' => $term->description,
                        'parent' => $term->parent,
                        'count' => $term->count,
                    );
                }
                return array( 'categories' => $categories, 'count' => count( $categories ) );
            },
            'permission_callback' => function() { return current_user_can( 'edit_posts' ); },
            'meta' => self::$mcp_meta,
        ) );

        // Create Category
        wp_register_ability( 'elementor/create-category', array(
            'label' => 'Create WordPress Category',
            'description' => 'Create a new WordPress category or return existing if duplicate.',
            'category' => self::$category,
            'input_schema' => array(
                'type' => 'object',
                'properties' => array(
                    'name' => array( 'type' => 'string', 'description' => 'Category name.' ),
                    'slug' => array( 'type' => 'string', 'description' => 'Category slug (optional).' ),
                    'description' => array( 'type' => 'string', 'description' => 'Category description.' ),
                    'parent' => array( 'type' => 'integer', 'description' => 'Parent category ID.' ),
                ),
                'required' => array( 'name' ),
            ),
            'output_schema' => array( 'type' => 'object' ),
            'execute_callback' => function( $input ) {
                $name = sanitize_text_field( $input['name'] );

                // Check if exists
                $existing = get_term_by( 'name', $name, 'category' );
                if ( $existing ) {
                    return array(
                        'created' => false,
                        'category' => array(
                            'id' => $existing->term_id,
                            'name' => $existing->name,
                            'slug' => $existing->slug,
                        ),
                        'message' => "Category '$name' already exists.",
                    );
                }

                $args = array();
                if ( isset( $input['slug'] ) ) $args['slug'] = sanitize_title( $input['slug'] );
                if ( isset( $input['description'] ) ) $args['description'] = sanitize_textarea_field( $input['description'] );
                if ( isset( $input['parent'] ) ) $args['parent'] = absint( $input['parent'] );

                $result = wp_insert_term( $name, 'category', $args );
                if ( is_wp_error( $result ) ) {
                    return $result;
                }

                $term = get_term( $result['term_id'], 'category' );
                return array(
                    'created' => true,
                    'category' => array(
                        'id' => $term->term_id,
                        'name' => $term->name,
                        'slug' => $term->slug,
                    ),
                    'message' => "Category '$name' created successfully.",
                );
            },
            'permission_callback' => function() { return current_user_can( 'manage_categories' ); },
            'meta' => self::$mcp_meta,
        ) );

        // Get Tags
        wp_register_ability( 'elementor/get-tags', array(
            'label' => 'Get WordPress Tags',
            'description' => 'Retrieve WordPress tags with optional search and filtering.',
            'category' => self::$category,
            'input_schema' => array(
                'type' => 'object',
                'properties' => array(
                    'search' => array( 'type' => 'string', 'description' => 'Search tags by name.' ),
                    'per_page' => array( 'type' => 'integer', 'description' => 'Number of results. Default: 100.', 'default' => 100 ),
                    'hide_empty' => array( 'type' => 'boolean', 'description' => 'Hide tags with no posts. Default: false.', 'default' => false ),
                ),
            ),
            'output_schema' => array( 'type' => 'object' ),
            'execute_callback' => function( $input ) {
                $args = array(
                    'taxonomy' => 'post_tag',
                    'number' => isset( $input['per_page'] ) ? absint( $input['per_page'] ) : 100,
                    'hide_empty' => isset( $input['hide_empty'] ) ? (bool) $input['hide_empty'] : false,
                );
                if ( isset( $input['search'] ) ) {
                    $args['search'] = sanitize_text_field( $input['search'] );
                }
                $terms = get_terms( $args );
                $tags = array();
                foreach ( $terms as $term ) {
                    $tags[] = array(
                        'id' => $term->term_id,
                        'name' => $term->name,
                        'slug' => $term->slug,
                        'count' => $term->count,
                    );
                }
                return array( 'tags' => $tags, 'count' => count( $tags ) );
            },
            'permission_callback' => function() { return current_user_can( 'edit_posts' ); },
            'meta' => self::$mcp_meta,
        ) );

        // Create Tag
        wp_register_ability( 'elementor/create-tag', array(
            'label' => 'Create WordPress Tag',
            'description' => 'Create a new WordPress tag or return existing if duplicate.',
            'category' => self::$category,
            'input_schema' => array(
                'type' => 'object',
                'properties' => array(
                    'name' => array( 'type' => 'string', 'description' => 'Tag name.' ),
                    'slug' => array( 'type' => 'string', 'description' => 'Tag slug (optional).' ),
                    'description' => array( 'type' => 'string', 'description' => 'Tag description.' ),
                ),
                'required' => array( 'name' ),
            ),
            'output_schema' => array( 'type' => 'object' ),
            'execute_callback' => function( $input ) {
                $name = sanitize_text_field( $input['name'] );

                // Check if exists
                $existing = get_term_by( 'name', $name, 'post_tag' );
                if ( $existing ) {
                    return array(
                        'created' => false,
                        'tag' => array(
                            'id' => $existing->term_id,
                            'name' => $existing->name,
                            'slug' => $existing->slug,
                        ),
                        'message' => "Tag '$name' already exists.",
                    );
                }

                $args = array();
                if ( isset( $input['slug'] ) ) $args['slug'] = sanitize_title( $input['slug'] );
                if ( isset( $input['description'] ) ) $args['description'] = sanitize_textarea_field( $input['description'] );

                $result = wp_insert_term( $name, 'post_tag', $args );
                if ( is_wp_error( $result ) ) {
                    return $result;
                }

                $term = get_term( $result['term_id'], 'post_tag' );
                return array(
                    'created' => true,
                    'tag' => array(
                        'id' => $term->term_id,
                        'name' => $term->name,
                        'slug' => $term->slug,
                    ),
                    'message' => "Tag '$name' created successfully.",
                );
            },
            'permission_callback' => function() { return current_user_can( 'manage_categories' ); },
            'meta' => self::$mcp_meta,
        ) );
    }

    // =========================================================================
    // MEDIA ABILITIES
    // =========================================================================

    private function register_media_abilities() {
        // Get Media Library
        wp_register_ability( 'elementor/get-media', array(
            'label' => 'Get Media Library',
            'description' => 'Retrieve media items from the WordPress media library with filtering options.',
            'category' => self::$category,
            'input_schema' => array(
                'type' => 'object',
                'properties' => array(
                    'per_page' => array( 'type' => 'integer', 'description' => 'Number of results. Default: 20.', 'default' => 20 ),
                    'search' => array( 'type' => 'string', 'description' => 'Search media by title.' ),
                    'mime_type' => array( 'type' => 'string', 'description' => 'Filter by MIME type (image, video, audio, application).' ),
                ),
            ),
            'output_schema' => array( 'type' => 'object' ),
            'execute_callback' => function( $input ) {
                $args = array(
                    'post_type' => 'attachment',
                    'post_status' => 'inherit',
                    'posts_per_page' => isset( $input['per_page'] ) ? absint( $input['per_page'] ) : 20,
                );
                if ( isset( $input['search'] ) ) {
                    $args['s'] = sanitize_text_field( $input['search'] );
                }
                if ( isset( $input['mime_type'] ) ) {
                    $args['post_mime_type'] = sanitize_text_field( $input['mime_type'] );
                }

                $query = new WP_Query( $args );
                $media = array();
                foreach ( $query->posts as $attachment ) {
                    $media[] = array(
                        'id' => $attachment->ID,
                        'title' => $attachment->post_title,
                        'url' => wp_get_attachment_url( $attachment->ID ),
                        'mime_type' => $attachment->post_mime_type,
                        'alt_text' => get_post_meta( $attachment->ID, '_wp_attachment_image_alt', true ),
                        'caption' => $attachment->post_excerpt,
                    );
                }
                return array( 'media' => $media, 'count' => count( $media ) );
            },
            'permission_callback' => function() { return current_user_can( 'upload_files' ); },
            'meta' => self::$mcp_meta,
        ) );

        // Upload Media from URL
        wp_register_ability( 'elementor/upload-media-url', array(
            'label' => 'Upload Media from URL',
            'description' => 'Download and upload an image from a URL to the WordPress media library.',
            'category' => self::$category,
            'input_schema' => array(
                'type' => 'object',
                'properties' => array(
                    'url' => array( 'type' => 'string', 'description' => 'URL of the image to upload.' ),
                    'title' => array( 'type' => 'string', 'description' => 'Title for the media item.' ),
                    'alt_text' => array( 'type' => 'string', 'description' => 'Alt text for the image.' ),
                    'caption' => array( 'type' => 'string', 'description' => 'Caption for the media.' ),
                    'post_id' => array( 'type' => 'integer', 'description' => 'Optional post ID to attach media to.' ),
                ),
                'required' => array( 'url' ),
            ),
            'output_schema' => array( 'type' => 'object' ),
            'execute_callback' => array( $this, 'execute_upload_media_url' ),
            'permission_callback' => function() { return current_user_can( 'upload_files' ); },
            'meta' => self::$mcp_meta,
        ) );

        // Set Featured Image
        wp_register_ability( 'elementor/set-featured-image', array(
            'label' => 'Set Featured Image',
            'description' => 'Set or update the featured image for a post.',
            'category' => self::$category,
            'input_schema' => array(
                'type' => 'object',
                'properties' => array(
                    'post_id' => array( 'type' => 'integer', 'description' => 'Post ID to set featured image for.' ),
                    'media_id' => array( 'type' => 'integer', 'description' => 'Media attachment ID to use as featured image.' ),
                ),
                'required' => array( 'post_id', 'media_id' ),
            ),
            'output_schema' => array( 'type' => 'object' ),
            'execute_callback' => function( $input ) {
                $post_id = absint( $input['post_id'] );
                $media_id = absint( $input['media_id'] );

                $post = get_post( $post_id );
                if ( ! $post ) {
                    return new WP_Error( 'not_found', 'Post not found.' );
                }

                $attachment = get_post( $media_id );
                if ( ! $attachment || $attachment->post_type !== 'attachment' ) {
                    return new WP_Error( 'invalid_media', 'Invalid media attachment ID.' );
                }

                $result = set_post_thumbnail( $post_id, $media_id );
                return array(
                    'success' => (bool) $result,
                    'post_id' => $post_id,
                    'media_id' => $media_id,
                    'thumbnail_url' => get_the_post_thumbnail_url( $post_id, 'full' ),
                );
            },
            'permission_callback' => function() { return current_user_can( 'edit_posts' ); },
            'meta' => self::$mcp_meta,
        ) );
    }

    public function execute_upload_media_url( $input ) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $url = esc_url_raw( $input['url'] );

        // Download file
        $tmp = download_url( $url );
        if ( is_wp_error( $tmp ) ) {
            return $tmp;
        }

        // Get filename from URL
        $filename = basename( parse_url( $url, PHP_URL_PATH ) );
        if ( empty( $filename ) ) {
            $filename = 'uploaded-image-' . time() . '.jpg';
        }

        $file_array = array(
            'name' => $filename,
            'tmp_name' => $tmp,
        );

        // Upload to media library
        $post_id = isset( $input['post_id'] ) ? absint( $input['post_id'] ) : 0;
        $attachment_id = media_handle_sideload( $file_array, $post_id );

        // Clean up temp file
        @unlink( $tmp );

        if ( is_wp_error( $attachment_id ) ) {
            return $attachment_id;
        }

        // Set metadata
        if ( isset( $input['title'] ) ) {
            wp_update_post( array(
                'ID' => $attachment_id,
                'post_title' => sanitize_text_field( $input['title'] ),
            ) );
        }
        if ( isset( $input['alt_text'] ) ) {
            update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $input['alt_text'] ) );
        }
        if ( isset( $input['caption'] ) ) {
            wp_update_post( array(
                'ID' => $attachment_id,
                'post_excerpt' => sanitize_textarea_field( $input['caption'] ),
            ) );
        }

        return array(
            'success' => true,
            'id' => $attachment_id,
            'url' => wp_get_attachment_url( $attachment_id ),
            'title' => get_the_title( $attachment_id ),
        );
    }

    // =========================================================================
    // CONTENT CONVERSION ABILITIES
    // =========================================================================

    private function register_content_abilities() {
        // Convert Markdown to HTML
        wp_register_ability( 'elementor/convert-markdown', array(
            'label' => 'Convert Markdown to HTML',
            'description' => 'Convert Markdown content to HTML for use in WordPress posts.',
            'category' => self::$category,
            'input_schema' => array(
                'type' => 'object',
                'properties' => array(
                    'markdown' => array( 'type' => 'string', 'description' => 'Markdown content to convert.' ),
                ),
                'required' => array( 'markdown' ),
            ),
            'output_schema' => array( 'type' => 'object' ),
            'execute_callback' => function( $input ) {
                $markdown = $input['markdown'];

                // Basic Markdown to HTML conversion
                $html = $markdown;

                // Headers
                $html = preg_replace( '/^###### (.+)$/m', '<h6>$1</h6>', $html );
                $html = preg_replace( '/^##### (.+)$/m', '<h5>$1</h5>', $html );
                $html = preg_replace( '/^#### (.+)$/m', '<h4>$1</h4>', $html );
                $html = preg_replace( '/^### (.+)$/m', '<h3>$1</h3>', $html );
                $html = preg_replace( '/^## (.+)$/m', '<h2>$1</h2>', $html );
                $html = preg_replace( '/^# (.+)$/m', '<h1>$1</h1>', $html );

                // Bold and italic
                $html = preg_replace( '/\*\*\*(.+?)\*\*\*/s', '<strong><em>$1</em></strong>', $html );
                $html = preg_replace( '/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $html );
                $html = preg_replace( '/\*(.+?)\*/s', '<em>$1</em>', $html );

                // Links
                $html = preg_replace( '/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2">$1</a>', $html );

                // Images
                $html = preg_replace( '/!\[([^\]]*)\]\(([^)]+)\)/', '<img src="$2" alt="$1" />', $html );

                // Code blocks
                $html = preg_replace( '/```(\w+)?\n(.*?)```/s', '<pre><code>$2</code></pre>', $html );
                $html = preg_replace( '/`([^`]+)`/', '<code>$1</code>', $html );

                // Unordered lists
                $html = preg_replace( '/^\* (.+)$/m', '<li>$1</li>', $html );
                $html = preg_replace( '/(<li>.*<\/li>)/s', '<ul>$1</ul>', $html );

                // Ordered lists
                $html = preg_replace( '/^\d+\. (.+)$/m', '<li>$1</li>', $html );

                // Blockquotes
                $html = preg_replace( '/^> (.+)$/m', '<blockquote>$1</blockquote>', $html );

                // Horizontal rules
                $html = preg_replace( '/^---$/m', '<hr />', $html );

                // Line breaks
                $html = preg_replace( '/\n\n/', '</p><p>', $html );
                $html = '<p>' . $html . '</p>';
                $html = preg_replace( '/<p><(h[1-6]|ul|ol|pre|blockquote|hr)/', '<$1', $html );
                $html = preg_replace( '/<\/(h[1-6]|ul|ol|pre|blockquote)><\/p>/', '</$1>', $html );

                return array(
                    'html' => $html,
                    'original_length' => strlen( $markdown ),
                    'html_length' => strlen( $html ),
                );
            },
            'permission_callback' => function() { return current_user_can( 'edit_posts' ); },
            'meta' => self::$mcp_meta,
        ) );

        // Create WordPress Post
        wp_register_ability( 'elementor/create-post', array(
            'label' => 'Create WordPress Post',
            'description' => 'Create a complete WordPress post with title, content, categories, tags, and SEO metadata.',
            'category' => self::$category,
            'input_schema' => array(
                'type' => 'object',
                'properties' => array(
                    'title' => array( 'type' => 'string', 'description' => 'Post title.' ),
                    'content' => array( 'type' => 'string', 'description' => 'Post content (HTML or Markdown).' ),
                    'content_type' => array( 'type' => 'string', 'description' => 'Content format: html or markdown. Default: html.', 'default' => 'html' ),
                    'status' => array( 'type' => 'string', 'description' => 'Post status: draft, publish, pending. Default: draft.', 'default' => 'draft' ),
                    'excerpt' => array( 'type' => 'string', 'description' => 'Post excerpt.' ),
                    'categories' => array( 'type' => 'array', 'description' => 'Category IDs.' ),
                    'tags' => array( 'type' => 'array', 'description' => 'Tag IDs.' ),
                    'featured_media' => array( 'type' => 'integer', 'description' => 'Featured image attachment ID.' ),
                    'seo' => array( 'type' => 'object', 'description' => 'SEO metadata (title, meta_description, focus_keyword).' ),
                ),
                'required' => array( 'title', 'content' ),
            ),
            'output_schema' => array( 'type' => 'object' ),
            'execute_callback' => array( $this, 'execute_create_post' ),
            'permission_callback' => function() { return current_user_can( 'publish_posts' ); },
            'meta' => self::$mcp_meta,
        ) );
    }

    public function execute_create_post( $input ) {
        $content = $input['content'];

        // Convert markdown if needed
        if ( isset( $input['content_type'] ) && $input['content_type'] === 'markdown' ) {
            // Use our markdown converter
            $result = call_user_func( array( $this, 'convert_markdown_to_html' ), $content );
            $content = $result;
        }

        $post_data = array(
            'post_title' => sanitize_text_field( $input['title'] ),
            'post_content' => wp_kses_post( $content ),
            'post_status' => isset( $input['status'] ) ? sanitize_key( $input['status'] ) : 'draft',
            'post_type' => 'post',
        );

        if ( isset( $input['excerpt'] ) ) {
            $post_data['post_excerpt'] = sanitize_textarea_field( $input['excerpt'] );
        }
        if ( isset( $input['categories'] ) && is_array( $input['categories'] ) ) {
            $post_data['post_category'] = array_map( 'absint', $input['categories'] );
        }
        if ( isset( $input['tags'] ) && is_array( $input['tags'] ) ) {
            $post_data['tags_input'] = array_map( 'absint', $input['tags'] );
        }

        $post_id = wp_insert_post( $post_data, true );
        if ( is_wp_error( $post_id ) ) {
            return $post_id;
        }

        // Set featured image
        if ( isset( $input['featured_media'] ) ) {
            set_post_thumbnail( $post_id, absint( $input['featured_media'] ) );
        }

        // Set SEO metadata
        if ( isset( $input['seo'] ) && is_array( $input['seo'] ) ) {
            $seo = $input['seo'];
            if ( isset( $seo['title'] ) ) {
                update_post_meta( $post_id, '_yoast_wpseo_title', sanitize_text_field( $seo['title'] ) );
            }
            if ( isset( $seo['meta_description'] ) ) {
                update_post_meta( $post_id, '_yoast_wpseo_metadesc', sanitize_textarea_field( $seo['meta_description'] ) );
            }
            if ( isset( $seo['focus_keyword'] ) ) {
                update_post_meta( $post_id, '_yoast_wpseo_focuskw', sanitize_text_field( $seo['focus_keyword'] ) );
            }
        }

        return array(
            'success' => true,
            'post_id' => $post_id,
            'link' => get_permalink( $post_id ),
            'edit_url' => get_edit_post_link( $post_id, 'raw' ),
        );
    }

    private function convert_markdown_to_html( $markdown ) {
        $html = $markdown;

        // Headers
        $html = preg_replace( '/^###### (.+)$/m', '<h6>$1</h6>', $html );
        $html = preg_replace( '/^##### (.+)$/m', '<h5>$1</h5>', $html );
        $html = preg_replace( '/^#### (.+)$/m', '<h4>$1</h4>', $html );
        $html = preg_replace( '/^### (.+)$/m', '<h3>$1</h3>', $html );
        $html = preg_replace( '/^## (.+)$/m', '<h2>$1</h2>', $html );
        $html = preg_replace( '/^# (.+)$/m', '<h1>$1</h1>', $html );

        // Bold and italic
        $html = preg_replace( '/\*\*\*(.+?)\*\*\*/s', '<strong><em>$1</em></strong>', $html );
        $html = preg_replace( '/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $html );
        $html = preg_replace( '/\*(.+?)\*/s', '<em>$1</em>', $html );

        // Links and images
        $html = preg_replace( '/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2">$1</a>', $html );
        $html = preg_replace( '/!\[([^\]]*)\]\(([^)]+)\)/', '<img src="$2" alt="$1" />', $html );

        return $html;
    }
}

Elementor_Abilities_Plugin::instance();
