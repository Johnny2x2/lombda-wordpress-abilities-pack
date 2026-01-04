<?php
/**
 * Plugin Name: WordPress FacetWP Abilities
 * Description: Exposes FacetWP filtering and facet management functionality as WordPress Abilities for AI agents via MCP.
 * Version: 1.0.0
 * Author: Lombda LLC
 * Requires Plugins: facetwp
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WordPress_FacetWP_Abilities_Plugin {
    private static $instance = null;
    private static $mcp_meta = array(
        'show_in_rest' => true,
        'mcp'          => array( 'public' => true ),
    );
    private static $category = 'facetwp';

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
                'label'       => 'FacetWP',
                'description' => 'FacetWP faceted filtering and search abilities for WordPress.',
            )
        );
    }

    /**
     * Check if FacetWP is active
     */
    private function is_facetwp_active() {
        return function_exists( 'FWP' ) && class_exists( 'FacetWP' );
    }

    public function register_abilities() {
        $this->register_facet_abilities();
        $this->register_template_abilities();
        $this->register_index_abilities();
        $this->register_settings_abilities();
        $this->register_data_source_abilities();
    }

    // =========================================================================
    // FACET ABILITIES
    // =========================================================================

    private function register_facet_abilities() {
        // List All Facets
        wp_register_ability( 'facetwp/list-facets', array(
            'label' => 'List FacetWP Facets',
            'description' => 'Get a list of all configured FacetWP facets with their settings.',
            'category' => self::$category,
            'input_schema' => array(
                'type' => 'object',
                'properties' => array(
                    'type' => array( 'type' => 'string', 'description' => 'Filter by facet type (checkboxes, dropdown, slider, etc.).' ),
                    'source' => array( 'type' => 'string', 'description' => 'Filter by data source prefix (tax, cf, acf, etc.).' ),
                ),
            ),
            'output_schema' => array( 'type' => 'object' ),
            'execute_callback' => array( $this, 'execute_list_facets' ),
            'permission_callback' => function() { return current_user_can( 'manage_options' ); },
            'meta' => self::$mcp_meta,
        ) );

        // Get Single Facet
        wp_register_ability( 'facetwp/get-facet', array(
            'label' => 'Get FacetWP Facet',
            'description' => 'Get detailed information about a specific facet by name.',
            'category' => self::$category,
            'input_schema' => array(
                'type' => 'object',
                'properties' => array(
                    'name' => array( 'type' => 'string', 'description' => 'The unique name/slug of the facet.' ),
                ),
                'required' => array( 'name' ),
            ),
            'output_schema' => array( 'type' => 'object' ),
            'execute_callback' => array( $this, 'execute_get_facet' ),
            'permission_callback' => function() { return current_user_can( 'manage_options' ); },
            'meta' => self::$mcp_meta,
        ) );

        // Create Facet
        wp_register_ability( 'facetwp/create-facet', array(
            'label' => 'Create FacetWP Facet',
            'description' => 'Create a new FacetWP facet with specified settings.',
            'category' => self::$category,
            'input_schema' => array(
                'type' => 'object',
                'properties' => array(
                    'name' => array( 'type' => 'string', 'description' => 'Unique name/slug for the facet (lowercase, no spaces).' ),
                    'label' => array( 'type' => 'string', 'description' => 'Display label for the facet.' ),
                    'type' => array( 
                        'type' => 'string', 
                        'description' => 'Facet type: checkboxes, dropdown, radio, fselect, hierarchy, slider, search, autocomplete, date_range, number_range, rating, proximity, pager, reset, sort.',
                        'enum' => array( 'checkboxes', 'dropdown', 'radio', 'fselect', 'hierarchy', 'slider', 'search', 'autocomplete', 'date_range', 'number_range', 'rating', 'proximity', 'pager', 'reset', 'sort' ),
                    ),
                    'source' => array( 'type' => 'string', 'description' => 'Data source (e.g., "tax/category", "cf/price", "post_type").' ),
                    'orderby' => array( 'type' => 'string', 'description' => 'Sort order: count, display_value, raw_value, term_order.' ),
                    'count' => array( 'type' => 'integer', 'description' => 'Number of choices to show (-1 for unlimited).' ),
                    'settings' => array( 'type' => 'object', 'description' => 'Additional facet-specific settings as key-value pairs.' ),
                ),
                'required' => array( 'name', 'label', 'type' ),
            ),
            'output_schema' => array( 'type' => 'object' ),
            'execute_callback' => array( $this, 'execute_create_facet' ),
            'permission_callback' => function() { return current_user_can( 'manage_options' ); },
            'meta' => self::$mcp_meta,
        ) );

        // Update Facet
        wp_register_ability( 'facetwp/update-facet', array(
            'label' => 'Update FacetWP Facet',
            'description' => 'Update an existing FacetWP facet.',
            'category' => self::$category,
            'input_schema' => array(
                'type' => 'object',
                'properties' => array(
                    'name' => array( 'type' => 'string', 'description' => 'The name of the facet to update.' ),
                    'label' => array( 'type' => 'string', 'description' => 'New display label.' ),
                    'type' => array( 'type' => 'string', 'description' => 'New facet type.' ),
                    'source' => array( 'type' => 'string', 'description' => 'New data source.' ),
                    'orderby' => array( 'type' => 'string', 'description' => 'New sort order.' ),
                    'count' => array( 'type' => 'integer', 'description' => 'New number of choices.' ),
                    'settings' => array( 'type' => 'object', 'description' => 'Additional settings to merge.' ),
                ),
                'required' => array( 'name' ),
            ),
            'output_schema' => array( 'type' => 'object' ),
            'execute_callback' => array( $this, 'execute_update_facet' ),
            'permission_callback' => function() { return current_user_can( 'manage_options' ); },
            'meta' => self::$mcp_meta,
        ) );

        // Delete Facet
        wp_register_ability( 'facetwp/delete-facet', array(
            'label' => 'Delete FacetWP Facet',
            'description' => 'Delete a FacetWP facet by name.',
            'category' => self::$category,
            'input_schema' => array(
                'type' => 'object',
                'properties' => array(
                    'name' => array( 'type' => 'string', 'description' => 'The name of the facet to delete.' ),
                ),
                'required' => array( 'name' ),
            ),
            'output_schema' => array( 'type' => 'object' ),
            'execute_callback' => array( $this, 'execute_delete_facet' ),
            'permission_callback' => function() { return current_user_can( 'manage_options' ); },
            'meta' => self::$mcp_meta,
        ) );

        // Get Facet Types
        wp_register_ability( 'facetwp/list-facet-types', array(
            'label' => 'List FacetWP Facet Types',
            'description' => 'Get all available facet types with their details.',
            'category' => self::$category,
            'input_schema' => array(
                'type' => 'object',
                'properties' => array(),
            ),
            'output_schema' => array( 'type' => 'object' ),
            'execute_callback' => array( $this, 'execute_list_facet_types' ),
            'permission_callback' => function() { return current_user_can( 'manage_options' ); },
            'meta' => self::$mcp_meta,
        ) );
    }

    // =========================================================================
    // TEMPLATE ABILITIES
    // =========================================================================

    private function register_template_abilities() {
        // List Templates
        wp_register_ability( 'facetwp/list-templates', array(
            'label' => 'List FacetWP Templates',
            'description' => 'Get a list of all configured FacetWP listing templates.',
            'category' => self::$category,
            'input_schema' => array(
                'type' => 'object',
                'properties' => array(),
            ),
            'output_schema' => array( 'type' => 'object' ),
            'execute_callback' => array( $this, 'execute_list_templates' ),
            'permission_callback' => function() { return current_user_can( 'manage_options' ); },
            'meta' => self::$mcp_meta,
        ) );

        // Get Template
        wp_register_ability( 'facetwp/get-template', array(
            'label' => 'Get FacetWP Template',
            'description' => 'Get detailed information about a specific template.',
            'category' => self::$category,
            'input_schema' => array(
                'type' => 'object',
                'properties' => array(
                    'name' => array( 'type' => 'string', 'description' => 'The unique name of the template.' ),
                ),
                'required' => array( 'name' ),
            ),
            'output_schema' => array( 'type' => 'object' ),
            'execute_callback' => array( $this, 'execute_get_template' ),
            'permission_callback' => function() { return current_user_can( 'manage_options' ); },
            'meta' => self::$mcp_meta,
        ) );

        // Create Template
        wp_register_ability( 'facetwp/create-template', array(
            'label' => 'Create FacetWP Template',
            'description' => 'Create a new FacetWP listing template.',
            'category' => self::$category,
            'input_schema' => array(
                'type' => 'object',
                'properties' => array(
                    'name' => array( 'type' => 'string', 'description' => 'Unique name/slug for the template.' ),
                    'label' => array( 'type' => 'string', 'description' => 'Display label for the template.' ),
                    'query' => array( 'type' => 'object', 'description' => 'WP_Query arguments as an object.' ),
                    'template' => array( 'type' => 'string', 'description' => 'PHP/HTML display template code.' ),
                    'modes' => array( 'type' => 'array', 'description' => 'Display modes: display, visual.' ),
                ),
                'required' => array( 'name', 'label' ),
            ),
            'output_schema' => array( 'type' => 'object' ),
            'execute_callback' => array( $this, 'execute_create_template' ),
            'permission_callback' => function() { return current_user_can( 'manage_options' ); },
            'meta' => self::$mcp_meta,
        ) );

        // Update Template
        wp_register_ability( 'facetwp/update-template', array(
            'label' => 'Update FacetWP Template',
            'description' => 'Update an existing FacetWP template.',
            'category' => self::$category,
            'input_schema' => array(
                'type' => 'object',
                'properties' => array(
                    'name' => array( 'type' => 'string', 'description' => 'The name of the template to update.' ),
                    'label' => array( 'type' => 'string', 'description' => 'New display label.' ),
                    'query' => array( 'type' => 'object', 'description' => 'New WP_Query arguments.' ),
                    'template' => array( 'type' => 'string', 'description' => 'New display template code.' ),
                ),
                'required' => array( 'name' ),
            ),
            'output_schema' => array( 'type' => 'object' ),
            'execute_callback' => array( $this, 'execute_update_template' ),
            'permission_callback' => function() { return current_user_can( 'manage_options' ); },
            'meta' => self::$mcp_meta,
        ) );

        // Delete Template
        wp_register_ability( 'facetwp/delete-template', array(
            'label' => 'Delete FacetWP Template',
            'description' => 'Delete a FacetWP template by name.',
            'category' => self::$category,
            'input_schema' => array(
                'type' => 'object',
                'properties' => array(
                    'name' => array( 'type' => 'string', 'description' => 'The name of the template to delete.' ),
                ),
                'required' => array( 'name' ),
            ),
            'output_schema' => array( 'type' => 'object' ),
            'execute_callback' => array( $this, 'execute_delete_template' ),
            'permission_callback' => function() { return current_user_can( 'manage_options' ); },
            'meta' => self::$mcp_meta,
        ) );
    }

    // =========================================================================
    // INDEX ABILITIES
    // =========================================================================

    private function register_index_abilities() {
        // Get Indexer Status
        wp_register_ability( 'facetwp/get-index-status', array(
            'label' => 'Get FacetWP Index Status',
            'description' => 'Get the current status of the FacetWP index including last indexed time and row counts.',
            'category' => self::$category,
            'input_schema' => array(
                'type' => 'object',
                'properties' => array(),
            ),
            'output_schema' => array( 'type' => 'object' ),
            'execute_callback' => array( $this, 'execute_get_index_status' ),
            'permission_callback' => function() { return current_user_can( 'manage_options' ); },
            'meta' => self::$mcp_meta,
        ) );

        // Rebuild Index
        wp_register_ability( 'facetwp/rebuild-index', array(
            'label' => 'Rebuild FacetWP Index',
            'description' => 'Trigger a complete rebuild of the FacetWP index. This may take time for large sites.',
            'category' => self::$category,
            'input_schema' => array(
                'type' => 'object',
                'properties' => array(
                    'post_id' => array( 'type' => 'integer', 'description' => 'Index a single post by ID. Omit to reindex all.' ),
                ),
            ),
            'output_schema' => array( 'type' => 'object' ),
            'execute_callback' => array( $this, 'execute_rebuild_index' ),
            'permission_callback' => function() { return current_user_can( 'manage_options' ); },
            'meta' => self::$mcp_meta,
        ) );

        // Get Index Progress
        wp_register_ability( 'facetwp/get-index-progress', array(
            'label' => 'Get FacetWP Index Progress',
            'description' => 'Get the current indexing progress percentage.',
            'category' => self::$category,
            'input_schema' => array(
                'type' => 'object',
                'properties' => array(),
            ),
            'output_schema' => array( 'type' => 'object' ),
            'execute_callback' => array( $this, 'execute_get_index_progress' ),
            'permission_callback' => function() { return current_user_can( 'manage_options' ); },
            'meta' => self::$mcp_meta,
        ) );

        // Purge Index
        wp_register_ability( 'facetwp/purge-index', array(
            'label' => 'Purge FacetWP Index',
            'description' => 'Completely purge the FacetWP index table. Use with caution.',
            'category' => self::$category,
            'input_schema' => array(
                'type' => 'object',
                'properties' => array(
                    'confirm' => array( 'type' => 'boolean', 'description' => 'Must be true to confirm purge.' ),
                ),
                'required' => array( 'confirm' ),
            ),
            'output_schema' => array( 'type' => 'object' ),
            'execute_callback' => array( $this, 'execute_purge_index' ),
            'permission_callback' => function() { return current_user_can( 'manage_options' ); },
            'meta' => self::$mcp_meta,
        ) );
    }

    // =========================================================================
    // SETTINGS ABILITIES
    // =========================================================================

    private function register_settings_abilities() {
        // Get Settings
        wp_register_ability( 'facetwp/get-settings', array(
            'label' => 'Get FacetWP Settings',
            'description' => 'Get the current FacetWP general settings.',
            'category' => self::$category,
            'input_schema' => array(
                'type' => 'object',
                'properties' => array(),
            ),
            'output_schema' => array( 'type' => 'object' ),
            'execute_callback' => array( $this, 'execute_get_settings' ),
            'permission_callback' => function() { return current_user_can( 'manage_options' ); },
            'meta' => self::$mcp_meta,
        ) );

        // Update Settings
        wp_register_ability( 'facetwp/update-settings', array(
            'label' => 'Update FacetWP Settings',
            'description' => 'Update FacetWP general settings.',
            'category' => self::$category,
            'input_schema' => array(
                'type' => 'object',
                'properties' => array(
                    'prefix' => array( 'type' => 'string', 'description' => 'URL prefix (fwp_ or _).' ),
                    'thousands_separator' => array( 'type' => 'string', 'description' => 'Number thousands separator.' ),
                    'decimal_separator' => array( 'type' => 'string', 'description' => 'Number decimal separator.' ),
                    'load_jquery' => array( 'type' => 'string', 'description' => 'Load jQuery: yes or no.' ),
                    'debug_mode' => array( 'type' => 'string', 'description' => 'Debug mode: on or off.' ),
                    'gmaps_api_key' => array( 'type' => 'string', 'description' => 'Google Maps API key.' ),
                ),
            ),
            'output_schema' => array( 'type' => 'object' ),
            'execute_callback' => array( $this, 'execute_update_settings' ),
            'permission_callback' => function() { return current_user_can( 'manage_options' ); },
            'meta' => self::$mcp_meta,
        ) );

        // Get FacetWP Info
        wp_register_ability( 'facetwp/get-info', array(
            'label' => 'Get FacetWP Info',
            'description' => 'Get FacetWP plugin information including version, license status, and capabilities.',
            'category' => self::$category,
            'input_schema' => array(
                'type' => 'object',
                'properties' => array(),
            ),
            'output_schema' => array( 'type' => 'object' ),
            'execute_callback' => array( $this, 'execute_get_info' ),
            'permission_callback' => function() { return current_user_can( 'manage_options' ); },
            'meta' => self::$mcp_meta,
        ) );

        // Export Settings
        wp_register_ability( 'facetwp/export-settings', array(
            'label' => 'Export FacetWP Settings',
            'description' => 'Export all FacetWP settings, facets, and templates as JSON.',
            'category' => self::$category,
            'input_schema' => array(
                'type' => 'object',
                'properties' => array(),
            ),
            'output_schema' => array( 'type' => 'object' ),
            'execute_callback' => array( $this, 'execute_export_settings' ),
            'permission_callback' => function() { return current_user_can( 'manage_options' ); },
            'meta' => self::$mcp_meta,
        ) );

        // Import Settings
        wp_register_ability( 'facetwp/import-settings', array(
            'label' => 'Import FacetWP Settings',
            'description' => 'Import FacetWP settings from JSON. Can overwrite or merge with existing.',
            'category' => self::$category,
            'input_schema' => array(
                'type' => 'object',
                'properties' => array(
                    'data' => array( 'type' => 'object', 'description' => 'The FacetWP settings object to import.' ),
                    'overwrite' => array( 'type' => 'boolean', 'description' => 'If true, completely replace existing settings. Default: false (merge).', 'default' => false ),
                ),
                'required' => array( 'data' ),
            ),
            'output_schema' => array( 'type' => 'object' ),
            'execute_callback' => array( $this, 'execute_import_settings' ),
            'permission_callback' => function() { return current_user_can( 'manage_options' ); },
            'meta' => self::$mcp_meta,
        ) );
    }

    // =========================================================================
    // DATA SOURCE ABILITIES
    // =========================================================================

    private function register_data_source_abilities() {
        // Get Data Sources
        wp_register_ability( 'facetwp/list-data-sources', array(
            'label' => 'List FacetWP Data Sources',
            'description' => 'Get all available data sources that can be used for facets (taxonomies, custom fields, etc.).',
            'category' => self::$category,
            'input_schema' => array(
                'type' => 'object',
                'properties' => array(
                    'type' => array( 'type' => 'string', 'description' => 'Filter by source type: posts, taxonomies, custom_fields.' ),
                ),
            ),
            'output_schema' => array( 'type' => 'object' ),
            'execute_callback' => array( $this, 'execute_list_data_sources' ),
            'permission_callback' => function() { return current_user_can( 'manage_options' ); },
            'meta' => self::$mcp_meta,
        ) );

        // Get Indexable Post Types
        wp_register_ability( 'facetwp/list-indexable-types', array(
            'label' => 'List Indexable Post Types',
            'description' => 'Get the list of post types that FacetWP can index.',
            'category' => self::$category,
            'input_schema' => array(
                'type' => 'object',
                'properties' => array(),
            ),
            'output_schema' => array( 'type' => 'object' ),
            'execute_callback' => array( $this, 'execute_list_indexable_types' ),
            'permission_callback' => function() { return current_user_can( 'manage_options' ); },
            'meta' => self::$mcp_meta,
        ) );
    }

    // =========================================================================
    // EXECUTE CALLBACKS - FACETS
    // =========================================================================

    public function execute_list_facets( $input ) {
        if ( ! $this->is_facetwp_active() ) {
            return new WP_Error( 'facetwp_not_active', 'FacetWP is not installed or active.', array( 'status' => 500 ) );
        }

        $facets = FWP()->helper->get_facets();
        
        // Filter by type if specified
        if ( ! empty( $input['type'] ) ) {
            $type = sanitize_text_field( $input['type'] );
            $facets = array_filter( $facets, function( $facet ) use ( $type ) {
                return isset( $facet['type'] ) && $facet['type'] === $type;
            } );
        }

        // Filter by source if specified
        if ( ! empty( $input['source'] ) ) {
            $source = sanitize_text_field( $input['source'] );
            $facets = array_filter( $facets, function( $facet ) use ( $source ) {
                return isset( $facet['source'] ) && strpos( $facet['source'], $source ) !== false;
            } );
        }

        return array(
            'facets' => array_values( $facets ),
            'count' => count( $facets ),
        );
    }

    public function execute_get_facet( $input ) {
        if ( ! $this->is_facetwp_active() ) {
            return new WP_Error( 'facetwp_not_active', 'FacetWP is not installed or active.', array( 'status' => 500 ) );
        }

        $name = sanitize_text_field( $input['name'] );
        $facet = FWP()->helper->get_facet_by_name( $name );

        if ( ! $facet ) {
            return new WP_Error( 'facet_not_found', "Facet '$name' not found.", array( 'status' => 404 ) );
        }

        // Get row count for this facet from index
        $row_counts = FWP()->helper->get_row_counts();
        $facet['index_count'] = isset( $row_counts[ $name ] ) ? $row_counts[ $name ] : 0;

        return array( 'facet' => $facet );
    }

    public function execute_create_facet( $input ) {
        if ( ! $this->is_facetwp_active() ) {
            return new WP_Error( 'facetwp_not_active', 'FacetWP is not installed or active.', array( 'status' => 500 ) );
        }

        $name = sanitize_title( $input['name'] );
        
        // Check if facet already exists
        if ( FWP()->helper->get_facet_by_name( $name ) ) {
            return new WP_Error( 'facet_exists', "Facet '$name' already exists.", array( 'status' => 400 ) );
        }

        // Build facet config
        $facet = array(
            'name' => $name,
            'label' => sanitize_text_field( $input['label'] ),
            'type' => sanitize_text_field( $input['type'] ),
        );

        if ( ! empty( $input['source'] ) ) {
            $facet['source'] = sanitize_text_field( $input['source'] );
        }
        if ( ! empty( $input['orderby'] ) ) {
            $facet['orderby'] = sanitize_text_field( $input['orderby'] );
        }
        if ( isset( $input['count'] ) ) {
            $facet['count'] = intval( $input['count'] );
        }

        // Merge additional settings
        if ( ! empty( $input['settings'] ) && is_array( $input['settings'] ) ) {
            foreach ( $input['settings'] as $key => $value ) {
                $facet[ sanitize_key( $key ) ] = is_array( $value ) ? $value : sanitize_text_field( $value );
            }
        }

        // Get current settings and add facet
        $settings_json = get_option( 'facetwp_settings', '{}' );
        $settings = json_decode( $settings_json, true );
        
        if ( ! isset( $settings['facets'] ) ) {
            $settings['facets'] = array();
        }

        $settings['facets'][] = $facet;

        // Save settings
        update_option( 'facetwp_settings', json_encode( $settings ), 'no' );

        // Reload helper settings
        FWP()->helper->settings = FWP()->helper->load_settings();

        return array(
            'success' => true,
            'facet' => $facet,
            'message' => "Facet '$name' created successfully. Re-index required for it to work.",
        );
    }

    public function execute_update_facet( $input ) {
        if ( ! $this->is_facetwp_active() ) {
            return new WP_Error( 'facetwp_not_active', 'FacetWP is not installed or active.', array( 'status' => 500 ) );
        }

        $name = sanitize_text_field( $input['name'] );

        // Get current settings
        $settings_json = get_option( 'facetwp_settings', '{}' );
        $settings = json_decode( $settings_json, true );

        if ( ! isset( $settings['facets'] ) ) {
            return new WP_Error( 'facet_not_found', "Facet '$name' not found.", array( 'status' => 404 ) );
        }

        // Find and update the facet
        $found = false;
        foreach ( $settings['facets'] as $i => $facet ) {
            if ( $facet['name'] === $name ) {
                // Update fields
                if ( ! empty( $input['label'] ) ) {
                    $settings['facets'][ $i ]['label'] = sanitize_text_field( $input['label'] );
                }
                if ( ! empty( $input['type'] ) ) {
                    $settings['facets'][ $i ]['type'] = sanitize_text_field( $input['type'] );
                }
                if ( ! empty( $input['source'] ) ) {
                    $settings['facets'][ $i ]['source'] = sanitize_text_field( $input['source'] );
                }
                if ( ! empty( $input['orderby'] ) ) {
                    $settings['facets'][ $i ]['orderby'] = sanitize_text_field( $input['orderby'] );
                }
                if ( isset( $input['count'] ) ) {
                    $settings['facets'][ $i ]['count'] = intval( $input['count'] );
                }

                // Merge additional settings
                if ( ! empty( $input['settings'] ) && is_array( $input['settings'] ) ) {
                    foreach ( $input['settings'] as $key => $value ) {
                        $settings['facets'][ $i ][ sanitize_key( $key ) ] = is_array( $value ) ? $value : sanitize_text_field( $value );
                    }
                }

                $found = true;
                $updated_facet = $settings['facets'][ $i ];
                break;
            }
        }

        if ( ! $found ) {
            return new WP_Error( 'facet_not_found', "Facet '$name' not found.", array( 'status' => 404 ) );
        }

        // Save settings
        update_option( 'facetwp_settings', json_encode( $settings ), 'no' );

        // Reload helper settings
        FWP()->helper->settings = FWP()->helper->load_settings();

        return array(
            'success' => true,
            'facet' => $updated_facet,
            'message' => "Facet '$name' updated successfully. Re-index may be required.",
        );
    }

    public function execute_delete_facet( $input ) {
        if ( ! $this->is_facetwp_active() ) {
            return new WP_Error( 'facetwp_not_active', 'FacetWP is not installed or active.', array( 'status' => 500 ) );
        }

        $name = sanitize_text_field( $input['name'] );

        // Get current settings
        $settings_json = get_option( 'facetwp_settings', '{}' );
        $settings = json_decode( $settings_json, true );

        if ( ! isset( $settings['facets'] ) ) {
            return new WP_Error( 'facet_not_found', "Facet '$name' not found.", array( 'status' => 404 ) );
        }

        // Find and remove the facet
        $found = false;
        foreach ( $settings['facets'] as $i => $facet ) {
            if ( $facet['name'] === $name ) {
                unset( $settings['facets'][ $i ] );
                $settings['facets'] = array_values( $settings['facets'] ); // Re-index array
                $found = true;
                break;
            }
        }

        if ( ! $found ) {
            return new WP_Error( 'facet_not_found', "Facet '$name' not found.", array( 'status' => 404 ) );
        }

        // Save settings
        update_option( 'facetwp_settings', json_encode( $settings ), 'no' );

        // Reload helper settings
        FWP()->helper->settings = FWP()->helper->load_settings();

        return array(
            'success' => true,
            'message' => "Facet '$name' deleted successfully.",
        );
    }

    public function execute_list_facet_types( $input ) {
        if ( ! $this->is_facetwp_active() ) {
            return new WP_Error( 'facetwp_not_active', 'FacetWP is not installed or active.', array( 'status' => 500 ) );
        }

        $types = FWP()->helper->get_facet_types();
        $result = array();

        foreach ( $types as $slug => $type_obj ) {
            $result[] = array(
                'slug' => $slug,
                'label' => isset( $type_obj->label ) ? $type_obj->label : ucfirst( str_replace( '_', ' ', $slug ) ),
            );
        }

        return array(
            'types' => $result,
            'count' => count( $result ),
        );
    }

    // =========================================================================
    // EXECUTE CALLBACKS - TEMPLATES
    // =========================================================================

    public function execute_list_templates( $input ) {
        if ( ! $this->is_facetwp_active() ) {
            return new WP_Error( 'facetwp_not_active', 'FacetWP is not installed or active.', array( 'status' => 500 ) );
        }

        $templates = FWP()->helper->get_templates();

        return array(
            'templates' => $templates,
            'count' => count( $templates ),
        );
    }

    public function execute_get_template( $input ) {
        if ( ! $this->is_facetwp_active() ) {
            return new WP_Error( 'facetwp_not_active', 'FacetWP is not installed or active.', array( 'status' => 500 ) );
        }

        $name = sanitize_text_field( $input['name'] );
        $template = FWP()->helper->get_template_by_name( $name );

        if ( ! $template ) {
            return new WP_Error( 'template_not_found', "Template '$name' not found.", array( 'status' => 404 ) );
        }

        return array( 'template' => $template );
    }

    public function execute_create_template( $input ) {
        if ( ! $this->is_facetwp_active() ) {
            return new WP_Error( 'facetwp_not_active', 'FacetWP is not installed or active.', array( 'status' => 500 ) );
        }

        $name = sanitize_title( $input['name'] );
        
        // Check if template already exists
        if ( FWP()->helper->get_template_by_name( $name ) ) {
            return new WP_Error( 'template_exists', "Template '$name' already exists.", array( 'status' => 400 ) );
        }

        // Build template config
        $template = array(
            'name' => $name,
            'label' => sanitize_text_field( $input['label'] ),
        );

        if ( ! empty( $input['query'] ) ) {
            $template['query'] = $input['query'];
        }
        if ( ! empty( $input['template'] ) ) {
            $template['template'] = $input['template'];
        }
        if ( ! empty( $input['modes'] ) ) {
            $template['modes'] = $input['modes'];
        }

        // Get current settings and add template
        $settings_json = get_option( 'facetwp_settings', '{}' );
        $settings = json_decode( $settings_json, true );
        
        if ( ! isset( $settings['templates'] ) ) {
            $settings['templates'] = array();
        }

        $settings['templates'][] = $template;

        // Save settings
        update_option( 'facetwp_settings', json_encode( $settings ), 'no' );

        // Reload helper settings
        FWP()->helper->settings = FWP()->helper->load_settings();

        return array(
            'success' => true,
            'template' => $template,
            'message' => "Template '$name' created successfully.",
        );
    }

    public function execute_update_template( $input ) {
        if ( ! $this->is_facetwp_active() ) {
            return new WP_Error( 'facetwp_not_active', 'FacetWP is not installed or active.', array( 'status' => 500 ) );
        }

        $name = sanitize_text_field( $input['name'] );

        // Get current settings
        $settings_json = get_option( 'facetwp_settings', '{}' );
        $settings = json_decode( $settings_json, true );

        if ( ! isset( $settings['templates'] ) ) {
            return new WP_Error( 'template_not_found', "Template '$name' not found.", array( 'status' => 404 ) );
        }

        // Find and update the template
        $found = false;
        foreach ( $settings['templates'] as $i => $template ) {
            if ( $template['name'] === $name ) {
                if ( ! empty( $input['label'] ) ) {
                    $settings['templates'][ $i ]['label'] = sanitize_text_field( $input['label'] );
                }
                if ( ! empty( $input['query'] ) ) {
                    $settings['templates'][ $i ]['query'] = $input['query'];
                }
                if ( ! empty( $input['template'] ) ) {
                    $settings['templates'][ $i ]['template'] = $input['template'];
                }

                $found = true;
                $updated_template = $settings['templates'][ $i ];
                break;
            }
        }

        if ( ! $found ) {
            return new WP_Error( 'template_not_found', "Template '$name' not found.", array( 'status' => 404 ) );
        }

        // Save settings
        update_option( 'facetwp_settings', json_encode( $settings ), 'no' );

        // Reload helper settings
        FWP()->helper->settings = FWP()->helper->load_settings();

        return array(
            'success' => true,
            'template' => $updated_template,
            'message' => "Template '$name' updated successfully.",
        );
    }

    public function execute_delete_template( $input ) {
        if ( ! $this->is_facetwp_active() ) {
            return new WP_Error( 'facetwp_not_active', 'FacetWP is not installed or active.', array( 'status' => 500 ) );
        }

        $name = sanitize_text_field( $input['name'] );

        // Get current settings
        $settings_json = get_option( 'facetwp_settings', '{}' );
        $settings = json_decode( $settings_json, true );

        if ( ! isset( $settings['templates'] ) ) {
            return new WP_Error( 'template_not_found', "Template '$name' not found.", array( 'status' => 404 ) );
        }

        // Find and remove the template
        $found = false;
        foreach ( $settings['templates'] as $i => $template ) {
            if ( $template['name'] === $name ) {
                unset( $settings['templates'][ $i ] );
                $settings['templates'] = array_values( $settings['templates'] );
                $found = true;
                break;
            }
        }

        if ( ! $found ) {
            return new WP_Error( 'template_not_found', "Template '$name' not found.", array( 'status' => 404 ) );
        }

        // Save settings
        update_option( 'facetwp_settings', json_encode( $settings ), 'no' );

        // Reload helper settings
        FWP()->helper->settings = FWP()->helper->load_settings();

        return array(
            'success' => true,
            'message' => "Template '$name' deleted successfully.",
        );
    }

    // =========================================================================
    // EXECUTE CALLBACKS - INDEX
    // =========================================================================

    public function execute_get_index_status( $input ) {
        if ( ! $this->is_facetwp_active() ) {
            return new WP_Error( 'facetwp_not_active', 'FacetWP is not installed or active.', array( 'status' => 500 ) );
        }

        $last_indexed = get_option( 'facetwp_last_indexed' );
        $row_counts = FWP()->helper->get_row_counts();
        $total_rows = array_sum( $row_counts );

        return array(
            'last_indexed' => $last_indexed ? date( 'Y-m-d H:i:s', $last_indexed ) : null,
            'last_indexed_ago' => $last_indexed ? human_time_diff( $last_indexed ) . ' ago' : 'never',
            'total_rows' => $total_rows,
            'facet_row_counts' => $row_counts,
            'indexable_post_types' => FWP()->helper->get_indexable_types(),
        );
    }

    public function execute_rebuild_index( $input ) {
        if ( ! $this->is_facetwp_active() ) {
            return new WP_Error( 'facetwp_not_active', 'FacetWP is not installed or active.', array( 'status' => 500 ) );
        }

        $post_id = isset( $input['post_id'] ) ? absint( $input['post_id'] ) : false;

        // Cancel any existing indexing
        update_option( 'facetwp_indexing_cancelled', 'no', 'no' );

        if ( $post_id ) {
            // Index single post
            FWP()->indexer->index( $post_id );
            return array(
                'success' => true,
                'message' => "Post $post_id has been re-indexed.",
            );
        } else {
            // Start full rebuild (note: this may take a while)
            // For large sites, this should be done via AJAX/cron
            FWP()->indexer->index();
            return array(
                'success' => true,
                'message' => 'Full index rebuild has been initiated.',
            );
        }
    }

    public function execute_get_index_progress( $input ) {
        if ( ! $this->is_facetwp_active() ) {
            return new WP_Error( 'facetwp_not_active', 'FacetWP is not installed or active.', array( 'status' => 500 ) );
        }

        $progress = FWP()->indexer->get_progress();
        
        return array(
            'progress' => $progress,
            'is_complete' => ( $progress == -1 || $progress >= 100 ),
            'is_indexing' => ( $progress > 0 && $progress < 100 ),
        );
    }

    public function execute_purge_index( $input ) {
        if ( ! $this->is_facetwp_active() ) {
            return new WP_Error( 'facetwp_not_active', 'FacetWP is not installed or active.', array( 'status' => 500 ) );
        }

        if ( empty( $input['confirm'] ) || $input['confirm'] !== true ) {
            return new WP_Error( 'confirmation_required', 'You must set confirm to true to purge the index.', array( 'status' => 400 ) );
        }

        global $wpdb;

        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}facetwp_index" );
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}facetwp_temp" );
        delete_option( 'facetwp_version' );
        delete_option( 'facetwp_indexing' );
        delete_option( 'facetwp_indexing_data' );

        return array(
            'success' => true,
            'message' => 'FacetWP index has been purged. Please re-index.',
        );
    }

    // =========================================================================
    // EXECUTE CALLBACKS - SETTINGS
    // =========================================================================

    public function execute_get_settings( $input ) {
        if ( ! $this->is_facetwp_active() ) {
            return new WP_Error( 'facetwp_not_active', 'FacetWP is not installed or active.', array( 'status' => 500 ) );
        }

        $settings = FWP()->helper->settings['settings'];

        // Don't expose license key
        unset( $settings['license_key'] );

        return array( 'settings' => $settings );
    }

    public function execute_update_settings( $input ) {
        if ( ! $this->is_facetwp_active() ) {
            return new WP_Error( 'facetwp_not_active', 'FacetWP is not installed or active.', array( 'status' => 500 ) );
        }

        // Get current settings
        $settings_json = get_option( 'facetwp_settings', '{}' );
        $settings = json_decode( $settings_json, true );

        if ( ! isset( $settings['settings'] ) ) {
            $settings['settings'] = array();
        }

        // Update provided settings
        $allowed_keys = array( 'prefix', 'thousands_separator', 'decimal_separator', 'load_jquery', 'debug_mode', 'gmaps_api_key' );
        
        foreach ( $allowed_keys as $key ) {
            if ( isset( $input[ $key ] ) ) {
                $settings['settings'][ $key ] = sanitize_text_field( $input[ $key ] );
            }
        }

        // Save settings
        update_option( 'facetwp_settings', json_encode( $settings ), 'no' );

        // Reload helper settings
        FWP()->helper->settings = FWP()->helper->load_settings();

        return array(
            'success' => true,
            'settings' => $settings['settings'],
            'message' => 'Settings updated successfully.',
        );
    }

    public function execute_get_info( $input ) {
        if ( ! $this->is_facetwp_active() ) {
            return new WP_Error( 'facetwp_not_active', 'FacetWP is not installed or active.', array( 'status' => 500 ) );
        }

        $facet_types = array_keys( FWP()->helper->get_facet_types() );
        $facets = FWP()->helper->get_facets();
        $templates = FWP()->helper->get_templates();

        return array(
            'version' => defined( 'FACETWP_VERSION' ) ? FACETWP_VERSION : 'unknown',
            'license_active' => FWP()->helper->is_license_active(),
            'facet_count' => count( $facets ),
            'template_count' => count( $templates ),
            'available_facet_types' => $facet_types,
            'indexable_post_types' => FWP()->helper->get_indexable_types(),
        );
    }

    public function execute_export_settings( $input ) {
        if ( ! $this->is_facetwp_active() ) {
            return new WP_Error( 'facetwp_not_active', 'FacetWP is not installed or active.', array( 'status' => 500 ) );
        }

        $settings_json = get_option( 'facetwp_settings', '{}' );
        $settings = json_decode( $settings_json, true );

        // Remove license key from export
        if ( isset( $settings['settings']['license_key'] ) ) {
            unset( $settings['settings']['license_key'] );
        }

        return array(
            'data' => $settings,
            'exported_at' => current_time( 'mysql' ),
        );
    }

    public function execute_import_settings( $input ) {
        if ( ! $this->is_facetwp_active() ) {
            return new WP_Error( 'facetwp_not_active', 'FacetWP is not installed or active.', array( 'status' => 500 ) );
        }

        $import_data = $input['data'];
        $overwrite = isset( $input['overwrite'] ) ? (bool) $input['overwrite'] : false;

        if ( $overwrite ) {
            // Complete replacement
            $settings = $import_data;
        } else {
            // Merge with existing
            $settings_json = get_option( 'facetwp_settings', '{}' );
            $settings = json_decode( $settings_json, true );

            // Merge facets
            if ( isset( $import_data['facets'] ) ) {
                foreach ( $import_data['facets'] as $facet ) {
                    $exists = false;
                    foreach ( $settings['facets'] as $i => $existing ) {
                        if ( $existing['name'] === $facet['name'] ) {
                            $settings['facets'][ $i ] = $facet;
                            $exists = true;
                            break;
                        }
                    }
                    if ( ! $exists ) {
                        $settings['facets'][] = $facet;
                    }
                }
            }

            // Merge templates
            if ( isset( $import_data['templates'] ) ) {
                foreach ( $import_data['templates'] as $template ) {
                    $exists = false;
                    foreach ( $settings['templates'] as $i => $existing ) {
                        if ( $existing['name'] === $template['name'] ) {
                            $settings['templates'][ $i ] = $template;
                            $exists = true;
                            break;
                        }
                    }
                    if ( ! $exists ) {
                        $settings['templates'][] = $template;
                    }
                }
            }

            // Merge settings
            if ( isset( $import_data['settings'] ) ) {
                $settings['settings'] = array_merge( $settings['settings'] ?? array(), $import_data['settings'] );
            }
        }

        // Save settings
        update_option( 'facetwp_settings', json_encode( $settings ), 'no' );

        // Reload helper settings
        FWP()->helper->settings = FWP()->helper->load_settings();

        return array(
            'success' => true,
            'message' => 'Settings imported successfully. Re-index may be required.',
            'facet_count' => count( $settings['facets'] ?? array() ),
            'template_count' => count( $settings['templates'] ?? array() ),
        );
    }

    // =========================================================================
    // EXECUTE CALLBACKS - DATA SOURCES
    // =========================================================================

    public function execute_list_data_sources( $input ) {
        if ( ! $this->is_facetwp_active() ) {
            return new WP_Error( 'facetwp_not_active', 'FacetWP is not installed or active.', array( 'status' => 500 ) );
        }

        $sources = FWP()->helper->get_data_sources();

        // Filter by type if specified
        if ( ! empty( $input['type'] ) ) {
            $type = sanitize_text_field( $input['type'] );
            if ( isset( $sources[ $type ] ) ) {
                $sources = array( $type => $sources[ $type ] );
            } else {
                $sources = array();
            }
        }

        return array(
            'sources' => $sources,
        );
    }

    public function execute_list_indexable_types( $input ) {
        if ( ! $this->is_facetwp_active() ) {
            return new WP_Error( 'facetwp_not_active', 'FacetWP is not installed or active.', array( 'status' => 500 ) );
        }

        $types = FWP()->helper->get_indexable_types();

        return array(
            'post_types' => $types,
            'count' => count( $types ),
        );
    }
}

// Initialize plugin
WordPress_FacetWP_Abilities_Plugin::instance();
