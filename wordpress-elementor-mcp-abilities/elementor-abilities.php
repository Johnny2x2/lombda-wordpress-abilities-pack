<?php
/**
 * Plugin Name: Elementor Abilities
 * Description: Exposes comprehensive Elementor functionality as WordPress Abilities for AI agents via MCP. Includes document, widget, and control management tools.
 * Version: 2.1.0
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
        $this->register_builder_abilities();
        $this->register_theme_builder_abilities();
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
            'execute_callback' => array( $this, 'execute_get_elementor_data' ),
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
            'execute_callback' => array( $this, 'execute_update_elementor_data' ),
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

        // Get Subtree (full raw element + descendants)
        wp_register_ability( 'elementor/get-subtree', array(
            'label' => 'Get Element Subtree',
            'description' => 'Get the full raw element tree rooted at a given element ID, including every nested descendant with complete settings. Use this when you need to inspect or copy an entire container/section without pulling the whole document. Saves many round-trips vs. repeated find-widget calls. Pass element_id = "root" to return every top-level element in the document.',
            'category' => self::$category,
            'input_schema' => array(
                'type' => 'object',
                'properties' => array(
                    'post_id' => array( 'type' => 'integer', 'description' => 'The ID of the page/post.' ),
                    'element_id' => array( 'type' => 'string', 'description' => 'The root element ID, or "root" to return all top-level elements.' ),
                    'max_depth' => array( 'type' => 'integer', 'description' => 'Optional maximum descendant depth (0 = only this element, no children). Omit for unlimited.' ),
                ),
                'required' => array( 'post_id', 'element_id' ),
            ),
            'output_schema' => array( 'type' => 'object' ),
            'execute_callback' => array( $this, 'execute_get_subtree' ),
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
     * Get the active Elementor plugin instance.
     */
    private function get_elementor_plugin_instance() {
        if ( ! class_exists( 'Elementor\Plugin' ) || ! isset( Elementor\Plugin::$instance ) ) {
            return null;
        }

        return Elementor\Plugin::$instance;
    }

    /**
     * Get an Elementor document when the document manager is available.
     */
    private function get_elementor_document( $post_id, $from_cache = true ) {
        $plugin = $this->get_elementor_plugin_instance();

        if ( ! $plugin || ! isset( $plugin->documents ) || ! method_exists( $plugin->documents, 'get' ) ) {
            return null;
        }

        return $plugin->documents->get( $post_id, $from_cache );
    }

    /**
     * Get the Elementor widgets manager if available.
     */
    private function get_elementor_widgets_manager() {
        $plugin = $this->get_elementor_plugin_instance();

        if ( ! $plugin || ! isset( $plugin->widgets_manager ) ) {
            return null;
        }

        return $plugin->widgets_manager;
    }

    /**
     * Get the Elementor controls manager if available.
     */
    private function get_elementor_controls_manager() {
        $plugin = $this->get_elementor_plugin_instance();

        if ( ! $plugin || ! isset( $plugin->controls_manager ) ) {
            return null;
        }

        return $plugin->controls_manager;
    }

    /**
     * Get the Elementor experiments manager if available.
     */
    private function get_elementor_experiments_manager() {
        $plugin = $this->get_elementor_plugin_instance();

        if ( ! $plugin || ! isset( $plugin->experiments ) ) {
            return null;
        }

        return $plugin->experiments;
    }

    /**
     * Get the best-known Elementor version for the current site.
     */
    private function get_elementor_version( $post_id = 0 ) {
        if ( defined( 'ELEMENTOR_VERSION' ) ) {
            return ELEMENTOR_VERSION;
        }

        if ( $post_id ) {
            $stored_version = get_post_meta( $post_id, '_elementor_version', true );
            if ( is_string( $stored_version ) && '' !== $stored_version ) {
                return $stored_version;
            }
        }

        return '3.0.0';
    }

    /**
     * Report runtime Elementor capabilities that affect document generation.
     */
    private function get_elementor_capabilities() {
        $plugin = $this->get_elementor_plugin_instance();
        $experiments_manager = $this->get_elementor_experiments_manager();
        $container_feature = array();
        $container_feature_active = false;

        if ( $experiments_manager && method_exists( $experiments_manager, 'get_features' ) ) {
            $feature = $experiments_manager->get_features( 'container' );
            if ( is_array( $feature ) ) {
                $container_feature = $feature;
            }
        }

        if ( $experiments_manager && method_exists( $experiments_manager, 'is_feature_active' ) ) {
            $container_feature_active = (bool) $experiments_manager->is_feature_active( 'container' );
        }

        return array(
            'document_api' => (bool) ( $plugin && isset( $plugin->documents ) ),
            'native_save' => (bool) ( $plugin && isset( $plugin->documents ) ),
            'container_feature_defined' => ! empty( $container_feature ),
            'container_feature_active' => $container_feature_active,
            'container_elements' => $container_feature_active,
            'grid_containers' => $container_feature_active,
            'container_feature' => ! empty( $container_feature ) ? array(
                'name' => $container_feature['name'] ?? 'container',
                'title' => $container_feature['title'] ?? 'Container',
                'release_status' => $container_feature['release_status'] ?? null,
                'default' => $container_feature['default'] ?? null,
            ) : null,
            'editor' => $this->get_editor_version_info(),
        );
    }

    /**
     * Detect which Elementor editor generation is available/active on the site.
     *
     * Elementor gates its v4 "Atomic" editor behind a set of experiments. When any
     * of them is active the site is running (or opted-in to) the v4 editor, while
     * the classic v3 editor keeps working for existing content. This helper
     * reports the detection result without forcing a particular mode so callers
     * can branch their payloads accordingly.
     *
     * @return array{
     *     mode: string,                 // "v3" | "v4-available" | "v4-active"
     *     v4_available: bool,           // any v4 experiment is registered
     *     v4_active: bool,              // any v4 experiment is active
     *     v4_experiments: array<string,array{defined:bool,active:bool,release_status:?string}>,
     *     active_v4_experiments: string[],
     * }
     */
    private function get_editor_version_info() {
        $experiments_manager = $this->get_elementor_experiments_manager();

        // Known v4 editor experiment slugs. Elementor has shipped the v4 editor
        // under several flags over releases; we probe all of them so detection
        // keeps working across versions without forcing v4 behaviour.
        $v4_experiment_slugs = array(
            'editor_v2',
            'e_opt_in_v4_page',
            'e_atomic_elements',
            'atomic_widgets',
            'e_v_3_30',
        );

        $experiments = array();
        $any_defined = false;
        $any_active  = false;
        $active_list = array();

        if ( $experiments_manager ) {
            foreach ( $v4_experiment_slugs as $slug ) {
                $defined = false;
                $active  = false;
                $release_status = null;

                if ( method_exists( $experiments_manager, 'get_features' ) ) {
                    $feature = $experiments_manager->get_features( $slug );
                    if ( is_array( $feature ) && ! empty( $feature ) ) {
                        $defined = true;
                        $release_status = $feature['release_status'] ?? null;
                    }
                }

                if ( $defined && method_exists( $experiments_manager, 'is_feature_active' ) ) {
                    $active = (bool) $experiments_manager->is_feature_active( $slug );
                }

                $experiments[ $slug ] = array(
                    'defined'        => $defined,
                    'active'         => $active,
                    'release_status' => $release_status,
                );

                if ( $defined ) {
                    $any_defined = true;
                }
                if ( $active ) {
                    $any_active = true;
                    $active_list[] = $slug;
                }
            }
        }

        if ( $any_active ) {
            $mode = 'v4-active';
        } elseif ( $any_defined ) {
            $mode = 'v4-available';
        } else {
            $mode = 'v3';
        }

        return array(
            'mode'                  => $mode,
            'v4_available'          => $any_defined,
            'v4_active'             => $any_active,
            'v4_experiments'        => $experiments,
            'active_v4_experiments' => $active_list,
        );
    }

    /**
     * Scan a parsed Elementor element tree for v4 atomic markers.
     *
     * v4 atomic widgets/containers use the `e-` prefix on elType/widgetType
     * (e.g. `e-flexbox`, `e-heading`, `e-tabs`). This lets callers check whether
     * a specific document already contains v4 content even when the site-wide
     * experiment is only "available".
     *
     * @param array $elements Parsed Elementor elements array.
     * @return array{has_v4_elements:bool,v4_element_types:string[]}
     */
    private function detect_v4_elements_in_tree( $elements ) {
        $types = array();

        $walker = function( $nodes ) use ( &$walker, &$types ) {
            if ( ! is_array( $nodes ) ) {
                return;
            }
            foreach ( $nodes as $node ) {
                if ( ! is_array( $node ) ) {
                    continue;
                }
                $el_type     = isset( $node['elType'] ) ? (string) $node['elType'] : '';
                $widget_type = isset( $node['widgetType'] ) ? (string) $node['widgetType'] : '';
                foreach ( array( $el_type, $widget_type ) as $candidate ) {
                    if ( '' !== $candidate && 0 === strpos( $candidate, 'e-' ) ) {
                        $types[ $candidate ] = true;
                    }
                }
                if ( isset( $node['elements'] ) && is_array( $node['elements'] ) ) {
                    $walker( $node['elements'] );
                }
            }
        };

        $walker( is_array( $elements ) ? $elements : array() );

        return array(
            'has_v4_elements'  => ! empty( $types ),
            'v4_element_types' => array_keys( $types ),
        );
    }

    /**
     * Whether the current Elementor environment supports container documents.
     */
    private function supports_container_elements() {
        $capabilities = $this->get_elementor_capabilities();

        return ! empty( $capabilities['container_elements'] );
    }

    /**
     * Whether the current Elementor environment supports grid containers.
     */
    private function supports_grid_containers() {
        $capabilities = $this->get_elementor_capabilities();

        return ! empty( $capabilities['grid_containers'] );
    }

    /**
     * Clear Elementor-generated caches when the file manager is available.
     */
    private function clear_elementor_cache() {
        $plugin = $this->get_elementor_plugin_instance();

        if ( ! $plugin || ! isset( $plugin->files_manager ) || ! method_exists( $plugin->files_manager, 'clear_cache' ) ) {
            return false;
        }

        $plugin->files_manager->clear_cache();

        return true;
    }

    /**
     * Read decoded Elementor elements for a document.
     */
    private function get_document_elements( $post_id ) {
        $elementor_data = get_post_meta( $post_id, '_elementor_data', true );

        if ( empty( $elementor_data ) ) {
            return array();
        }

        $elements = json_decode( $elementor_data, true );

        return is_array( $elements ) ? $elements : array();
    }

    /**
     * Read Elementor document settings with a document API fallback.
     */
    private function get_document_settings( $post_id ) {
        $document = $this->get_elementor_document( $post_id );
        if ( $document && method_exists( $document, 'get_settings' ) ) {
            $settings = $document->get_settings();
            if ( is_array( $settings ) ) {
                return $settings;
            }
        }

        $settings = get_post_meta( $post_id, '_elementor_page_settings', true );

        return is_array( $settings ) ? $settings : array();
    }

    /**
     * Merge partial settings updates into the stored document settings.
     */
    private function merge_document_settings( $post_id, $settings ) {
        $existing_settings = $this->get_document_settings( $post_id );

        return array_replace_recursive( $existing_settings, $settings );
    }

    /**
     * Decode raw Elementor JSON into an elements array.
     */
    private function decode_elementor_json( $json_string ) {
        if ( ! is_string( $json_string ) ) {
            return new WP_Error( 'invalid_json', 'Elementor data must be provided as a JSON string.', array( 'status' => 400 ) );
        }

        $elements = json_decode( $json_string, true );
        if ( JSON_ERROR_NONE !== json_last_error() ) {
            return new WP_Error( 'invalid_json', 'Invalid JSON in elementor_data.', array( 'status' => 400 ) );
        }

        if ( ! is_array( $elements ) ) {
            return new WP_Error( 'invalid_elements', 'Elementor data must decode to an array of elements.', array( 'status' => 400 ) );
        }

        return $elements;
    }

    /**
     * Format an element path for validation errors.
     */
    private function format_element_path( $path ) {
        if ( empty( $path ) ) {
            return 'root';
        }

        $segments = array();
        foreach ( $path as $index ) {
            $segments[] = 'elements[' . $index . ']';
        }

        return implode( ' > ', $segments );
    }

    /**
     * Validate an elements array before persisting it.
     */
    private function validate_elements_for_save( $elements ) {
        if ( ! is_array( $elements ) ) {
            return new WP_Error( 'invalid_elements', 'Elements must be an array.', array( 'status' => 400 ) );
        }

        foreach ( $elements as $index => $element ) {
            $validation_result = $this->validate_element_for_save( $element, array( $index ) );
            if ( is_wp_error( $validation_result ) ) {
                return $validation_result;
            }
        }

        return true;
    }

    /**
     * Validate a single Elementor element.
     */
    private function validate_element_for_save( $element, $path = array() ) {
        $element_path = $this->format_element_path( $path );

        if ( ! is_array( $element ) ) {
            return new WP_Error( 'invalid_element', 'Element at ' . $element_path . ' must be an object.', array( 'status' => 400 ) );
        }

        if ( empty( $element['id'] ) || ! is_string( $element['id'] ) ) {
            return new WP_Error( 'invalid_element_id', 'Element at ' . $element_path . ' is missing a valid id.', array( 'status' => 400 ) );
        }

        if ( empty( $element['elType'] ) || ! is_string( $element['elType'] ) ) {
            return new WP_Error( 'invalid_element_type', 'Element at ' . $element_path . ' is missing a valid elType.', array( 'status' => 400 ) );
        }

        if ( isset( $element['settings'] ) && ! is_array( $element['settings'] ) ) {
            return new WP_Error( 'invalid_element_settings', 'Element settings at ' . $element_path . ' must be an object.', array( 'status' => 400 ) );
        }

        if ( isset( $element['elements'] ) && ! is_array( $element['elements'] ) ) {
            return new WP_Error( 'invalid_child_elements', 'Child elements at ' . $element_path . ' must be an array.', array( 'status' => 400 ) );
        }

        $element_type = $element['elType'];
        if ( ! in_array( $element_type, array( 'widget', 'container', 'section', 'column' ), true ) ) {
            return new WP_Error( 'unsupported_element_type', 'Unsupported elType "' . $element_type . '" at ' . $element_path . '.', array( 'status' => 400 ) );
        }

        if ( 'widget' === $element_type ) {
            if ( empty( $element['widgetType'] ) || ! is_string( $element['widgetType'] ) ) {
                return new WP_Error( 'invalid_widget_type', 'Widget at ' . $element_path . ' is missing a valid widgetType.', array( 'status' => 400 ) );
            }

            $widget = $this->get_widget_definition( $element['widgetType'] );
            if ( $this->get_elementor_widgets_manager() && ! $widget ) {
                return new WP_Error( 'widget_not_found', 'Widget type "' . $element['widgetType'] . '" was not found at ' . $element_path . '.', array( 'status' => 400 ) );
            }
        }

        if ( 'container' === $element_type ) {
            if ( ! $this->supports_container_elements() ) {
                return new WP_Error( 'containers_not_supported', 'Container elements are not active in Elementor for ' . $element_path . '.', array( 'status' => 400 ) );
            }

            $container_settings = isset( $element['settings'] ) ? $element['settings'] : array();
            if ( isset( $container_settings['container_type'] ) && ! in_array( $container_settings['container_type'], array( 'flex', 'grid' ), true ) ) {
                return new WP_Error( 'invalid_container_type', 'Container type at ' . $element_path . ' must be flex or grid.', array( 'status' => 400 ) );
            }

            if ( isset( $container_settings['container_type'] ) && 'grid' === $container_settings['container_type'] && ! $this->supports_grid_containers() ) {
                return new WP_Error( 'grid_not_supported', 'Grid containers are not active in Elementor for ' . $element_path . '.', array( 'status' => 400 ) );
            }
        }

        if ( ! empty( $element['elements'] ) ) {
            foreach ( $element['elements'] as $index => $child_element ) {
                $validation_result = $this->validate_element_for_save( $child_element, array_merge( $path, array( $index ) ) );
                if ( is_wp_error( $validation_result ) ) {
                    return $validation_result;
                }
            }
        }

        return true;
    }

    /**
     * Get a registered Elementor widget definition when available.
     */
    private function get_widget_definition( $widget_type ) {
        $widgets_manager = $this->get_elementor_widgets_manager();

        if ( ! $widgets_manager || ! method_exists( $widgets_manager, 'get_widget_types' ) ) {
            return null;
        }

        return $widgets_manager->get_widget_types( $widget_type );
    }

    /**
     * Whether an element can accept child elements.
     */
    private function can_accept_child_elements( $element ) {
        if ( ! is_array( $element ) ) {
            return false;
        }

        return isset( $element['elType'] ) && in_array( $element['elType'], array( 'container', 'section', 'column' ), true );
    }

    /**
     * Normalize requested container layout values.
     */
    private function normalize_container_layout( $layout ) {
        $layout = strtolower( sanitize_text_field( $layout ) );

        if ( in_array( $layout, array( 'grid', 'grid-responsive' ), true ) ) {
            return 'grid';
        }

        return 'flex';
    }

    /**
     * Normalize a numeric or object gap value into Elementor's responsive format.
     */
    private function normalize_gap_setting( $gap ) {
        if ( is_array( $gap ) ) {
            return $gap;
        }

        if ( is_numeric( $gap ) ) {
            return array(
                'unit' => 'px',
                'size' => 0 + $gap,
                'column' => (string) $gap,
                'row' => (string) $gap,
                'isLinked' => true,
            );
        }

        return null;
    }

    /**
     * Build a widget element using the plugin's ID generation conventions.
     */
    private function build_widget_element( $widget_type, $settings = array() ) {
        return array(
            'id' => $this->generate_element_id(),
            'elType' => 'widget',
            'widgetType' => $widget_type,
            'settings' => $settings,
            'elements' => array(),
        );
    }

    /**
     * Build a modern container element.
     */
    private function build_container_element( $settings = array(), $elements = array(), $layout = 'flexbox', $direction = 'column', $is_inner = false ) {
        $layout_mode = $this->normalize_container_layout( $layout );
        $container_settings = is_array( $settings ) ? $settings : array();
        $container_settings['container_type'] = $layout_mode;

        if ( 'grid' === $layout_mode ) {
            unset( $container_settings['flex_direction'] );
        } elseif ( ! isset( $container_settings['flex_direction'] ) ) {
            $container_settings['flex_direction'] = sanitize_text_field( $direction );
        }

        $container = array(
            'id' => $this->generate_element_id(),
            'elType' => 'container',
            'settings' => $container_settings,
            'elements' => is_array( $elements ) ? array_values( $elements ) : array(),
        );

        if ( $is_inner ) {
            $container['isInner'] = true;
        }

        return $container;
    }

    /**
     * Build a legacy section/column wrapper when container support is unavailable.
     */
    private function build_legacy_section_element( $settings = array(), $elements = array() ) {
        return array(
            'id' => $this->generate_element_id(),
            'elType' => 'section',
            'settings' => is_array( $settings ) ? $settings : array(),
            'elements' => array(
                array(
                    'id' => $this->generate_element_id(),
                    'elType' => 'column',
                    'settings' => array( '_column_size' => 100 ),
                    'elements' => is_array( $elements ) ? array_values( $elements ) : array(),
                ),
            ),
        );
    }

    /**
     * Persist Elementor content through the native document API when available.
     */
    private function save_document_content( $post_id, $elements = null, $settings = null ) {
        if ( null !== $elements ) {
            $validation_result = $this->validate_elements_for_save( $elements );
            if ( is_wp_error( $validation_result ) ) {
                return $validation_result;
            }
        }

        if ( null !== $settings && ! is_array( $settings ) ) {
            return new WP_Error( 'invalid_settings', 'Settings must be an object.', array( 'status' => 400 ) );
        }

        update_post_meta( $post_id, '_elementor_edit_mode', 'builder' );

        $document = $this->get_elementor_document( $post_id, false );
        if ( $document && method_exists( $document, 'set_is_built_with_elementor' ) && method_exists( $document, 'save' ) ) {
            $document->set_is_built_with_elementor( true );

            $payload = array();
            if ( null !== $elements ) {
                $payload['elements'] = $elements;
            }
            if ( null !== $settings ) {
                $payload['settings'] = $settings;
            }

            if ( ! empty( $payload ) ) {
                $save_result = $document->save( $payload );
                if ( false === $save_result ) {
                    return new WP_Error( 'document_save_failed', 'Elementor rejected the document save.', array( 'status' => 500 ) );
                }
            }

            update_post_meta( $post_id, '_elementor_version', $this->get_elementor_version( $post_id ) );
            $this->clear_elementor_cache();

            return true;
        }

        if ( null !== $elements ) {
            $json_data = wp_json_encode( $elements );
            update_post_meta( $post_id, '_elementor_data', wp_slash( $json_data ) );
        }

        if ( null !== $settings ) {
            update_post_meta( $post_id, '_elementor_page_settings', $settings );
        }

        update_post_meta( $post_id, '_elementor_version', $this->get_elementor_version( $post_id ) );
        $this->clear_elementor_cache();

        return true;
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
                'isInner' => ! empty( $element['isInner'] ),
                'depth' => $depth,
                'index' => $index,
            );

            if ( isset( $element['settings']['container_type'] ) ) {
                $node['containerType'] = $element['settings']['container_type'];
            }
            
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
        return $this->save_document_content( $post_id, $elements );
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

        $elements = $this->get_document_elements( $post_id );
        
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

        $elements = $this->get_document_elements( $post_id );
        
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

    /**
     * Get the full element tree rooted at a given element ID.
     * Returns every descendant with full settings. If element_id === 'root',
     * the document's top-level elements are returned.
     */
    public function execute_get_subtree( $input ) {
        $post_id    = absint( $input['post_id'] );
        $element_id = isset( $input['element_id'] ) ? sanitize_text_field( $input['element_id'] ) : 'root';
        $max_depth  = array_key_exists( 'max_depth', $input ) ? (int) $input['max_depth'] : null;

        $post = get_post( $post_id );
        if ( ! $post ) {
            return new WP_Error( 'not_found', 'Post not found.', array( 'status' => 404 ) );
        }

        $elements = $this->get_document_elements( $post_id );
        if ( ! is_array( $elements ) ) {
            return new WP_Error( 'no_elements', 'No Elementor elements found.' );
        }

        if ( 'root' === $element_id || '' === $element_id ) {
            $subtree = $this->clone_subtree_at_depth( $elements, $max_depth );
            return array(
                'post_id'       => $post_id,
                'element_id'    => 'root',
                'elements'      => $subtree,
                'element_count' => $this->count_elements( $subtree ),
            );
        }

        $found = $this->find_element_by_id( $elements, $element_id );
        if ( ! $found ) {
            return new WP_Error( 'element_not_found', "Element with ID '$element_id' not found." );
        }

        $element = $this->clone_subtree_at_depth( array( $found['element'] ), $max_depth );
        $element = $element[0] ?? array();

        return array(
            'post_id'       => $post_id,
            'element_id'    => $element_id,
            'element'       => $element,
            'path'          => $found['path'],
            'element_count' => $this->count_elements( array( $element ) ),
        );
    }

    /**
     * Recursively clone elements, optionally trimming nested descendants
     * beyond `$max_depth`. `null` means unlimited depth. `0` means "this
     * element only, no children".
     */
    private function clone_subtree_at_depth( $elements, $max_depth, $current_depth = 0 ) {
        $out = array();
        foreach ( (array) $elements as $el ) {
            if ( ! is_array( $el ) ) {
                continue;
            }
            $copy = $el;
            if ( null !== $max_depth && $current_depth >= $max_depth ) {
                unset( $copy['elements'] );
            } elseif ( ! empty( $el['elements'] ) && is_array( $el['elements'] ) ) {
                $copy['elements'] = $this->clone_subtree_at_depth( $el['elements'], $max_depth, $current_depth + 1 );
            }
            $out[] = $copy;
        }
        return $out;
    }

    /**
     * Validate widget settings against the widget's registered Elementor
     * controls. Returns a structured report — never fatal, so the caller can
     * still decide to proceed. Used by update-widget / add-widget to surface
     * typos like `facet_name` vs `filter_name`.
     *
     * @param string $widget_type Elementor widget slug (e.g. "heading").
     * @param array  $settings    Settings the caller is trying to apply.
     * @return array {
     *   @type string[] unknown_controls  Keys that don't exist on the widget.
     *   @type string[] suggestions       For each unknown key, the closest known key (if any).
     *   @type bool     schema_available  False when Elementor isn't loaded; validation was skipped.
     * }
     */
    private function validate_widget_settings( $widget_type, $settings ) {
        $report = array(
            'unknown_controls' => array(),
            'suggestions'      => array(),
            'schema_available' => false,
        );

        if ( ! is_array( $settings ) || empty( $settings ) ) {
            return $report;
        }

        $widget = $this->get_widget_definition( $widget_type );
        if ( ! $widget || ! method_exists( $widget, 'get_controls' ) ) {
            return $report; // schema_available stays false.
        }

        $controls = $widget->get_controls();
        if ( ! is_array( $controls ) ) {
            return $report;
        }

        $report['schema_available'] = true;
        $known = array_keys( $controls );
        // Also treat Elementor's reserved/meta keys as known.
        $reserved = array( '_element_id', '_css_classes', '_animation', '_animation_delay', 'custom_css', 'css_classes', '__globals__', '__dynamic__' );
        $known = array_merge( $known, $reserved );

        foreach ( array_keys( $settings ) as $key ) {
            if ( in_array( $key, $known, true ) ) {
                continue;
            }
            // Responsive control variants: heading_size_tablet, padding_mobile, etc.
            $base = preg_replace( '/_(tablet|mobile|tablet_extra|laptop|widescreen|mobile_extra)$/', '', $key );
            if ( $base !== $key && in_array( $base, $known, true ) ) {
                continue;
            }

            $report['unknown_controls'][] = $key;

            // Cheap "did you mean" — Levenshtein against known controls.
            $best_match = null;
            $best_dist  = PHP_INT_MAX;
            foreach ( $known as $candidate ) {
                if ( ! is_string( $candidate ) ) {
                    continue;
                }
                $dist = levenshtein( $key, $candidate );
                if ( $dist < $best_dist && $dist <= max( 2, (int) ( strlen( $key ) / 3 ) ) ) {
                    $best_dist  = $dist;
                    $best_match = $candidate;
                }
            }
            if ( $best_match ) {
                $report['suggestions'][ $key ] = $best_match;
            }
        }

        return $report;
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

        $elements = $this->get_document_elements( $post_id );
        
        if ( ! is_array( $elements ) ) {
            return new WP_Error( 'no_elements', 'No Elementor elements found.' );
        }
        
        $found = $this->find_element_by_id( $elements, $element_id );
        
        if ( ! $found ) {
            return new WP_Error( 'element_not_found', "Element with ID '$element_id' not found." );
        }
        
        // Validate the provided settings against the widget's control schema.
        // Never fatal — just surfaced as warnings so typos get caught early
        // (e.g. `facet_name` when the widget actually uses `filter_name`).
        $widget_type = $found['element']['widgetType'] ?? '';
        $validation  = $widget_type ? $this->validate_widget_settings( $widget_type, $new_settings ) : array(
            'unknown_controls' => array(), 'suggestions' => array(), 'schema_available' => false,
        );

        // Merge settings
        $current_settings = $found['element']['settings'] ?? array();
        $merged_settings = array_replace_recursive( $current_settings, $new_settings );
        
        // Update the element in place
        $found['element']['settings'] = $merged_settings;
        
        // Save
        $save_result = $this->save_elements( $post_id, $elements );
        if ( is_wp_error( $save_result ) ) {
            return $save_result;
        }
        
        return array(
            'success' => true,
            'element_id' => $element_id,
            'updated_settings' => array_keys( $new_settings ),
            'validation' => $validation,
            'warnings' => ! empty( $validation['unknown_controls'] )
                ? array( 'Unknown control keys for widget "' . $widget_type . '": ' . implode( ', ', $validation['unknown_controls'] ) . '. See validation.suggestions.' )
                : array(),
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
        if ( $this->get_elementor_widgets_manager() ) {
            $widget = $this->get_widget_definition( $widget_type );
            if ( ! $widget ) {
                return new WP_Error( 'widget_not_found', "Widget type '$widget_type' not found." );
            }
        }

        $elements = $this->get_document_elements( $post_id );
        
        if ( ! is_array( $elements ) ) {
            $elements = array();
        }
        
        // Create the widget
        $new_widget = $this->build_widget_element( $widget_type, $settings );
        
        if ( $container_id ) {
            // Find container and add widget to it
            $found = $this->find_element_by_id( $elements, $container_id );
            if ( ! $found ) {
                return new WP_Error( 'container_not_found', "Container with ID '$container_id' not found." );
            }

            if ( ! $this->can_accept_child_elements( $found['element'] ) ) {
                return new WP_Error( 'invalid_container', "Element with ID '$container_id' cannot contain widgets." );
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
            // Add to root - wrap widgets in a root layout element.
            if ( $this->supports_container_elements() ) {
                $container = $this->build_container_element( array( 'content_width' => 'boxed' ), array( $new_widget ) );
            } else {
                $container = $this->build_legacy_section_element( array(), array( $new_widget ) );
            }
            
            if ( $position >= 0 && $position < count( $elements ) ) {
                array_splice( $elements, $position, 0, array( $container ) );
            } else {
                $elements[] = $container;
            }
        }
        
        $save_result = $this->save_elements( $post_id, $elements );
        if ( is_wp_error( $save_result ) ) {
            return $save_result;
        }
        
        // Validate settings shape against the widget's registered controls.
        $validation = $this->validate_widget_settings( $widget_type, $settings );

        return array(
            'success' => true,
            'widget_id' => $new_widget['id'],
            'widget_type' => $widget_type,
            'container_id' => $container_id ?? 'new_container',
            'validation' => $validation,
            'warnings' => ! empty( $validation['unknown_controls'] )
                ? array( 'Unknown control keys for widget "' . $widget_type . '": ' . implode( ', ', $validation['unknown_controls'] ) . '. See validation.suggestions.' )
                : array(),
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

        $elements = $this->get_document_elements( $post_id );
        
        if ( ! is_array( $elements ) ) {
            return new WP_Error( 'no_elements', 'No Elementor elements found.' );
        }
        
        // Find and remove the element
        $removed = $this->remove_element_by_id( $elements, $element_id );
        
        if ( ! $removed ) {
            return new WP_Error( 'element_not_found', "Element with ID '$element_id' not found." );
        }
        
        $save_result = $this->save_elements( $post_id, $elements );
        if ( is_wp_error( $save_result ) ) {
            return $save_result;
        }
        
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

        $elements = $this->get_document_elements( $post_id );
        
        if ( ! is_array( $elements ) ) {
            return new WP_Error( 'no_elements', 'No Elementor elements found.' );
        }
        
        // Find element and its parent
        $result = $this->duplicate_element_by_id( $elements, $element_id );
        
        if ( ! $result ) {
            return new WP_Error( 'element_not_found', "Element with ID '$element_id' not found." );
        }
        
        $save_result = $this->save_elements( $post_id, $elements );
        if ( is_wp_error( $save_result ) ) {
            return $save_result;
        }
        
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

        $elements = $this->get_document_elements( $post_id );
        
        if ( ! is_array( $elements ) ) {
            return new WP_Error( 'no_elements', 'No Elementor elements found.' );
        }
        
        // Find and extract the element
        $found = $this->find_element_by_id( $elements, $element_id );
        if ( ! $found ) {
            return new WP_Error( 'element_not_found', "Element with ID '$element_id' not found." );
        }
        
        $element_to_move = $found['element'];

        if ( 'root' !== $target_container_id ) {
            $target = $this->find_element_by_id( $elements, $target_container_id );
            if ( ! $target ) {
                return new WP_Error( 'target_not_found', "Target container with ID '$target_container_id' not found." );
            }

            if ( ! $this->can_accept_child_elements( $target['element'] ) ) {
                return new WP_Error( 'invalid_target', "Target element with ID '$target_container_id' cannot contain child elements." );
            }

            if ( count( $target['path'] ) > count( $found['path'] ) && $found['path'] === array_slice( $target['path'], 0, count( $found['path'] ) ) ) {
                return new WP_Error( 'invalid_target', 'Cannot move an element into one of its own descendants.' );
            }
        }
        
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

            if ( ! $this->can_accept_child_elements( $target['element'] ) ) {
                return new WP_Error( 'invalid_target', "Target element with ID '$target_container_id' cannot contain child elements." );
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
        
        $save_result = $this->save_elements( $post_id, $elements );
        if ( is_wp_error( $save_result ) ) {
            return $save_result;
        }
        
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

        $elements = $this->get_document_elements( $post_id );
        
        if ( ! is_array( $elements ) ) {
            $elements = array();
        }
        
        // Build child elements from widgets
        $child_elements = array();
        foreach ( $widgets as $widget_data ) {
            if ( isset( $widget_data['widget_type'] ) ) {
                $widget_type = sanitize_text_field( $widget_data['widget_type'] );
                if ( $this->get_elementor_widgets_manager() && ! $this->get_widget_definition( $widget_type ) ) {
                    return new WP_Error( 'widget_not_found', "Widget type '$widget_type' not found." );
                }

                $child_elements[] = $this->build_widget_element(
                    $widget_type,
                    isset( $widget_data['settings'] ) && is_array( $widget_data['settings'] ) ? $widget_data['settings'] : array()
                );
            }
        }

        $section_settings = array_merge( array( 'content_width' => $layout ), $settings );

        if ( 'section' === strtolower( $section_type ) || ! $this->supports_container_elements() ) {
            $new_section = $this->build_legacy_section_element( $section_settings, $child_elements );
        } else {
            $new_section = $this->build_container_element( $section_settings, $child_elements, 'flexbox', 'column', ! empty( $parent_id ) );
        }
        
        // Add to parent or root
        if ( $parent_id ) {
            $parent = $this->find_element_by_id( $elements, $parent_id );
            if ( ! $parent ) {
                return new WP_Error( 'parent_not_found', "Parent container with ID '$parent_id' not found." );
            }

            if ( ! $this->can_accept_child_elements( $parent['element'] ) ) {
                return new WP_Error( 'invalid_parent', "Parent element with ID '$parent_id' cannot contain child elements." );
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
        
        $save_result = $this->save_elements( $post_id, $elements );
        if ( is_wp_error( $save_result ) ) {
            return $save_result;
        }
        
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

        $elements = $this->get_document_elements( $post_id );
        
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
                $found['element']['settings'] = array_replace_recursive( $current_settings, $new_settings );
                $results['updated'][] = $element_id;
            } else {
                $results['failed'][] = $element_id;
            }
        }
        
        $save_result = $this->save_elements( $post_id, $elements );
        if ( is_wp_error( $save_result ) ) {
            return $save_result;
        }
        
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

        $elements = $this->get_document_elements( $post_id );
        
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

    public function execute_get_elementor_data( $input ) {
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
    }

    public function execute_update_elementor_data( $input ) {
        $post_id = absint( $input['post_id'] );
        $post = get_post( $post_id );
        if ( ! $post ) {
            return new WP_Error( 'not_found', 'Post not found.', array( 'status' => 404 ) );
        }

        $elements = $this->decode_elementor_json( $input['elementor_data'] );
        if ( is_wp_error( $elements ) ) {
            return $elements;
        }

        $save_result = $this->save_document_content( $post_id, $elements );
        if ( is_wp_error( $save_result ) ) {
            return $save_result;
        }

        return array( 'success' => true, 'post_id' => $post_id );
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

        // Set template if specified
        if ( $template ) {
            update_post_meta( $post_id, '_wp_page_template', $template );
        }

        $save_result = $this->save_document_content( $post_id, array() );
        if ( is_wp_error( $save_result ) ) {
            return $save_result;
        }

        $document = $this->get_elementor_document( $post_id );

        $edit_url = $document && method_exists( $document, 'get_edit_url' )
            ? $document->get_edit_url()
            : admin_url( 'post.php?post=' . $post_id . '&action=elementor' );

        $preview_url = $document && method_exists( $document, 'get_preview_url' )
            ? $document->get_preview_url()
            : get_preview_post_link( $post_id );

        return array(
            'post_id' => $post_id,
            'edit_url' => $edit_url,
            'preview_url' => $preview_url,
        );
    }

    public function execute_get_document( $input ) {
        $post_id = absint( $input['post_id'] );
        $post = get_post( $post_id );
        if ( ! $post ) {
            return new WP_Error( 'not_found', 'Post not found.', array( 'status' => 404 ) );
        }

        $elements = $this->get_document_elements( $post_id );
        $settings = $this->get_document_settings( $post_id );
        $document = $this->get_elementor_document( $post_id );

        $edit_url = $document && method_exists( $document, 'get_edit_url' )
            ? $document->get_edit_url()
            : admin_url( 'post.php?post=' . $post_id . '&action=elementor' );

        $preview_url = $document && method_exists( $document, 'get_preview_url' )
            ? $document->get_preview_url()
            : get_preview_post_link( $post_id );

        return array(
            'post_id' => $post_id,
            'post_title' => $post->post_title,
            'post_type' => $post->post_type,
            'post_status' => $post->post_status,
            'elements' => is_array( $elements ) ? $elements : array(),
            'settings' => $settings,
            'edit_url' => $edit_url,
            'preview_url' => $preview_url,
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

        $settings = null;
        if ( isset( $input['settings'] ) ) {
            if ( ! is_array( $input['settings'] ) ) {
                return new WP_Error( 'invalid_settings', 'Settings must be an object.' );
            }

            $settings = $this->merge_document_settings( $post_id, $input['settings'] );
        }

        $save_result = $this->save_document_content( $post_id, $elements, $settings );
        if ( is_wp_error( $save_result ) ) {
            return $save_result;
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
                    'layout' => array( 'type' => 'string', 'description' => 'Layout type: flexbox, flex, or grid. Default: flexbox.', 'default' => 'flexbox' ),
                    'direction' => array( 'type' => 'string', 'description' => 'Flex direction: row, column. Default: column.', 'default' => 'column' ),
                    'elements' => array( 'type' => 'array', 'description' => 'Child elements (widgets or nested containers).' ),
                    'settings' => array( 'type' => 'object', 'description' => 'Container settings (gap, padding, etc.).' ),
                    'columns' => array( 'type' => 'integer', 'description' => 'Optional grid column count when layout=grid.' ),
                    'rows' => array( 'type' => 'integer', 'description' => 'Optional grid row count when layout=grid.' ),
                    'gap' => array( 'description' => 'Optional gap value. Accepts a number or a responsive Elementor gap object.' ),
                    'is_inner' => array( 'type' => 'boolean', 'description' => 'Mark this as an inner container. Default: false.', 'default' => false ),
                ),
            ),
            'output_schema' => array( 'type' => 'object' ),
            'execute_callback' => array( $this, 'execute_create_container' ),
            'permission_callback' => function() { return current_user_can( 'edit_posts' ); },
            'meta' => self::$mcp_meta,
        ) );
    }

    public function execute_list_widgets( $input ) {
        $widgets_manager = $this->get_elementor_widgets_manager();
        if ( ! $widgets_manager || ! method_exists( $widgets_manager, 'get_widget_types' ) ) {
            return new WP_Error( 'elementor_not_active', 'Elementor is not active.' );
        }
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
        $widgets_manager = $this->get_elementor_widgets_manager();
        if ( ! $widgets_manager ) {
            return new WP_Error( 'elementor_not_active', 'Elementor is not active.' );
        }

        $widget_name = sanitize_text_field( $input['widget_name'] );
        $include_common = isset( $input['include_common_controls'] ) ? (bool) $input['include_common_controls'] : true;

        $widget = $widgets_manager->get_widget_types( $widget_name );
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
        if ( ! $this->get_elementor_widgets_manager() ) {
            return new WP_Error( 'elementor_not_active', 'Elementor is not active.' );
        }

        $widget_type = sanitize_text_field( $input['widget_type'] );
        $settings = isset( $input['settings'] ) && is_array( $input['settings'] ) ? $input['settings'] : array();

        $widget = $this->get_widget_definition( $widget_type );
        if ( ! $widget ) {
            return new WP_Error( 'widget_not_found', "Widget '$widget_type' not found." );
        }

        $widget_instance = $this->build_widget_element( $widget_type, $settings );

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
        $widgets_manager = $this->get_elementor_widgets_manager();
        if ( ! $widgets_manager ) {
            return new WP_Error( 'elementor_not_active', 'Elementor is not active.' );
        }

        $widget_name = sanitize_text_field( $input['widget_name'] );
        $control_name = isset( $input['control_name'] ) ? sanitize_text_field( $input['control_name'] ) : '';
        $include_common = isset( $input['include_common'] ) ? (bool) $input['include_common'] : true;
        $tab_filter = isset( $input['tab'] ) ? sanitize_text_field( $input['tab'] ) : '';

        $widget = $widgets_manager->get_widget_types( $widget_name );
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
        $is_inner = isset( $input['is_inner'] ) ? (bool) $input['is_inner'] : false;
        $layout_mode = $this->normalize_container_layout( $layout );

        if ( ! $this->supports_container_elements() ) {
            return new WP_Error( 'containers_not_supported', 'Container elements are not active in the current Elementor installation.' );
        }

        if ( 'grid' === $layout_mode && ! $this->supports_grid_containers() ) {
            return new WP_Error( 'grid_not_supported', 'Grid containers are not active in the current Elementor installation.' );
        }

        if ( 'grid' === $layout_mode ) {
            if ( isset( $input['columns'] ) && ! isset( $settings['grid_columns_grid'] ) ) {
                $settings['grid_columns_grid'] = absint( $input['columns'] );
            }

            if ( isset( $input['rows'] ) && ! isset( $settings['grid_rows_grid'] ) ) {
                $settings['grid_rows_grid'] = absint( $input['rows'] );
            }
        }

        if ( isset( $input['gap'] ) ) {
            $gap_setting = $this->normalize_gap_setting( $input['gap'] );
            if ( $gap_setting ) {
                if ( 'grid' === $layout_mode ) {
                    $settings['grid_gaps'] = $gap_setting;
                } else {
                    $settings['flex_gap'] = $gap_setting;
                }
            }
        }

        $container = $this->build_container_element( $settings, $elements, $layout_mode, $direction, $is_inner );

        return array(
            'element' => $container,
            'capabilities' => $this->get_elementor_capabilities(),
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
        $controls_manager = $this->get_elementor_controls_manager();
        if ( ! $controls_manager || ! method_exists( $controls_manager, 'get_controls' ) ) {
            return new WP_Error( 'elementor_not_active', 'Elementor is not active.' );
        }

        $include_ui = isset( $input['include_ui_controls'] ) ? (bool) $input['include_ui_controls'] : true;
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
        $controls_manager = $this->get_elementor_controls_manager();
        if ( ! $controls_manager || ! method_exists( $controls_manager, 'get_control' ) ) {
            return new WP_Error( 'elementor_not_active', 'Elementor is not active.' );
        }

        $control_type = sanitize_text_field( $input['control_type'] );
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
                
                $elements = $this->get_document_elements( $template_id );
                $page_settings = $this->get_document_settings( $template_id );
                $document = $this->get_elementor_document( $template_id );
                
                return array(
                    'id' => $template_id,
                    'title' => $post->post_title,
                    'template_type' => get_post_meta( $template_id, '_elementor_template_type', true ),
                    'elements' => $elements,
                    'page_settings' => $page_settings ? $page_settings : array(),
                    'status' => $post->post_status,
                    'edit_url' => $document && method_exists( $document, 'get_edit_url' )
                        ? $document->get_edit_url()
                        : admin_url( 'post.php?post=' . $template_id . '&action=elementor' ),
                    'preview_url' => $document && method_exists( $document, 'get_preview_url' )
                        ? $document->get_preview_url()
                        : get_preview_post_link( $template_id ),
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

                // Copy the Elementor-specific template metadata.
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
                $this->clear_elementor_cache();
                
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
                
                $elements = $this->get_document_elements( $template_id );
                $page_settings = $this->get_document_settings( $template_id );
                $template_type = get_post_meta( $template_id, '_elementor_template_type', true );
                
                $export_data = array(
                    'title' => $post->post_title,
                    'template_type' => $template_type ? $template_type : 'section',
                    'content' => $elements,
                    'page_settings' => $page_settings ? $page_settings : array(),
                    'version' => $this->get_elementor_version( $template_id ),
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
        } elseif ( isset( $input['elementor_data'] ) && is_string( $input['elementor_data'] ) ) {
            $elements = $this->decode_elementor_json( $input['elementor_data'] );
            if ( is_wp_error( $elements ) ) {
                return $elements;
            }
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
        
        // Set template metadata before save so Elementor reads the correct document type.
        update_post_meta( $post_id, '_elementor_template_type', $template_type );
        update_post_meta( $post_id, '_elementor_edit_mode', 'builder' );
        
        // Set page settings if provided
        $page_settings = null;
        if ( isset( $input['page_settings'] ) && is_array( $input['page_settings'] ) ) {
            $page_settings = $input['page_settings'];
        }

        $save_result = $this->save_document_content( $post_id, $elements, $page_settings );
        if ( is_wp_error( $save_result ) ) {
            return $save_result;
        }
        
        // Set the template type taxonomy term (used by Elementor for categorization)
        wp_set_object_terms( $post_id, $template_type, 'elementor_library_type' );

        $document = $this->get_elementor_document( $post_id );
        
        return array(
            'success' => true,
            'template_id' => $post_id,
            'title' => $title,
            'template_type' => $template_type,
            'edit_url' => $document && method_exists( $document, 'get_edit_url' )
                ? $document->get_edit_url()
                : admin_url( 'post.php?post=' . $post_id . '&action=elementor' ),
            'preview_url' => $document && method_exists( $document, 'get_preview_url' )
                ? $document->get_preview_url()
                : get_preview_post_link( $post_id ),
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
        
        $elements = null;
        if ( isset( $input['elements'] ) && is_array( $input['elements'] ) ) {
            $elements = $input['elements'];
        } elseif ( isset( $input['elementor_data'] ) && is_string( $input['elementor_data'] ) ) {
            $elements = $this->decode_elementor_json( $input['elementor_data'] );
            if ( is_wp_error( $elements ) ) {
                return $elements;
            }
        }
        
        // Update page settings if provided
        $page_settings = null;
        if ( isset( $input['page_settings'] ) && is_array( $input['page_settings'] ) ) {
            $page_settings = $this->merge_document_settings( $template_id, $input['page_settings'] );
        }

        if ( null !== $elements || null !== $page_settings ) {
            $save_result = $this->save_document_content( $template_id, $elements, $page_settings );
            if ( is_wp_error( $save_result ) ) {
                return $save_result;
            }
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
                if ( ! $this->get_elementor_plugin_instance() ) {
                    return new WP_Error( 'elementor_not_active', 'Elementor is not active.' );
                }

                return array( 'success' => $this->clear_elementor_cache() );
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
                $plugin = $this->get_elementor_plugin_instance();
                if ( ! $plugin ) {
                    return new WP_Error( 'elementor_not_active', 'Elementor is not active.' );
                }

                $info = array(
                    'version' => $this->get_elementor_version(),
                    'is_pro' => defined( 'ELEMENTOR_PRO_VERSION' ),
                    'pro_version' => defined( 'ELEMENTOR_PRO_VERSION' ) ? ELEMENTOR_PRO_VERSION : null,
                    'capabilities' => $this->get_elementor_capabilities(),
                );

                if ( isset( $input['include_widgets'] ) && $input['include_widgets'] ) {
                    $widgets_manager = $this->get_elementor_widgets_manager();
                    if ( $widgets_manager && method_exists( $widgets_manager, 'get_widget_types' ) ) {
                        $widgets = $widgets_manager->get_widget_types();
                        $info['widgets'] = array(
                            'count' => count( $widgets ),
                            'names' => array_keys( $widgets ),
                        );
                    }
                }

                if ( isset( $input['include_settings'] ) && $input['include_settings'] ) {
                    $info['settings'] = array(
                        'css_print_method' => get_option( 'elementor_css_print_method', 'external' ),
                        'disable_color_schemes' => get_option( 'elementor_disable_color_schemes' ),
                        'disable_typography_schemes' => get_option( 'elementor_disable_typography_schemes' ),
                    );

                    $experiments_manager = $this->get_elementor_experiments_manager();
                    if ( $experiments_manager && method_exists( $experiments_manager, 'get_active_features' ) ) {
                        $active_features = $experiments_manager->get_active_features();
                        $info['experiments'] = array(
                            'active' => array_keys( $active_features ),
                        );
                    }
                }

                return $info;
            },
            'permission_callback' => function() { return current_user_can( 'edit_posts' ); },
            'meta' => self::$mcp_meta,
        ) );

        // Get Editor Version - detect v3 vs v4 editor without forcing either
        wp_register_ability( 'elementor/get-editor-version', array(
            'label' => 'Get Elementor Editor Version',
            'description' => 'Detects whether the site is running the classic Elementor v3 editor or the newer v4 Atomic editor. Reports the current mode, which v4 experiments are registered, which are active, and (optionally) whether a specific post already contains v4 atomic elements. Useful for choosing the right payload shape before calling create/update abilities without forcing v4 on sites that have not opted in.',
            'category' => self::$category,
            'input_schema' => array(
                'type' => 'object',
                'properties' => array(
                    'post_id' => array( 'type' => 'integer', 'description' => 'Optional post/page ID. When provided, the response also reports whether this document already contains v4 atomic elements.' ),
                ),
            ),
            'output_schema' => array(
                'type' => 'object',
                'properties' => array(
                    'mode' => array( 'type' => 'string', 'description' => 'One of "v3", "v4-available", or "v4-active".' ),
                    'v4_available' => array( 'type' => 'boolean' ),
                    'v4_active' => array( 'type' => 'boolean' ),
                    'active_v4_experiments' => array( 'type' => 'array' ),
                    'v4_experiments' => array( 'type' => 'object' ),
                    'elementor_version' => array( 'type' => 'string' ),
                    'container_feature_active' => array( 'type' => 'boolean' ),
                    'post' => array( 'type' => 'object', 'description' => 'Present when post_id is supplied.' ),
                ),
            ),
            'execute_callback' => function( $input ) {
                if ( ! $this->get_elementor_plugin_instance() ) {
                    return new WP_Error( 'elementor_not_active', 'Elementor is not active.' );
                }

                $capabilities = $this->get_elementor_capabilities();
                $editor       = $capabilities['editor'];

                $response = array(
                    'mode'                     => $editor['mode'],
                    'v4_available'             => $editor['v4_available'],
                    'v4_active'                => $editor['v4_active'],
                    'active_v4_experiments'    => $editor['active_v4_experiments'],
                    'v4_experiments'           => $editor['v4_experiments'],
                    'elementor_version'        => $this->get_elementor_version(),
                    'container_feature_active' => ! empty( $capabilities['container_feature_active'] ),
                );

                if ( isset( $input['post_id'] ) && (int) $input['post_id'] > 0 ) {
                    $post_id  = (int) $input['post_id'];
                    $elements = $this->get_document_elements( $post_id );

                    if ( is_wp_error( $elements ) ) {
                        $response['post'] = array(
                            'post_id' => $post_id,
                            'error'   => $elements->get_error_message(),
                        );
                    } else {
                        $scan = $this->detect_v4_elements_in_tree( $elements );
                        $response['post'] = array(
                            'post_id'           => $post_id,
                            'stored_version'    => get_post_meta( $post_id, '_elementor_version', true ) ?: null,
                            'edit_mode'         => get_post_meta( $post_id, '_elementor_edit_mode', true ) ?: null,
                            'has_v4_elements'   => $scan['has_v4_elements'],
                            'v4_element_types'  => $scan['v4_element_types'],
                        );
                    }
                }

                return $response;
            },
            'permission_callback' => function() { return current_user_can( 'edit_posts' ); },
            'meta' => self::$mcp_meta,
        ) );
    }

    // =========================================================================
    // BUILDER ABILITIES (High-level orchestration endpoints)
    // =========================================================================

    private function register_builder_abilities() {

        // Get Container Type
        wp_register_ability( 'elementor/get-container-type', array(
            'label'       => 'Get Container Type',
            'description' => 'Detects whether the site uses modern Flexbox/Grid Containers or legacy Sections/Columns. Use this before generating page blueprints to ensure the correct element structure.',
            'category'    => self::$category,
            'input_schema' => array(
                'type'       => 'object',
                'properties' => array(),
            ),
            'output_schema' => array(
                'type'       => 'object',
                'properties' => array(
                    'container_type'   => array( 'type' => 'string', 'description' => 'Active container type: "flexbox", "grid", or "legacy".' ),
                    'supports_flexbox' => array( 'type' => 'boolean' ),
                    'supports_grid'    => array( 'type' => 'boolean' ),
                    'recommended_root_element' => array( 'type' => 'string', 'description' => 'The elType to use for root-level layout elements.' ),
                    'capabilities'     => array( 'type' => 'object' ),
                ),
            ),
            'execute_callback'    => array( $this, 'execute_get_container_type' ),
            'permission_callback' => function () { return current_user_can( 'edit_posts' ); },
            'meta'                => self::$mcp_meta,
        ) );

        // Get Page Summary
        wp_register_ability( 'elementor/get-page-summary', array(
            'label'       => 'Get Page Summary',
            'description' => 'Returns an AI-friendly summary of a page: section count, widget types used, text content preview, structure depth, and URLs. Much lighter than get-document — use this when you need context to make editing decisions without parsing the full element tree.',
            'category'    => self::$category,
            'input_schema' => array(
                'type'       => 'object',
                'properties' => array(
                    'post_id' => array( 'type' => 'integer', 'description' => 'The ID of the page/post.' ),
                ),
                'required' => array( 'post_id' ),
            ),
            'output_schema' => array(
                'type'       => 'object',
                'properties' => array(
                    'post_id'          => array( 'type' => 'integer' ),
                    'post_title'       => array( 'type' => 'string' ),
                    'post_status'      => array( 'type' => 'string' ),
                    'permalink'        => array( 'type' => 'string' ),
                    'edit_url'         => array( 'type' => 'string' ),
                    'total_elements'   => array( 'type' => 'integer' ),
                    'root_sections'    => array( 'type' => 'integer' ),
                    'max_depth'        => array( 'type' => 'integer' ),
                    'widget_summary'   => array( 'type' => 'object', 'description' => 'Map of widget_type => count.' ),
                    'sections'         => array( 'type' => 'array', 'description' => 'Summary of each root-level section.' ),
                    'container_type'   => array( 'type' => 'string' ),
                ),
            ),
            'execute_callback'    => array( $this, 'execute_get_page_summary' ),
            'permission_callback' => function () { return current_user_can( 'edit_posts' ); },
            'meta'                => self::$mcp_meta,
        ) );

        // Apply Blueprint
        wp_register_ability( 'elementor/apply-blueprint', array(
            'label'       => 'Apply Page Blueprint',
            'description' => 'Build or replace an entire page from a blueprint definition in ONE call. Accepts a full array of Elementor elements (containers/sections with nested widgets and settings). Replaces all existing content. Uses the native Elementor document save API for proper lifecycle handling. Auto-detects container type if not specified.',
            'category'    => self::$category,
            'input_schema' => array(
                'type'       => 'object',
                'properties' => array(
                    'post_id'  => array( 'type' => 'integer', 'description' => 'The ID of the page/post to apply the blueprint to.' ),
                    'elements' => array( 'type' => 'array', 'description' => 'Full array of Elementor elements. Each root element should be a container (or section for legacy). Nested widgets go inside.' ),
                    'settings' => array( 'type' => 'object', 'description' => 'Optional document-level page settings.' ),
                    'auto_wrap_widgets' => array( 'type' => 'boolean', 'description' => 'If true, any root-level widgets without a container wrapper will be auto-wrapped in the correct container type. Default: true.', 'default' => true ),
                    'generate_ids' => array( 'type' => 'boolean', 'description' => 'If true, generates new unique IDs for all elements (useful when pasting from templates). Default: false.', 'default' => false ),
                ),
                'required' => array( 'post_id', 'elements' ),
            ),
            'output_schema' => array(
                'type'       => 'object',
                'properties' => array(
                    'success'        => array( 'type' => 'boolean' ),
                    'post_id'        => array( 'type' => 'integer' ),
                    'element_count'  => array( 'type' => 'integer' ),
                    'root_sections'  => array( 'type' => 'integer' ),
                    'preview_url'    => array( 'type' => 'string' ),
                    'edit_url'       => array( 'type' => 'string' ),
                ),
            ),
            'execute_callback'    => array( $this, 'execute_apply_blueprint' ),
            'permission_callback' => function () { return current_user_can( 'edit_posts' ); },
            'meta'                => self::$mcp_meta,
        ) );

        // Apply Batch
        wp_register_ability( 'elementor/apply-batch', array(
            'label'       => 'Apply Batch Operations',
            'description' => 'Apply multiple element operations atomically in a single call. All operations succeed or the entire batch is rolled back. Supports: add, update, remove, move, duplicate, and replace operations.',
            'category'    => self::$category,
            'input_schema' => array(
                'type'       => 'object',
                'properties' => array(
                    'post_id'    => array( 'type' => 'integer', 'description' => 'The ID of the page/post.' ),
                    'operations' => array(
                        'type'        => 'array',
                        'description' => 'Array of operations to apply in order.',
                        'items'       => array(
                            'type'       => 'object',
                            'properties' => array(
                                'op'                  => array( 'type' => 'string', 'description' => 'Operation type: add, update, remove, move, duplicate, replace.' ),
                                'element_id'          => array( 'type' => 'string', 'description' => 'Target element ID (for update, remove, move, duplicate, replace).' ),
                                'container_id'        => array( 'type' => 'string', 'description' => 'Container to add into (for add operation). Omit for root.' ),
                                'widget_type'         => array( 'type' => 'string', 'description' => 'Widget type (for add operation).' ),
                                'settings'            => array( 'type' => 'object', 'description' => 'Settings to apply (for add, update operations).' ),
                                'position'            => array( 'type' => 'integer', 'description' => 'Position index (for add, move operations).' ),
                                'target_container_id' => array( 'type' => 'string', 'description' => 'Target container (for move operation). Use "root" for page root.' ),
                                'element'             => array( 'type' => 'object', 'description' => 'Full element definition (for replace operation or add with full element).' ),
                            ),
                            'required' => array( 'op' ),
                        ),
                    ),
                ),
                'required' => array( 'post_id', 'operations' ),
            ),
            'output_schema' => array(
                'type'       => 'object',
                'properties' => array(
                    'success'          => array( 'type' => 'boolean' ),
                    'operations_count' => array( 'type' => 'integer' ),
                    'results'          => array( 'type' => 'array' ),
                    'affected_ids'     => array( 'type' => 'array' ),
                    'element_count'    => array( 'type' => 'integer' ),
                ),
            ),
            'execute_callback'    => array( $this, 'execute_apply_batch' ),
            'permission_callback' => function () { return current_user_can( 'edit_posts' ); },
            'meta'                => self::$mcp_meta,
        ) );
    }

    // =========================================================================
    // THEME BUILDER CONDITION ABILITIES (Elementor Pro)
    //
    // Theme Builder templates (archives, singles, headers, footers) are
    // assigned to content by storing a flat list of condition strings in
    // the `_elementor_conditions` post meta on each template post. Elementor
    // Pro then compiles those into the `elementor_pro_theme_builder_conditions`
    // option (a nested `[location][template_id] => [rules]` cache).
    //
    // Writing directly to the option is not durable — Pro will rebuild it
    // from post meta. These abilities therefore:
    //   1. Mutate `_elementor_conditions` post meta on the template post.
    //   2. Call `Conditions_Cache::regenerate()` so the cache option matches.
    //   3. Clear Elementor's compiled-CSS/files cache so the template renders.
    //
    // Condition strings are `{include|exclude}/{type}[/{sub_type}]`.
    // =========================================================================

    private function register_theme_builder_abilities() {
        // List all conditions
        wp_register_ability( 'elementor/list-theme-builder-conditions', array(
            'label'               => 'List Theme Builder Conditions',
            'description'         => 'List all Elementor Pro Theme Builder condition assignments (post_type archives, singles, headers, footers, etc.). Returns the raw conditions map so you can verify which templates are wired up to which content.',
            'category'            => self::$category,
            'input_schema'        => array(
                'type'       => 'object',
                'properties' => array(
                    'template_id' => array( 'type' => 'integer', 'description' => 'Optional — return conditions for a single template only.' ),
                ),
            ),
            'output_schema'       => array( 'type' => 'object' ),
            'execute_callback'    => array( $this, 'execute_list_theme_builder_conditions' ),
            'permission_callback' => function () { return current_user_can( 'edit_theme_options' ); },
            'meta'                => self::$mcp_meta,
        ) );

        // Add a condition
        wp_register_ability( 'elementor/add-theme-builder-condition', array(
            'label'               => 'Add Theme Builder Condition',
            'description'         => 'Wire an Elementor Pro Theme Builder template (archive/single/header/footer) to a content rule. The rule is stored as `{include|exclude}/{type}/{sub_type}` in the `elementor_pro_theme_builder_conditions` option. Examples: type=archive + sub_type=cable-product-line_archive, type=singular + sub_type=page, type=general + sub_type= (empty for site-wide). Use this instead of guessing the option name.',
            'category'            => self::$category,
            'input_schema'        => array(
                'type'       => 'object',
                'properties' => array(
                    'template_id' => array( 'type' => 'integer', 'description' => 'The Elementor template (elementor_library) post ID.' ),
                    'include'     => array( 'type' => 'boolean', 'description' => 'true = include rule, false = exclude rule. Default: true.', 'default' => true ),
                    'type'        => array( 'type' => 'string', 'description' => 'Condition type: archive, singular, general, search, etc.' ),
                    'sub_type'    => array( 'type' => 'string', 'description' => 'Condition sub-type (e.g. `cable-product-line_archive`, `page`, `category_cat-slug`). May be empty for catch-all types.' ),
                ),
                'required'   => array( 'template_id', 'type' ),
            ),
            'output_schema'       => array( 'type' => 'object' ),
            'execute_callback'    => array( $this, 'execute_add_theme_builder_condition' ),
            'permission_callback' => function () { return current_user_can( 'edit_theme_options' ); },
            'meta'                => self::$mcp_meta,
        ) );

        // Remove a condition
        wp_register_ability( 'elementor/remove-theme-builder-condition', array(
            'label'               => 'Remove Theme Builder Condition',
            'description'         => 'Remove a specific Theme Builder condition, or all conditions for a template if only template_id is provided.',
            'category'            => self::$category,
            'input_schema'        => array(
                'type'       => 'object',
                'properties' => array(
                    'template_id' => array( 'type' => 'integer', 'description' => 'The Elementor template post ID.' ),
                    'type'        => array( 'type' => 'string', 'description' => 'Optional — only remove conditions of this type.' ),
                    'sub_type'    => array( 'type' => 'string', 'description' => 'Optional — only remove the condition matching this sub_type.' ),
                    'include'     => array( 'type' => 'boolean', 'description' => 'Optional — filter by include/exclude flag.' ),
                ),
                'required'   => array( 'template_id' ),
            ),
            'output_schema'       => array( 'type' => 'object' ),
            'execute_callback'    => array( $this, 'execute_remove_theme_builder_condition' ),
            'permission_callback' => function () { return current_user_can( 'edit_theme_options' ); },
            'meta'                => self::$mcp_meta,
        ) );

        // Verify / repair malformed conditions
        wp_register_ability( 'elementor/verify-theme-builder-conditions', array(
            'label'               => 'Verify Theme Builder Conditions',
            'description'         => 'Inspect the raw `elementor_pro_theme_builder_conditions` option and each template\'s `_elementor_conditions` post meta for malformed entries (non-string values, empty strings, entries missing `{include|exclude}/{type}` shape). Returns a per-template report. Pass `repair: true` to drop the bad entries and regenerate the cache — useful when Elementor Pro throws `explode(): Argument #2 must be of type string, array given` in `conditions-manager.php`.',
            'category'            => self::$category,
            'input_schema'        => array(
                'type'       => 'object',
                'properties' => array(
                    'repair' => array( 'type' => 'boolean', 'description' => 'If true, remove malformed entries from post meta + rebuild the option cache. Default: false (report only).', 'default' => false ),
                ),
            ),
            'output_schema'       => array( 'type' => 'object' ),
            'execute_callback'    => array( $this, 'execute_verify_theme_builder_conditions' ),
            'permission_callback' => function () { return current_user_can( 'edit_theme_options' ); },
            'meta'                => self::$mcp_meta,
        ) );
    }

    // =========================================================================
    // THEME BUILDER EXECUTION METHODS
    // =========================================================================

    /**
     * Storage key for Elementor Pro's compiled theme-builder conditions cache.
     * IMPORTANT: This option is a *cache* that Elementor Pro regenerates from
     * post meta on demand. The authoritative source of a template's condition
     * list is the `_elementor_conditions` post meta on the theme template post
     * itself. Writing only to this option is not durable — Elementor will
     * rebuild it from post meta and overwrite your changes. These abilities
     * therefore write post meta first, then refresh the cache.
     *
     * Option shape (after regeneration):
     *   [
     *     $location => [                     // "archive", "single", "header", ...
     *       $template_post_id => [           // int
     *         "include/archive/{cpt}_archive",
     *         "exclude/singular/post_1234",
     *         ...
     *       ],
     *     ],
     *   ]
     */
    const THEME_BUILDER_OPTION = 'elementor_pro_theme_builder_conditions';

    /** Post meta key holding the authoritative condition list on each template. */
    const THEME_BUILDER_META = '_elementor_conditions';

    /**
     * Read raw cache option. Returns `[]` if unset or malformed.
     */
    private function read_theme_builder_cache() {
        $raw = get_option( self::THEME_BUILDER_OPTION, array() );
        if ( is_string( $raw ) ) {
            $decoded = json_decode( $raw, true );
            $raw = is_array( $decoded ) ? $decoded : array();
        }
        return is_array( $raw ) ? $raw : array();
    }

    /**
     * Read the authoritative condition list for a single template (from post
     * meta). Returns `[]` if the meta is missing.
     */
    private function read_template_conditions( $template_id ) {
        $meta = get_post_meta( (int) $template_id, self::THEME_BUILDER_META, true );
        return is_array( $meta ) ? array_values( $meta ) : array();
    }

    /**
     * Persist the condition list for a template via post meta, then refresh
     * Elementor Pro's compiled cache so the changes take effect immediately.
     */
    private function write_template_conditions( $template_id, array $conditions ) {
        $template_id = (int) $template_id;
        if ( empty( $conditions ) ) {
            delete_post_meta( $template_id, self::THEME_BUILDER_META );
        } else {
            update_post_meta( $template_id, self::THEME_BUILDER_META, array_values( $conditions ) );
        }
        $this->refresh_theme_builder_cache();
    }

    /**
     * Rebuild the `elementor_pro_theme_builder_conditions` option from current
     * `_elementor_conditions` post meta across all theme templates. Prefers
     * Elementor Pro's own cache class when available so we stay in sync with
     * any filters/locations it registers; falls back to a direct rebuild if
     * Pro isn't loaded.
     */
    private function refresh_theme_builder_cache() {
        $cache_class = '\\ElementorPro\\Modules\\ThemeBuilder\\Classes\\Conditions_Cache';
        if ( class_exists( $cache_class ) ) {
            try {
                $cache = new $cache_class();
                if ( method_exists( $cache, 'regenerate' ) ) {
                    $cache->regenerate();
                }
            } catch ( \Throwable $e ) {
                // Fall through to manual rebuild if Pro internals throw.
                $this->manual_rebuild_theme_builder_cache();
            }
        } else {
            $this->manual_rebuild_theme_builder_cache();
        }

        // Also bust Elementor's files (compiled CSS) cache so the new template
        // actually renders on the next pageview.
        if ( class_exists( '\\Elementor\\Plugin' ) ) {
            $plugin = \Elementor\Plugin::instance();
            if ( isset( $plugin->files_manager ) && method_exists( $plugin->files_manager, 'clear_cache' ) ) {
                $plugin->files_manager->clear_cache();
            }
        }
    }

    /**
     * Last-resort cache rebuild without Pro's helper. Note: Without Pro's
     * location registry this cannot reliably determine each document's
     * location, so it stores rules under a synthetic "unknown" bucket. The
     * primary path above via `Conditions_Cache::regenerate()` should always
     * succeed when Pro is active.
     */
    private function manual_rebuild_theme_builder_cache() {
        $query = new \WP_Query( array(
            'post_type'      => 'elementor_library',
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_key'       => self::THEME_BUILDER_META,
        ) );
        $rebuilt = array();
        foreach ( $query->posts as $pid ) {
            $rules = $this->read_template_conditions( $pid );
            if ( empty( $rules ) ) {
                continue;
            }
            $location = get_post_meta( $pid, '_elementor_template_type', true );
            if ( ! $location ) {
                $location = 'unknown';
            }
            if ( ! isset( $rebuilt[ $location ] ) ) {
                $rebuilt[ $location ] = array();
            }
            $rebuilt[ $location ][ (int) $pid ] = $rules;
        }
        update_option( self::THEME_BUILDER_OPTION, $rebuilt );
    }

    /**
     * Build the condition string that ends up in post meta + cache.
     * Format: `{include|exclude}/{type}[/{sub_type}]`
     */
    private function build_condition_string( $include, $type, $sub_type ) {
        $prefix = $include ? 'include' : 'exclude';
        $parts  = array( $prefix, $type );
        if ( '' !== (string) $sub_type ) {
            $parts[] = $sub_type;
        }
        return implode( '/', $parts );
    }

    /**
     * Enumerate every theme-builder template and return a flat list of
     * `{template_id, template_title, template_type, conditions}` rows. Reads
     * post meta directly so the result matches what Elementor will render with,
     * regardless of the cache option state.
     */
    private function collect_all_template_conditions() {
        $query = new \WP_Query( array(
            'post_type'      => 'elementor_library',
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_key'       => self::THEME_BUILDER_META,
        ) );
        $rows = array();
        foreach ( $query->posts as $pid ) {
            $rows[] = array(
                'template_id'    => (int) $pid,
                'template_title' => get_the_title( $pid ),
                'template_type'  => get_post_meta( $pid, '_elementor_template_type', true ),
                'conditions'     => $this->read_template_conditions( $pid ),
            );
        }
        return $rows;
    }

    public function execute_list_theme_builder_conditions( $input ) {
        if ( ! $this->is_elementor_pro_active() ) {
            return new WP_Error( 'elementor_pro_not_active', 'Elementor Pro is required for Theme Builder conditions.', array( 'status' => 500 ) );
        }

        if ( ! empty( $input['template_id'] ) ) {
            $tid = absint( $input['template_id'] );
            return array(
                'template_id'    => $tid,
                'template_title' => get_the_title( $tid ),
                'template_type'  => get_post_meta( $tid, '_elementor_template_type', true ),
                'conditions'     => $this->read_template_conditions( $tid ),
            );
        }

        $rows = $this->collect_all_template_conditions();
        return array(
            'conditions' => $rows,
            'count'      => count( $rows ),
        );
    }

    public function execute_add_theme_builder_condition( $input ) {
        if ( ! $this->is_elementor_pro_active() ) {
            return new WP_Error( 'elementor_pro_not_active', 'Elementor Pro is required for Theme Builder conditions.', array( 'status' => 500 ) );
        }

        $template_id = absint( $input['template_id'] );
        if ( ! $template_id || 'elementor_library' !== get_post_type( $template_id ) ) {
            return new WP_Error( 'invalid_template', "Template $template_id is not an elementor_library post.", array( 'status' => 400 ) );
        }

        $include  = array_key_exists( 'include', $input ) ? (bool) $input['include'] : true;
        $type     = sanitize_text_field( $input['type'] );
        $sub_type = isset( $input['sub_type'] ) ? sanitize_text_field( $input['sub_type'] ) : '';

        $condition = $this->build_condition_string( $include, $type, $sub_type );

        $existing = $this->read_template_conditions( $template_id );
        if ( ! in_array( $condition, $existing, true ) ) {
            $existing[] = $condition;
            $this->write_template_conditions( $template_id, $existing );
        }

        return array(
            'success'     => true,
            'template_id' => $template_id,
            'condition'   => $condition,
            'conditions'  => $existing,
            'message'     => "Condition '$condition' applied to template $template_id.",
        );
    }

    public function execute_remove_theme_builder_condition( $input ) {
        if ( ! $this->is_elementor_pro_active() ) {
            return new WP_Error( 'elementor_pro_not_active', 'Elementor Pro is required for Theme Builder conditions.', array( 'status' => 500 ) );
        }

        $template_id = absint( $input['template_id'] );
        $filter_type = isset( $input['type'] ) ? sanitize_text_field( $input['type'] ) : null;
        $filter_sub  = isset( $input['sub_type'] ) ? sanitize_text_field( $input['sub_type'] ) : null;
        $filter_inc  = array_key_exists( 'include', $input ) ? (bool) $input['include'] : null;

        $existing = $this->read_template_conditions( $template_id );
        if ( empty( $existing ) ) {
            return array(
                'success'     => true,
                'template_id' => $template_id,
                'removed'     => array(),
                'remaining'   => array(),
                'message'     => "No conditions found for template $template_id.",
            );
        }

        $no_filter = ( null === $filter_type && null === $filter_sub && null === $filter_inc );
        $remaining = array();
        $removed   = array();
        foreach ( $existing as $rule ) {
            $parts  = explode( '/', (string) $rule, 3 );
            $prefix = $parts[0] ?? '';
            $type   = $parts[1] ?? '';
            $sub    = $parts[2] ?? '';
            $inc    = ( 'include' === $prefix );

            $matches = true;
            if ( null !== $filter_type && $filter_type !== $type ) { $matches = false; }
            if ( null !== $filter_sub  && $filter_sub  !== $sub )  { $matches = false; }
            if ( null !== $filter_inc  && $filter_inc  !== $inc )  { $matches = false; }

            if ( $no_filter || $matches ) {
                $removed[] = $rule;
            } else {
                $remaining[] = $rule;
            }
        }

        $this->write_template_conditions( $template_id, $remaining );

        return array(
            'success'     => true,
            'template_id' => $template_id,
            'removed'     => $removed,
            'remaining'   => $remaining,
            'message'     => count( $removed ) . ' condition(s) removed from template ' . $template_id . '.',
        );
    }

    /**
     * Inspect post meta + option for malformed condition entries. A well-formed
     * entry is a non-empty string starting with `include/` or `exclude/`. Anything
     * else (arrays, ints, empty strings, bare words) will trip Elementor Pro's
     * `Conditions_Manager::parse_condition()` which calls `explode('/', $rule)`.
     */
    public function execute_verify_theme_builder_conditions( $input ) {
        if ( ! $this->is_elementor_pro_active() ) {
            return new WP_Error( 'elementor_pro_not_active', 'Elementor Pro is required for Theme Builder conditions.', array( 'status' => 500 ) );
        }

        $repair = ! empty( $input['repair'] );

        $is_valid = function ( $rule ) {
            if ( ! is_string( $rule ) || '' === $rule ) {
                return false;
            }
            $parts = explode( '/', $rule );
            if ( count( $parts ) < 2 ) {
                return false;
            }
            return in_array( $parts[0], array( 'include', 'exclude' ), true ) && '' !== $parts[1];
        };

        $describe = function ( $rule ) {
            if ( is_string( $rule ) ) {
                return array( 'type' => 'string', 'value' => $rule );
            }
            if ( is_array( $rule ) ) {
                return array( 'type' => 'array', 'value' => $rule );
            }
            return array( 'type' => gettype( $rule ), 'value' => $rule );
        };

        // 1. Inspect every template's post meta.
        $templates_report = array();
        $templates_repaired = array();
        $query = new \WP_Query( array(
            'post_type'      => 'elementor_library',
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_key'       => self::THEME_BUILDER_META,
        ) );
        foreach ( $query->posts as $pid ) {
            $raw = get_post_meta( (int) $pid, self::THEME_BUILDER_META, true );
            if ( ! is_array( $raw ) ) {
                $raw = array();
            }
            $bad   = array();
            $clean = array();
            foreach ( $raw as $rule ) {
                if ( $is_valid( $rule ) ) {
                    $clean[] = $rule;
                } else {
                    $bad[] = $describe( $rule );
                }
            }
            if ( empty( $bad ) ) {
                continue;
            }
            $templates_report[] = array(
                'template_id'    => (int) $pid,
                'template_title' => get_the_title( (int) $pid ),
                'template_type'  => get_post_meta( (int) $pid, '_elementor_template_type', true ),
                'valid'          => $clean,
                'invalid'        => $bad,
            );
            if ( $repair ) {
                if ( empty( $clean ) ) {
                    delete_post_meta( (int) $pid, self::THEME_BUILDER_META );
                } else {
                    update_post_meta( (int) $pid, self::THEME_BUILDER_META, array_values( $clean ) );
                }
                $templates_repaired[] = (int) $pid;
            }
        }

        // 2. Inspect the cached option directly.
        $option_raw = get_option( self::THEME_BUILDER_OPTION, array() );
        $option_issues = array();
        if ( is_array( $option_raw ) ) {
            foreach ( $option_raw as $location => $bucket ) {
                if ( ! is_array( $bucket ) ) {
                    $option_issues[] = array(
                        'location'    => $location,
                        'problem'     => 'bucket_not_array',
                        'value'       => $describe( $bucket ),
                    );
                    continue;
                }
                foreach ( $bucket as $tpl_id => $rules ) {
                    if ( ! is_array( $rules ) ) {
                        $option_issues[] = array(
                            'location'    => $location,
                            'template_id' => $tpl_id,
                            'problem'     => 'rules_not_array',
                            'value'       => $describe( $rules ),
                        );
                        continue;
                    }
                    foreach ( $rules as $rule ) {
                        if ( ! $is_valid( $rule ) ) {
                            $option_issues[] = array(
                                'location'    => $location,
                                'template_id' => $tpl_id,
                                'problem'     => 'invalid_rule',
                                'value'       => $describe( $rule ),
                            );
                        }
                    }
                }
            }
        } else {
            $option_issues[] = array(
                'problem' => 'option_not_array',
                'value'   => $describe( $option_raw ),
            );
        }

        if ( $repair ) {
            // Regenerate option from the now-clean post meta.
            $this->refresh_theme_builder_cache();
        }

        return array(
            'success'            => true,
            'repair'             => $repair,
            'templates_with_issues' => $templates_report,
            'templates_repaired' => $templates_repaired,
            'option_issues'      => $option_issues,
            'option_clean'       => empty( $option_issues ),
            'message'            => $repair
                ? sprintf( 'Repaired %d template(s); cache regenerated.', count( $templates_repaired ) )
                : sprintf( '%d template(s) have malformed entries; %d option-level issue(s).', count( $templates_report ), count( $option_issues ) ),
        );
    }

    /** Elementor Pro detection used by theme builder abilities. */
    private function is_elementor_pro_active() {
        return defined( 'ELEMENTOR_PRO_VERSION' ) || class_exists( '\\ElementorPro\\Plugin' );
    }

    // =========================================================================
    // BUILDER EXECUTION METHODS
    // =========================================================================

    public function execute_get_container_type( $input ) {
        $capabilities = $this->get_elementor_capabilities();

        if ( ! empty( $capabilities['container_feature_active'] ) ) {
            $container_type = ! empty( $capabilities['grid_containers'] ) ? 'flexbox' : 'flexbox';
            $recommended    = 'container';
        } else {
            $container_type = 'legacy';
            $recommended    = 'section';
        }

        return array(
            'container_type'           => $container_type,
            'supports_flexbox'         => ! empty( $capabilities['container_feature_active'] ),
            'supports_grid'            => ! empty( $capabilities['grid_containers'] ),
            'recommended_root_element' => $recommended,
            'capabilities'             => $capabilities,
            'elementor_version'        => $this->get_elementor_version(),
        );
    }

    public function execute_get_page_summary( $input ) {
        $post_id = absint( $input['post_id'] );
        $post    = get_post( $post_id );

        if ( ! $post ) {
            return new WP_Error( 'not_found', 'Post not found.', array( 'status' => 404 ) );
        }

        $elements = $this->get_document_elements( $post_id );
        $document = $this->get_elementor_document( $post_id );

        $widget_counts = array();
        $max_depth     = 0;
        $this->collect_element_stats( $elements, $widget_counts, $max_depth, 0 );

        $sections_summary = array();
        foreach ( $elements as $index => $element ) {
            $section_info = array(
                'index'      => $index,
                'id'         => $element['id'] ?? '',
                'elType'     => $element['elType'] ?? '',
                'childCount' => ! empty( $element['elements'] ) ? count( $element['elements'] ) : 0,
            );

            if ( isset( $element['settings']['container_type'] ) ) {
                $section_info['containerType'] = $element['settings']['container_type'];
            }

            // Collect text preview from direct child widgets.
            $text_preview = array();
            $this->collect_text_preview( array( $element ), $text_preview, 3 );
            if ( ! empty( $text_preview ) ) {
                $section_info['text_preview'] = $text_preview;
            }

            // List widget types in this section.
            $section_widgets = array();
            $this->collect_element_stats( array( $element ), $section_widgets, $max_depth, 0 );
            if ( ! empty( $section_widgets ) ) {
                $section_info['widget_types'] = array_keys( $section_widgets );
            }

            $sections_summary[] = $section_info;
        }

        $container_type_info = $this->execute_get_container_type( array() );

        $edit_url = $document && method_exists( $document, 'get_edit_url' )
            ? $document->get_edit_url()
            : admin_url( 'post.php?post=' . $post_id . '&action=elementor' );

        return array(
            'post_id'        => $post_id,
            'post_title'     => $post->post_title,
            'post_status'    => $post->post_status,
            'permalink'      => get_permalink( $post_id ),
            'edit_url'       => $edit_url,
            'total_elements' => $this->count_elements( $elements ),
            'root_sections'  => count( $elements ),
            'max_depth'      => $max_depth,
            'widget_summary' => $widget_counts,
            'sections'       => $sections_summary,
            'container_type' => $container_type_info['container_type'] ?? 'unknown',
        );
    }

    /**
     * Recursively collect widget type counts and max depth.
     */
    private function collect_element_stats( $elements, &$widget_counts, &$max_depth, $depth ) {
        if ( $depth > $max_depth ) {
            $max_depth = $depth;
        }

        foreach ( $elements as $element ) {
            if ( isset( $element['widgetType'] ) && '' !== $element['widgetType'] ) {
                $type = $element['widgetType'];
                if ( ! isset( $widget_counts[ $type ] ) ) {
                    $widget_counts[ $type ] = 0;
                }
                $widget_counts[ $type ]++;
            }

            if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
                $this->collect_element_stats( $element['elements'], $widget_counts, $max_depth, $depth + 1 );
            }
        }
    }

    /**
     * Collect short text previews from text-bearing widgets.
     */
    private function collect_text_preview( $elements, &$previews, $limit ) {
        if ( count( $previews ) >= $limit ) {
            return;
        }

        $text_keys = array( 'title', 'editor', 'text', 'heading_title', 'description_text', 'button_text', 'html' );

        foreach ( $elements as $element ) {
            if ( count( $previews ) >= $limit ) {
                return;
            }

            if ( isset( $element['settings'] ) && is_array( $element['settings'] ) ) {
                foreach ( $text_keys as $key ) {
                    if ( ! empty( $element['settings'][ $key ] ) && is_string( $element['settings'][ $key ] ) ) {
                        $text = wp_strip_all_tags( $element['settings'][ $key ] );
                        $text = mb_substr( trim( $text ), 0, 120 );
                        if ( '' !== $text ) {
                            $previews[] = array(
                                'widget_type' => $element['widgetType'] ?? $element['elType'] ?? '',
                                'key'         => $key,
                                'text'        => $text,
                            );
                            break;
                        }
                    }
                }
            }

            if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
                $this->collect_text_preview( $element['elements'], $previews, $limit );
            }
        }
    }

    public function execute_apply_blueprint( $input ) {
        $post_id           = absint( $input['post_id'] );
        $elements          = $input['elements'];
        $settings          = isset( $input['settings'] ) && is_array( $input['settings'] ) ? $input['settings'] : null;
        $auto_wrap_widgets = isset( $input['auto_wrap_widgets'] ) ? (bool) $input['auto_wrap_widgets'] : true;
        $generate_ids      = isset( $input['generate_ids'] ) ? (bool) $input['generate_ids'] : false;

        if ( ! is_array( $elements ) ) {
            return new WP_Error( 'invalid_elements', 'Elements must be an array.', array( 'status' => 400 ) );
        }

        $post = get_post( $post_id );
        if ( ! $post ) {
            return new WP_Error( 'not_found', 'Post not found.', array( 'status' => 404 ) );
        }

        // Generate fresh IDs if requested.
        if ( $generate_ids ) {
            $elements = array_map( array( $this, 'regenerate_element_ids' ), $elements );
        }

        // Ensure every element has an ID.
        $elements = array_map( array( $this, 'ensure_element_ids' ), $elements );

        // Auto-wrap root-level widgets in containers.
        if ( $auto_wrap_widgets ) {
            $elements = $this->auto_wrap_root_widgets( $elements );
        }

        // Validate all elements.
        $validation = $this->validate_elements_for_save( $elements );
        if ( is_wp_error( $validation ) ) {
            return $validation;
        }

        // Merge with existing settings if partial settings provided.
        if ( null !== $settings ) {
            $settings = $this->merge_document_settings( $post_id, $settings );
        }

        // Persist via the native document save API.
        $save_result = $this->save_document_content( $post_id, $elements, $settings );
        if ( is_wp_error( $save_result ) ) {
            return $save_result;
        }

        $document = $this->get_elementor_document( $post_id, false );

        $edit_url = $document && method_exists( $document, 'get_edit_url' )
            ? $document->get_edit_url()
            : admin_url( 'post.php?post=' . $post_id . '&action=elementor' );

        $preview_url = $document && method_exists( $document, 'get_preview_url' )
            ? $document->get_preview_url()
            : get_preview_post_link( $post_id );

        return array(
            'success'       => true,
            'post_id'       => $post_id,
            'element_count' => $this->count_elements( $elements ),
            'root_sections' => count( $elements ),
            'preview_url'   => $preview_url,
            'edit_url'      => $edit_url,
        );
    }

    /**
     * Recursively regenerate all element IDs.
     */
    private function regenerate_element_ids( $element ) {
        if ( is_array( $element ) ) {
            $element['id'] = $this->generate_element_id();

            if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
                $element['elements'] = array_map( array( $this, 'regenerate_element_ids' ), $element['elements'] );
            }
        }

        return $element;
    }

    /**
     * Ensure every element in a tree has a valid id.
     */
    private function ensure_element_ids( $element ) {
        if ( is_array( $element ) ) {
            if ( empty( $element['id'] ) || ! is_string( $element['id'] ) ) {
                $element['id'] = $this->generate_element_id();
            }

            if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
                $element['elements'] = array_map( array( $this, 'ensure_element_ids' ), $element['elements'] );
            }
        }

        return $element;
    }

    /**
     * Wrap any root-level widgets in the correct container type.
     */
    private function auto_wrap_root_widgets( $elements ) {
        $wrapped   = array();
        $pending   = array();

        foreach ( $elements as $element ) {
            if ( ! is_array( $element ) ) {
                continue;
            }

            $el_type = $element['elType'] ?? '';

            if ( in_array( $el_type, array( 'container', 'section' ), true ) ) {
                // Flush any pending widgets into a wrapper first.
                if ( ! empty( $pending ) ) {
                    $wrapped[] = $this->wrap_widgets_in_container( $pending );
                    $pending   = array();
                }
                $wrapped[] = $element;
            } else {
                $pending[] = $element;
            }
        }

        if ( ! empty( $pending ) ) {
            $wrapped[] = $this->wrap_widgets_in_container( $pending );
        }

        return $wrapped;
    }

    /**
     * Wrap a set of widget elements in the appropriate container/section.
     */
    private function wrap_widgets_in_container( $widgets ) {
        if ( $this->supports_container_elements() ) {
            return $this->build_container_element(
                array( 'content_width' => 'boxed' ),
                $widgets,
                'flexbox',
                'column',
                false
            );
        }

        return $this->build_legacy_section_element( array(), $widgets );
    }

    public function execute_apply_batch( $input ) {
        $post_id    = absint( $input['post_id'] );
        $operations = $input['operations'];

        if ( ! is_array( $operations ) || empty( $operations ) ) {
            return new WP_Error( 'invalid_operations', 'Operations must be a non-empty array.', array( 'status' => 400 ) );
        }

        $post = get_post( $post_id );
        if ( ! $post ) {
            return new WP_Error( 'not_found', 'Post not found.', array( 'status' => 404 ) );
        }

        // Snapshot the original data for rollback.
        $original_data = get_post_meta( $post_id, '_elementor_data', true );
        $elements      = $this->get_document_elements( $post_id );

        if ( ! is_array( $elements ) ) {
            $elements = array();
        }

        $results      = array();
        $affected_ids = array();

        foreach ( $operations as $op_index => $op ) {
            if ( ! isset( $op['op'] ) ) {
                // Rollback.
                if ( $original_data ) {
                    update_post_meta( $post_id, '_elementor_data', $original_data );
                }

                return new WP_Error( 'invalid_operation', 'Operation at index ' . $op_index . ' is missing the "op" field.', array( 'status' => 400 ) );
            }

            $op_type = sanitize_text_field( $op['op'] );
            $result  = null;

            switch ( $op_type ) {
                case 'add':
                    $result = $this->batch_op_add( $elements, $op );
                    break;

                case 'update':
                    $result = $this->batch_op_update( $elements, $op );
                    break;

                case 'remove':
                    $result = $this->batch_op_remove( $elements, $op );
                    break;

                case 'move':
                    $result = $this->batch_op_move( $elements, $op );
                    break;

                case 'duplicate':
                    $result = $this->batch_op_duplicate( $elements, $op );
                    break;

                case 'replace':
                    $result = $this->batch_op_replace( $elements, $op );
                    break;

                default:
                    $result = new WP_Error( 'unknown_op', 'Unknown operation "' . $op_type . '" at index ' . $op_index . '.' );
                    break;
            }

            if ( is_wp_error( $result ) ) {
                // Rollback on any failure.
                if ( $original_data ) {
                    update_post_meta( $post_id, '_elementor_data', $original_data );
                }

                return new WP_Error(
                    'batch_failed',
                    'Batch rolled back. Operation ' . $op_index . ' (' . $op_type . ') failed: ' . $result->get_error_message(),
                    array( 'status' => 400, 'failed_index' => $op_index, 'failed_op' => $op_type )
                );
            }

            $results[] = $result;
            if ( isset( $result['affected_id'] ) ) {
                $affected_ids[] = $result['affected_id'];
            }
        }

        // Save the final state.
        $save_result = $this->save_elements( $post_id, $elements );
        if ( is_wp_error( $save_result ) ) {
            // Rollback.
            if ( $original_data ) {
                update_post_meta( $post_id, '_elementor_data', $original_data );
            }

            return $save_result;
        }

        return array(
            'success'          => true,
            'operations_count' => count( $operations ),
            'results'          => $results,
            'affected_ids'     => $affected_ids,
            'element_count'    => $this->count_elements( $elements ),
        );
    }

    // ---- Batch operation helpers ----

    private function batch_op_add( &$elements, $op ) {
        $widget_type  = isset( $op['widget_type'] ) ? sanitize_text_field( $op['widget_type'] ) : '';
        $container_id = isset( $op['container_id'] ) ? sanitize_text_field( $op['container_id'] ) : null;
        $settings     = isset( $op['settings'] ) && is_array( $op['settings'] ) ? $op['settings'] : array();
        $position     = isset( $op['position'] ) ? intval( $op['position'] ) : -1;

        // Allow full element definition.
        if ( isset( $op['element'] ) && is_array( $op['element'] ) ) {
            $new_element = $this->ensure_element_ids( $op['element'] );
        } elseif ( $widget_type ) {
            $new_element = $this->build_widget_element( $widget_type, $settings );
        } else {
            return new WP_Error( 'missing_widget', 'Add operation requires widget_type or element.' );
        }

        if ( $container_id ) {
            $found = $this->find_element_by_id( $elements, $container_id );
            if ( ! $found ) {
                return new WP_Error( 'container_not_found', "Container '$container_id' not found." );
            }

            if ( ! $this->can_accept_child_elements( $found['element'] ) ) {
                return new WP_Error( 'invalid_container', "Element '$container_id' cannot contain children." );
            }

            if ( ! isset( $found['element']['elements'] ) ) {
                $found['element']['elements'] = array();
            }

            if ( $position >= 0 && $position < count( $found['element']['elements'] ) ) {
                array_splice( $found['element']['elements'], $position, 0, array( $new_element ) );
            } else {
                $found['element']['elements'][] = $new_element;
            }
        } else {
            // Root level — wrap in container.
            $wrapper = $this->wrap_widgets_in_container( array( $new_element ) );
            if ( $position >= 0 && $position < count( $elements ) ) {
                array_splice( $elements, $position, 0, array( $wrapper ) );
            } else {
                $elements[] = $wrapper;
            }
        }

        return array( 'op' => 'add', 'affected_id' => $new_element['id'] );
    }

    private function batch_op_update( &$elements, $op ) {
        $element_id = isset( $op['element_id'] ) ? sanitize_text_field( $op['element_id'] ) : '';
        $settings   = isset( $op['settings'] ) && is_array( $op['settings'] ) ? $op['settings'] : array();

        if ( ! $element_id ) {
            return new WP_Error( 'missing_id', 'Update operation requires element_id.' );
        }

        $found = $this->find_element_by_id( $elements, $element_id );
        if ( ! $found ) {
            return new WP_Error( 'not_found', "Element '$element_id' not found." );
        }

        $current_settings             = $found['element']['settings'] ?? array();
        $found['element']['settings'] = array_replace_recursive( $current_settings, $settings );

        return array( 'op' => 'update', 'affected_id' => $element_id );
    }

    private function batch_op_remove( &$elements, $op ) {
        $element_id = isset( $op['element_id'] ) ? sanitize_text_field( $op['element_id'] ) : '';

        if ( ! $element_id ) {
            return new WP_Error( 'missing_id', 'Remove operation requires element_id.' );
        }

        $removed = $this->remove_element_by_id( $elements, $element_id );
        if ( ! $removed ) {
            return new WP_Error( 'not_found', "Element '$element_id' not found." );
        }

        return array( 'op' => 'remove', 'affected_id' => $element_id );
    }

    private function batch_op_move( &$elements, $op ) {
        $element_id          = isset( $op['element_id'] ) ? sanitize_text_field( $op['element_id'] ) : '';
        $target_container_id = isset( $op['target_container_id'] ) ? sanitize_text_field( $op['target_container_id'] ) : '';
        $position            = isset( $op['position'] ) ? intval( $op['position'] ) : -1;

        if ( ! $element_id || ! $target_container_id ) {
            return new WP_Error( 'missing_params', 'Move operation requires element_id and target_container_id.' );
        }

        $found = $this->find_element_by_id( $elements, $element_id );
        if ( ! $found ) {
            return new WP_Error( 'not_found', "Element '$element_id' not found." );
        }

        $element_to_move = $found['element'];
        $this->remove_element_by_id( $elements, $element_id );

        if ( 'root' === $target_container_id ) {
            if ( $position >= 0 && $position < count( $elements ) ) {
                array_splice( $elements, $position, 0, array( $element_to_move ) );
            } else {
                $elements[] = $element_to_move;
            }
        } else {
            $target = $this->find_element_by_id( $elements, $target_container_id );
            if ( ! $target ) {
                return new WP_Error( 'target_not_found', "Target container '$target_container_id' not found." );
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

        return array( 'op' => 'move', 'affected_id' => $element_id );
    }

    private function batch_op_duplicate( &$elements, $op ) {
        $element_id = isset( $op['element_id'] ) ? sanitize_text_field( $op['element_id'] ) : '';

        if ( ! $element_id ) {
            return new WP_Error( 'missing_id', 'Duplicate operation requires element_id.' );
        }

        $result = $this->duplicate_element_by_id( $elements, $element_id );
        if ( ! $result ) {
            return new WP_Error( 'not_found', "Element '$element_id' not found." );
        }

        return array( 'op' => 'duplicate', 'affected_id' => $result['new_id'] );
    }

    private function batch_op_replace( &$elements, $op ) {
        $element_id  = isset( $op['element_id'] ) ? sanitize_text_field( $op['element_id'] ) : '';
        $new_element = isset( $op['element'] ) && is_array( $op['element'] ) ? $op['element'] : null;

        if ( ! $element_id || ! $new_element ) {
            return new WP_Error( 'missing_params', 'Replace operation requires element_id and element.' );
        }

        $new_element = $this->ensure_element_ids( $new_element );

        $found = $this->find_element_by_id( $elements, $element_id );
        if ( ! $found ) {
            return new WP_Error( 'not_found', "Element '$element_id' not found." );
        }

        // Replace the element in place by copying all properties.
        foreach ( array_keys( $found['element'] ) as $key ) {
            unset( $found['element'][ $key ] );
        }
        foreach ( $new_element as $key => $value ) {
            $found['element'][ $key ] = $value;
        }

        return array( 'op' => 'replace', 'affected_id' => $new_element['id'] );
    }
}

Elementor_Abilities_Plugin::instance();
