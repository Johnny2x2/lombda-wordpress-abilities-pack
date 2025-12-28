<?php
/**
 * Plugin Name: Database Abilities
 * Description: Provides database access abilities for AI agents via MCP. Query tables, view structure, access posts, options, and more.
 * Version: 1.0.0
 * Author: Santron
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Database_Abilities_Plugin {
    private static $instance = null;
    private static $mcp_meta = array(
        'show_in_rest' => true,
        'mcp'          => array( 'public' => true ),
    );
    private static $category = 'database';

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
                'label'       => 'Database',
                'description' => 'Database access and query abilities for exploring WordPress data.',
            )
        );
    }

    public function register_abilities() {
        $this->register_table_abilities();
        $this->register_query_abilities();
        $this->register_post_abilities();
        $this->register_option_abilities();
        $this->register_user_abilities();
        $this->register_meta_abilities();
    }

    // =========================================================================
    // TABLE STRUCTURE ABILITIES
    // =========================================================================

    private function register_table_abilities() {
        // List All Tables
        wp_register_ability( 'database/list-tables', array(
            'label' => 'List Database Tables',
            'description' => 'Get a list of all tables in the WordPress database with row counts.',
            'category' => self::$category,
            'input_schema' => array(
                'type' => 'object',
                'properties' => array(
                    'prefix_only' => array( 'type' => 'boolean', 'description' => 'Only show tables with WordPress prefix. Default: true.', 'default' => true ),
                ),
            ),
            'output_schema' => array( 'type' => 'object' ),
            'execute_callback' => array( $this, 'execute_list_tables' ),
            'permission_callback' => function() { return current_user_can( 'manage_options' ); },
            'meta' => self::$mcp_meta,
        ) );

        // Get Table Structure
        wp_register_ability( 'database/get-table-structure', array(
            'label' => 'Get Table Structure',
            'description' => 'Get the column structure, types, and keys for a specific table.',
            'category' => self::$category,
            'input_schema' => array(
                'type' => 'object',
                'properties' => array(
                    'table' => array( 'type' => 'string', 'description' => 'Table name (without prefix, e.g., "posts", "postmeta", "options").' ),
                    'full_name' => array( 'type' => 'boolean', 'description' => 'If true, table is the full name including prefix. Default: false.', 'default' => false ),
                ),
                'required' => array( 'table' ),
            ),
            'output_schema' => array( 'type' => 'object' ),
            'execute_callback' => array( $this, 'execute_get_table_structure' ),
            'permission_callback' => function() { return current_user_can( 'manage_options' ); },
            'meta' => self::$mcp_meta,
        ) );

        // Get Table Sample
        wp_register_ability( 'database/get-table-sample', array(
            'label' => 'Get Table Sample',
            'description' => 'Get sample rows from a table to understand its data.',
            'category' => self::$category,
            'input_schema' => array(
                'type' => 'object',
                'properties' => array(
                    'table' => array( 'type' => 'string', 'description' => 'Table name (without prefix).' ),
                    'limit' => array( 'type' => 'integer', 'description' => 'Number of rows. Default: 10. Max: 100.', 'default' => 10 ),
                    'offset' => array( 'type' => 'integer', 'description' => 'Offset for pagination. Default: 0.', 'default' => 0 ),
                    'order_by' => array( 'type' => 'string', 'description' => 'Column to order by.' ),
                    'order' => array( 'type' => 'string', 'description' => 'ASC or DESC. Default: DESC.', 'default' => 'DESC' ),
                    'full_name' => array( 'type' => 'boolean', 'description' => 'If true, table is the full name. Default: false.', 'default' => false ),
                ),
                'required' => array( 'table' ),
            ),
            'output_schema' => array( 'type' => 'object' ),
            'execute_callback' => array( $this, 'execute_get_table_sample' ),
            'permission_callback' => function() { return current_user_can( 'manage_options' ); },
            'meta' => self::$mcp_meta,
        ) );

        // Get Table Indexes
        wp_register_ability( 'database/get-table-indexes', array(
            'label' => 'Get Table Indexes',
            'description' => 'Get index information for a table.',
            'category' => self::$category,
            'input_schema' => array(
                'type' => 'object',
                'properties' => array(
                    'table' => array( 'type' => 'string', 'description' => 'Table name (without prefix).' ),
                    'full_name' => array( 'type' => 'boolean', 'description' => 'If true, table is the full name. Default: false.', 'default' => false ),
                ),
                'required' => array( 'table' ),
            ),
            'output_schema' => array( 'type' => 'object' ),
            'execute_callback' => array( $this, 'execute_get_table_indexes' ),
            'permission_callback' => function() { return current_user_can( 'manage_options' ); },
            'meta' => self::$mcp_meta,
        ) );
    }

    public function execute_list_tables( $input ) {
        global $wpdb;
        
        $prefix_only = isset( $input['prefix_only'] ) ? (bool) $input['prefix_only'] : true;
        
        $tables = $wpdb->get_results( "SHOW TABLE STATUS", ARRAY_A );
        
        $result = array();
        foreach ( $tables as $table ) {
            $name = $table['Name'];
            
            // Filter by prefix if requested
            if ( $prefix_only && strpos( $name, $wpdb->prefix ) !== 0 ) {
                continue;
            }
            
            $result[] = array(
                'name' => $name,
                'short_name' => str_replace( $wpdb->prefix, '', $name ),
                'rows' => (int) $table['Rows'],
                'size_mb' => round( ( $table['Data_length'] + $table['Index_length'] ) / 1024 / 1024, 2 ),
                'engine' => $table['Engine'],
                'collation' => $table['Collation'],
            );
        }
        
        return array(
            'tables' => $result,
            'count' => count( $result ),
            'prefix' => $wpdb->prefix,
        );
    }

    public function execute_get_table_structure( $input ) {
        global $wpdb;
        
        $table = $this->get_table_name( $input['table'], isset( $input['full_name'] ) && $input['full_name'] );
        
        if ( ! $this->table_exists( $table ) ) {
            return new WP_Error( 'table_not_found', "Table '$table' not found." );
        }
        
        $columns = $wpdb->get_results( "DESCRIBE `$table`", ARRAY_A );
        
        $structure = array();
        foreach ( $columns as $col ) {
            $structure[] = array(
                'name' => $col['Field'],
                'type' => $col['Type'],
                'null' => $col['Null'] === 'YES',
                'key' => $col['Key'],
                'default' => $col['Default'],
                'extra' => $col['Extra'],
            );
        }
        
        // Get row count
        $count = $wpdb->get_var( "SELECT COUNT(*) FROM `$table`" );
        
        return array(
            'table' => $table,
            'columns' => $structure,
            'column_count' => count( $structure ),
            'row_count' => (int) $count,
        );
    }

    public function execute_get_table_sample( $input ) {
        global $wpdb;
        
        $table = $this->get_table_name( $input['table'], isset( $input['full_name'] ) && $input['full_name'] );
        
        if ( ! $this->table_exists( $table ) ) {
            return new WP_Error( 'table_not_found', "Table '$table' not found." );
        }
        
        $limit = min( isset( $input['limit'] ) ? absint( $input['limit'] ) : 10, 100 );
        $offset = isset( $input['offset'] ) ? absint( $input['offset'] ) : 0;
        $order = isset( $input['order'] ) && strtoupper( $input['order'] ) === 'ASC' ? 'ASC' : 'DESC';
        
        $order_clause = '';
        if ( isset( $input['order_by'] ) ) {
            $order_by = sanitize_key( $input['order_by'] );
            $order_clause = "ORDER BY `$order_by` $order";
        }
        
        $rows = $wpdb->get_results( 
            $wpdb->prepare( "SELECT * FROM `$table` $order_clause LIMIT %d OFFSET %d", $limit, $offset ),
            ARRAY_A 
        );
        
        $total = $wpdb->get_var( "SELECT COUNT(*) FROM `$table`" );
        
        return array(
            'table' => $table,
            'rows' => $rows,
            'count' => count( $rows ),
            'total' => (int) $total,
            'offset' => $offset,
            'limit' => $limit,
        );
    }

    public function execute_get_table_indexes( $input ) {
        global $wpdb;
        
        $table = $this->get_table_name( $input['table'], isset( $input['full_name'] ) && $input['full_name'] );
        
        if ( ! $this->table_exists( $table ) ) {
            return new WP_Error( 'table_not_found', "Table '$table' not found." );
        }
        
        $indexes = $wpdb->get_results( "SHOW INDEX FROM `$table`", ARRAY_A );
        
        $result = array();
        foreach ( $indexes as $idx ) {
            $key_name = $idx['Key_name'];
            if ( ! isset( $result[ $key_name ] ) ) {
                $result[ $key_name ] = array(
                    'name' => $key_name,
                    'unique' => $idx['Non_unique'] === '0',
                    'columns' => array(),
                );
            }
            $result[ $key_name ]['columns'][] = $idx['Column_name'];
        }
        
        return array(
            'table' => $table,
            'indexes' => array_values( $result ),
        );
    }

    // =========================================================================
    // QUERY ABILITIES
    // =========================================================================

    private function register_query_abilities() {
        // Run SELECT Query
        wp_register_ability( 'database/query', array(
            'label' => 'Run Database Query',
            'description' => 'Execute a SELECT query on the database. Only SELECT queries are allowed for safety.',
            'category' => self::$category,
            'input_schema' => array(
                'type' => 'object',
                'properties' => array(
                    'sql' => array( 'type' => 'string', 'description' => 'The SELECT SQL query to execute. Use {prefix} as placeholder for table prefix.' ),
                    'limit' => array( 'type' => 'integer', 'description' => 'Override LIMIT if not specified in query. Default: 100.', 'default' => 100 ),
                ),
                'required' => array( 'sql' ),
            ),
            'output_schema' => array( 'type' => 'object' ),
            'execute_callback' => array( $this, 'execute_query' ),
            'permission_callback' => function() { return current_user_can( 'manage_options' ); },
            'meta' => self::$mcp_meta,
        ) );

        // Search Across Tables
        wp_register_ability( 'database/search', array(
            'label' => 'Search Database',
            'description' => 'Search for a value across multiple tables and columns.',
            'category' => self::$category,
            'input_schema' => array(
                'type' => 'object',
                'properties' => array(
                    'search' => array( 'type' => 'string', 'description' => 'The value to search for.' ),
                    'tables' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'description' => 'Tables to search (without prefix). If empty, searches common tables.' ),
                    'exact' => array( 'type' => 'boolean', 'description' => 'Exact match instead of LIKE. Default: false.', 'default' => false ),
                    'limit' => array( 'type' => 'integer', 'description' => 'Max results per table. Default: 10.', 'default' => 10 ),
                ),
                'required' => array( 'search' ),
            ),
            'output_schema' => array( 'type' => 'object' ),
            'execute_callback' => array( $this, 'execute_search' ),
            'permission_callback' => function() { return current_user_can( 'manage_options' ); },
            'meta' => self::$mcp_meta,
        ) );

        // Get Database Info
        wp_register_ability( 'database/info', array(
            'label' => 'Get Database Info',
            'description' => 'Get general database information including version, size, and configuration.',
            'category' => self::$category,
            'input_schema' => array( 'type' => 'object', 'properties' => array() ),
            'output_schema' => array( 'type' => 'object' ),
            'execute_callback' => array( $this, 'execute_get_info' ),
            'permission_callback' => function() { return current_user_can( 'manage_options' ); },
            'meta' => self::$mcp_meta,
        ) );
    }

    public function execute_query( $input ) {
        global $wpdb;
        
        $sql = $input['sql'];
        
        // Security: Only allow SELECT queries
        $sql_trimmed = strtoupper( trim( $sql ) );
        if ( strpos( $sql_trimmed, 'SELECT' ) !== 0 && strpos( $sql_trimmed, 'SHOW' ) !== 0 && strpos( $sql_trimmed, 'DESCRIBE' ) !== 0 ) {
            return new WP_Error( 'invalid_query', 'Only SELECT, SHOW, and DESCRIBE queries are allowed.' );
        }
        
        // Block dangerous keywords
        $dangerous = array( 'INSERT', 'UPDATE', 'DELETE', 'DROP', 'TRUNCATE', 'ALTER', 'CREATE', 'REPLACE', 'GRANT', 'REVOKE' );
        foreach ( $dangerous as $keyword ) {
            if ( preg_match( '/\b' . $keyword . '\b/i', $sql ) ) {
                return new WP_Error( 'invalid_query', "Query contains forbidden keyword: $keyword" );
            }
        }
        
        // Replace {prefix} placeholder
        $sql = str_replace( '{prefix}', $wpdb->prefix, $sql );
        
        // Add LIMIT if not present
        if ( stripos( $sql, 'LIMIT' ) === false ) {
            $limit = isset( $input['limit'] ) ? absint( $input['limit'] ) : 100;
            $sql .= " LIMIT $limit";
        }
        
        $results = $wpdb->get_results( $sql, ARRAY_A );
        
        if ( $wpdb->last_error ) {
            return new WP_Error( 'query_error', $wpdb->last_error );
        }
        
        return array(
            'results' => $results,
            'count' => count( $results ),
            'query' => $sql,
        );
    }

    public function execute_search( $input ) {
        global $wpdb;
        
        $search = $input['search'];
        $tables = isset( $input['tables'] ) && is_array( $input['tables'] ) ? $input['tables'] : array( 'posts', 'postmeta', 'options', 'terms', 'termmeta' );
        $exact = isset( $input['exact'] ) ? (bool) $input['exact'] : false;
        $limit = isset( $input['limit'] ) ? absint( $input['limit'] ) : 10;
        
        $results = array();
        
        foreach ( $tables as $table_name ) {
            $table = $wpdb->prefix . sanitize_key( $table_name );
            
            if ( ! $this->table_exists( $table ) ) {
                continue;
            }
            
            // Get text columns
            $columns = $wpdb->get_results( "DESCRIBE `$table`", ARRAY_A );
            $text_columns = array();
            foreach ( $columns as $col ) {
                if ( preg_match( '/(char|text|varchar|blob)/i', $col['Type'] ) ) {
                    $text_columns[] = $col['Field'];
                }
            }
            
            if ( empty( $text_columns ) ) {
                continue;
            }
            
            // Build search query
            $conditions = array();
            foreach ( $text_columns as $col ) {
                if ( $exact ) {
                    $conditions[] = $wpdb->prepare( "`$col` = %s", $search );
                } else {
                    $conditions[] = $wpdb->prepare( "`$col` LIKE %s", '%' . $wpdb->esc_like( $search ) . '%' );
                }
            }
            
            $where = implode( ' OR ', $conditions );
            $rows = $wpdb->get_results( "SELECT * FROM `$table` WHERE $where LIMIT $limit", ARRAY_A );
            
            if ( ! empty( $rows ) ) {
                $results[ $table_name ] = array(
                    'table' => $table,
                    'rows' => $rows,
                    'count' => count( $rows ),
                    'columns_searched' => $text_columns,
                );
            }
        }
        
        return array(
            'search' => $search,
            'results' => $results,
            'tables_searched' => count( $tables ),
            'tables_with_matches' => count( $results ),
        );
    }

    public function execute_get_info( $input ) {
        global $wpdb;
        
        $version = $wpdb->get_var( "SELECT VERSION()" );
        $size = $wpdb->get_row( "SELECT 
            SUM(data_length + index_length) / 1024 / 1024 AS size_mb,
            COUNT(*) as table_count
            FROM information_schema.tables 
            WHERE table_schema = DATABASE()", ARRAY_A );
        
        return array(
            'version' => $version,
            'database' => DB_NAME,
            'host' => DB_HOST,
            'prefix' => $wpdb->prefix,
            'charset' => $wpdb->charset,
            'collate' => $wpdb->collate,
            'size_mb' => round( (float) $size['size_mb'], 2 ),
            'table_count' => (int) $size['table_count'],
        );
    }

    // =========================================================================
    // POST ABILITIES
    // =========================================================================

    private function register_post_abilities() {
        // Get Posts with Meta
        wp_register_ability( 'database/get-posts', array(
            'label' => 'Get Posts with Meta',
            'description' => 'Query posts with their meta data. More flexible than WP_Query for database exploration.',
            'category' => self::$category,
            'input_schema' => array(
                'type' => 'object',
                'properties' => array(
                    'post_type' => array( 'type' => 'string', 'description' => 'Post type to query. Default: any.' ),
                    'post_status' => array( 'type' => 'string', 'description' => 'Post status. Default: any.' ),
                    'limit' => array( 'type' => 'integer', 'description' => 'Number of posts. Default: 20.', 'default' => 20 ),
                    'offset' => array( 'type' => 'integer', 'description' => 'Offset. Default: 0.', 'default' => 0 ),
                    'include_meta' => array( 'type' => 'boolean', 'description' => 'Include all meta data. Default: true.', 'default' => true ),
                    'meta_keys' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'description' => 'Specific meta keys to include.' ),
                    'search' => array( 'type' => 'string', 'description' => 'Search in title and content.' ),
                    'orderby' => array( 'type' => 'string', 'description' => 'Column to order by. Default: ID.', 'default' => 'ID' ),
                    'order' => array( 'type' => 'string', 'description' => 'ASC or DESC. Default: DESC.', 'default' => 'DESC' ),
                ),
            ),
            'output_schema' => array( 'type' => 'object' ),
            'execute_callback' => array( $this, 'execute_get_posts' ),
            'permission_callback' => function() { return current_user_can( 'edit_posts' ); },
            'meta' => self::$mcp_meta,
        ) );

        // Get Post Types Summary
        wp_register_ability( 'database/get-post-types', array(
            'label' => 'Get Post Types Summary',
            'description' => 'Get a summary of all post types with counts by status.',
            'category' => self::$category,
            'input_schema' => array( 'type' => 'object', 'properties' => array() ),
            'output_schema' => array( 'type' => 'object' ),
            'execute_callback' => array( $this, 'execute_get_post_types' ),
            'permission_callback' => function() { return current_user_can( 'edit_posts' ); },
            'meta' => self::$mcp_meta,
        ) );

        // Get Post Meta Keys
        wp_register_ability( 'database/get-meta-keys', array(
            'label' => 'Get Meta Keys',
            'description' => 'Get all unique meta keys used for a post type.',
            'category' => self::$category,
            'input_schema' => array(
                'type' => 'object',
                'properties' => array(
                    'post_type' => array( 'type' => 'string', 'description' => 'Post type to analyze. Default: all.' ),
                    'include_counts' => array( 'type' => 'boolean', 'description' => 'Include usage counts. Default: true.', 'default' => true ),
                ),
            ),
            'output_schema' => array( 'type' => 'object' ),
            'execute_callback' => array( $this, 'execute_get_meta_keys' ),
            'permission_callback' => function() { return current_user_can( 'edit_posts' ); },
            'meta' => self::$mcp_meta,
        ) );
    }

    public function execute_get_posts( $input ) {
        global $wpdb;
        
        $limit = min( isset( $input['limit'] ) ? absint( $input['limit'] ) : 20, 100 );
        $offset = isset( $input['offset'] ) ? absint( $input['offset'] ) : 0;
        $include_meta = isset( $input['include_meta'] ) ? (bool) $input['include_meta'] : true;
        $order = isset( $input['order'] ) && strtoupper( $input['order'] ) === 'ASC' ? 'ASC' : 'DESC';
        $orderby = isset( $input['orderby'] ) ? sanitize_key( $input['orderby'] ) : 'ID';
        
        $where = array( "1=1" );
        
        if ( isset( $input['post_type'] ) && $input['post_type'] !== 'any' ) {
            $where[] = $wpdb->prepare( "post_type = %s", $input['post_type'] );
        }
        
        if ( isset( $input['post_status'] ) && $input['post_status'] !== 'any' ) {
            $where[] = $wpdb->prepare( "post_status = %s", $input['post_status'] );
        }
        
        if ( isset( $input['search'] ) && ! empty( $input['search'] ) ) {
            $search = '%' . $wpdb->esc_like( $input['search'] ) . '%';
            $where[] = $wpdb->prepare( "(post_title LIKE %s OR post_content LIKE %s)", $search, $search );
        }
        
        $where_clause = implode( ' AND ', $where );
        
        $posts = $wpdb->get_results(
            "SELECT ID, post_title, post_type, post_status, post_date, post_modified, post_author 
             FROM {$wpdb->posts} 
             WHERE $where_clause 
             ORDER BY `$orderby` $order 
             LIMIT $limit OFFSET $offset",
            ARRAY_A
        );
        
        // Get total count
        $total = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE $where_clause" );
        
        // Add meta if requested
        if ( $include_meta && ! empty( $posts ) ) {
            $post_ids = wp_list_pluck( $posts, 'ID' );
            $meta_keys = isset( $input['meta_keys'] ) && is_array( $input['meta_keys'] ) ? $input['meta_keys'] : null;
            
            foreach ( $posts as &$post ) {
                $meta = get_post_meta( $post['ID'] );
                
                if ( $meta_keys ) {
                    $post['meta'] = array_intersect_key( $meta, array_flip( $meta_keys ) );
                } else {
                    // Filter out serialized/complex data for readability
                    $post['meta'] = array();
                    foreach ( $meta as $key => $values ) {
                        $post['meta'][ $key ] = count( $values ) === 1 ? $values[0] : $values;
                    }
                }
            }
        }
        
        return array(
            'posts' => $posts,
            'count' => count( $posts ),
            'total' => (int) $total,
            'offset' => $offset,
            'limit' => $limit,
        );
    }

    public function execute_get_post_types( $input ) {
        global $wpdb;
        
        $results = $wpdb->get_results(
            "SELECT post_type, post_status, COUNT(*) as count 
             FROM {$wpdb->posts} 
             GROUP BY post_type, post_status 
             ORDER BY post_type, post_status",
            ARRAY_A
        );
        
        $summary = array();
        foreach ( $results as $row ) {
            $type = $row['post_type'];
            if ( ! isset( $summary[ $type ] ) ) {
                $summary[ $type ] = array(
                    'post_type' => $type,
                    'total' => 0,
                    'statuses' => array(),
                );
            }
            $summary[ $type ]['statuses'][ $row['post_status'] ] = (int) $row['count'];
            $summary[ $type ]['total'] += (int) $row['count'];
        }
        
        return array(
            'post_types' => array_values( $summary ),
            'count' => count( $summary ),
        );
    }

    public function execute_get_meta_keys( $input ) {
        global $wpdb;
        
        $include_counts = isset( $input['include_counts'] ) ? (bool) $input['include_counts'] : true;
        
        $where = "";
        if ( isset( $input['post_type'] ) && ! empty( $input['post_type'] ) ) {
            $where = $wpdb->prepare( 
                "WHERE pm.post_id IN (SELECT ID FROM {$wpdb->posts} WHERE post_type = %s)", 
                $input['post_type'] 
            );
        }
        
        if ( $include_counts ) {
            $results = $wpdb->get_results(
                "SELECT pm.meta_key, COUNT(*) as count 
                 FROM {$wpdb->postmeta} pm 
                 $where
                 GROUP BY pm.meta_key 
                 ORDER BY count DESC",
                ARRAY_A
            );
        } else {
            $results = $wpdb->get_col(
                "SELECT DISTINCT pm.meta_key 
                 FROM {$wpdb->postmeta} pm 
                 $where
                 ORDER BY pm.meta_key"
            );
        }
        
        return array(
            'meta_keys' => $results,
            'count' => count( $results ),
            'post_type' => $input['post_type'] ?? 'all',
        );
    }

    // =========================================================================
    // OPTIONS ABILITIES
    // =========================================================================

    private function register_option_abilities() {
        // Get Options
        wp_register_ability( 'database/get-options', array(
            'label' => 'Get WordPress Options',
            'description' => 'Query WordPress options table. Can search or filter by prefix.',
            'category' => self::$category,
            'input_schema' => array(
                'type' => 'object',
                'properties' => array(
                    'search' => array( 'type' => 'string', 'description' => 'Search option names.' ),
                    'prefix' => array( 'type' => 'string', 'description' => 'Filter by option name prefix (e.g., "siteurl", "elementor_").' ),
                    'names' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'description' => 'Specific option names to retrieve.' ),
                    'limit' => array( 'type' => 'integer', 'description' => 'Max results. Default: 50.', 'default' => 50 ),
                    'autoload' => array( 'type' => 'string', 'description' => 'Filter by autoload: yes, no, or all. Default: all.' ),
                ),
            ),
            'output_schema' => array( 'type' => 'object' ),
            'execute_callback' => array( $this, 'execute_get_options' ),
            'permission_callback' => function() { return current_user_can( 'manage_options' ); },
            'meta' => self::$mcp_meta,
        ) );

        // Get Single Option
        wp_register_ability( 'database/get-option', array(
            'label' => 'Get Single Option',
            'description' => 'Get a single WordPress option value.',
            'category' => self::$category,
            'input_schema' => array(
                'type' => 'object',
                'properties' => array(
                    'name' => array( 'type' => 'string', 'description' => 'Option name.' ),
                ),
                'required' => array( 'name' ),
            ),
            'output_schema' => array( 'type' => 'object' ),
            'execute_callback' => array( $this, 'execute_get_option' ),
            'permission_callback' => function() { return current_user_can( 'manage_options' ); },
            'meta' => self::$mcp_meta,
        ) );

        // Set Option
        wp_register_ability( 'database/set-option', array(
            'label' => 'Set WordPress Option',
            'description' => 'Set or update a WordPress option value.',
            'category' => self::$category,
            'input_schema' => array(
                'type' => 'object',
                'properties' => array(
                    'name' => array( 'type' => 'string', 'description' => 'Option name.' ),
                    'value' => array( 'description' => 'Option value (string, number, boolean, array, or object).' ),
                    'autoload' => array( 'type' => 'boolean', 'description' => 'Whether to autoload. Default: true.', 'default' => true ),
                ),
                'required' => array( 'name', 'value' ),
            ),
            'output_schema' => array( 'type' => 'object' ),
            'execute_callback' => array( $this, 'execute_set_option' ),
            'permission_callback' => function() { return current_user_can( 'manage_options' ); },
            'meta' => self::$mcp_meta,
        ) );
    }

    public function execute_get_options( $input ) {
        global $wpdb;
        
        $limit = min( isset( $input['limit'] ) ? absint( $input['limit'] ) : 50, 500 );
        
        $where = array( "1=1" );
        
        if ( isset( $input['search'] ) && ! empty( $input['search'] ) ) {
            $where[] = $wpdb->prepare( "option_name LIKE %s", '%' . $wpdb->esc_like( $input['search'] ) . '%' );
        }
        
        if ( isset( $input['prefix'] ) && ! empty( $input['prefix'] ) ) {
            $where[] = $wpdb->prepare( "option_name LIKE %s", $wpdb->esc_like( $input['prefix'] ) . '%' );
        }
        
        if ( isset( $input['names'] ) && is_array( $input['names'] ) ) {
            $placeholders = implode( ',', array_fill( 0, count( $input['names'] ), '%s' ) );
            $where[] = $wpdb->prepare( "option_name IN ($placeholders)", $input['names'] );
        }
        
        if ( isset( $input['autoload'] ) && in_array( $input['autoload'], array( 'yes', 'no' ) ) ) {
            $where[] = $wpdb->prepare( "autoload = %s", $input['autoload'] );
        }
        
        $where_clause = implode( ' AND ', $where );
        
        $options = $wpdb->get_results(
            "SELECT option_name, option_value, autoload 
             FROM {$wpdb->options} 
             WHERE $where_clause 
             ORDER BY option_name 
             LIMIT $limit",
            ARRAY_A
        );
        
        // Try to unserialize values
        foreach ( $options as &$option ) {
            $unserialized = maybe_unserialize( $option['option_value'] );
            $option['value'] = $unserialized;
            $option['is_serialized'] = $option['option_value'] !== $unserialized;
            unset( $option['option_value'] );
        }
        
        return array(
            'options' => $options,
            'count' => count( $options ),
        );
    }

    public function execute_get_option( $input ) {
        $name = sanitize_key( $input['name'] );
        $value = get_option( $name, null );
        
        if ( $value === null ) {
            return new WP_Error( 'option_not_found', "Option '$name' not found." );
        }
        
        return array(
            'name' => $name,
            'value' => $value,
            'type' => gettype( $value ),
        );
    }

    public function execute_set_option( $input ) {
        $name = sanitize_text_field( $input['name'] );
        $value = $input['value'];
        $autoload = isset( $input['autoload'] ) ? ( $input['autoload'] ? 'yes' : 'no' ) : 'yes';
        
        $old_value = get_option( $name );
        $result = update_option( $name, $value, $autoload );
        
        return array(
            'success' => true,
            'name' => $name,
            'previous_value' => $old_value,
            'new_value' => $value,
            'created' => $old_value === false,
        );
    }

    // =========================================================================
    // USER ABILITIES
    // =========================================================================

    private function register_user_abilities() {
        // Get Users
        wp_register_ability( 'database/get-users', array(
            'label' => 'Get Users',
            'description' => 'Query WordPress users with their meta data.',
            'category' => self::$category,
            'input_schema' => array(
                'type' => 'object',
                'properties' => array(
                    'role' => array( 'type' => 'string', 'description' => 'Filter by role.' ),
                    'search' => array( 'type' => 'string', 'description' => 'Search by login, email, or display name.' ),
                    'limit' => array( 'type' => 'integer', 'description' => 'Max results. Default: 20.', 'default' => 20 ),
                    'include_meta' => array( 'type' => 'boolean', 'description' => 'Include user meta. Default: false.', 'default' => false ),
                ),
            ),
            'output_schema' => array( 'type' => 'object' ),
            'execute_callback' => array( $this, 'execute_get_users' ),
            'permission_callback' => function() { return current_user_can( 'list_users' ); },
            'meta' => self::$mcp_meta,
        ) );
    }

    public function execute_get_users( $input ) {
        $args = array(
            'number' => min( isset( $input['limit'] ) ? absint( $input['limit'] ) : 20, 100 ),
        );
        
        if ( isset( $input['role'] ) && ! empty( $input['role'] ) ) {
            $args['role'] = sanitize_text_field( $input['role'] );
        }
        
        if ( isset( $input['search'] ) && ! empty( $input['search'] ) ) {
            $args['search'] = '*' . sanitize_text_field( $input['search'] ) . '*';
        }
        
        $users = get_users( $args );
        $include_meta = isset( $input['include_meta'] ) ? (bool) $input['include_meta'] : false;
        
        $result = array();
        foreach ( $users as $user ) {
            $user_data = array(
                'ID' => $user->ID,
                'user_login' => $user->user_login,
                'user_email' => $user->user_email,
                'display_name' => $user->display_name,
                'roles' => $user->roles,
                'registered' => $user->user_registered,
            );
            
            if ( $include_meta ) {
                $user_data['meta'] = get_user_meta( $user->ID );
            }
            
            $result[] = $user_data;
        }
        
        return array(
            'users' => $result,
            'count' => count( $result ),
        );
    }

    // =========================================================================
    // META ABILITIES
    // =========================================================================

    private function register_meta_abilities() {
        // Get Post Meta
        wp_register_ability( 'database/get-post-meta', array(
            'label' => 'Get Post Meta',
            'description' => 'Get all meta data for a specific post.',
            'category' => self::$category,
            'input_schema' => array(
                'type' => 'object',
                'properties' => array(
                    'post_id' => array( 'type' => 'integer', 'description' => 'Post ID.' ),
                    'meta_key' => array( 'type' => 'string', 'description' => 'Optional: Get a specific meta key.' ),
                ),
                'required' => array( 'post_id' ),
            ),
            'output_schema' => array( 'type' => 'object' ),
            'execute_callback' => array( $this, 'execute_get_post_meta' ),
            'permission_callback' => function() { return current_user_can( 'edit_posts' ); },
            'meta' => self::$mcp_meta,
        ) );

        // Set Post Meta
        wp_register_ability( 'database/set-post-meta', array(
            'label' => 'Set Post Meta',
            'description' => 'Set or update a post meta value.',
            'category' => self::$category,
            'input_schema' => array(
                'type' => 'object',
                'properties' => array(
                    'post_id' => array( 'type' => 'integer', 'description' => 'Post ID.' ),
                    'meta_key' => array( 'type' => 'string', 'description' => 'Meta key.' ),
                    'meta_value' => array( 'description' => 'Meta value.' ),
                ),
                'required' => array( 'post_id', 'meta_key', 'meta_value' ),
            ),
            'output_schema' => array( 'type' => 'object' ),
            'execute_callback' => array( $this, 'execute_set_post_meta' ),
            'permission_callback' => function() { return current_user_can( 'edit_posts' ); },
            'meta' => self::$mcp_meta,
        ) );

        // Delete Post Meta
        wp_register_ability( 'database/delete-post-meta', array(
            'label' => 'Delete Post Meta',
            'description' => 'Delete a post meta value.',
            'category' => self::$category,
            'input_schema' => array(
                'type' => 'object',
                'properties' => array(
                    'post_id' => array( 'type' => 'integer', 'description' => 'Post ID.' ),
                    'meta_key' => array( 'type' => 'string', 'description' => 'Meta key to delete.' ),
                ),
                'required' => array( 'post_id', 'meta_key' ),
            ),
            'output_schema' => array( 'type' => 'object' ),
            'execute_callback' => array( $this, 'execute_delete_post_meta' ),
            'permission_callback' => function() { return current_user_can( 'edit_posts' ); },
            'meta' => self::$mcp_meta,
        ) );

        // Analyze Meta Values
        wp_register_ability( 'database/analyze-meta', array(
            'label' => 'Analyze Meta Values',
            'description' => 'Get statistics and sample values for a meta key across posts.',
            'category' => self::$category,
            'input_schema' => array(
                'type' => 'object',
                'properties' => array(
                    'meta_key' => array( 'type' => 'string', 'description' => 'Meta key to analyze.' ),
                    'post_type' => array( 'type' => 'string', 'description' => 'Filter by post type.' ),
                    'sample_size' => array( 'type' => 'integer', 'description' => 'Number of sample values. Default: 10.', 'default' => 10 ),
                ),
                'required' => array( 'meta_key' ),
            ),
            'output_schema' => array( 'type' => 'object' ),
            'execute_callback' => array( $this, 'execute_analyze_meta' ),
            'permission_callback' => function() { return current_user_can( 'edit_posts' ); },
            'meta' => self::$mcp_meta,
        ) );
    }

    public function execute_get_post_meta( $input ) {
        $post_id = absint( $input['post_id'] );
        
        if ( ! get_post( $post_id ) ) {
            return new WP_Error( 'post_not_found', 'Post not found.' );
        }
        
        if ( isset( $input['meta_key'] ) && ! empty( $input['meta_key'] ) ) {
            $value = get_post_meta( $post_id, $input['meta_key'], true );
            return array(
                'post_id' => $post_id,
                'meta_key' => $input['meta_key'],
                'value' => $value,
            );
        }
        
        $meta = get_post_meta( $post_id );
        $formatted = array();
        foreach ( $meta as $key => $values ) {
            $formatted[ $key ] = count( $values ) === 1 ? maybe_unserialize( $values[0] ) : array_map( 'maybe_unserialize', $values );
        }
        
        return array(
            'post_id' => $post_id,
            'meta' => $formatted,
            'count' => count( $formatted ),
        );
    }

    public function execute_set_post_meta( $input ) {
        $post_id = absint( $input['post_id'] );
        $meta_key = sanitize_text_field( $input['meta_key'] );
        $meta_value = $input['meta_value'];
        
        if ( ! get_post( $post_id ) ) {
            return new WP_Error( 'post_not_found', 'Post not found.' );
        }
        
        $old_value = get_post_meta( $post_id, $meta_key, true );
        $result = update_post_meta( $post_id, $meta_key, $meta_value );
        
        return array(
            'success' => $result !== false,
            'post_id' => $post_id,
            'meta_key' => $meta_key,
            'previous_value' => $old_value,
            'new_value' => $meta_value,
        );
    }

    public function execute_delete_post_meta( $input ) {
        $post_id = absint( $input['post_id'] );
        $meta_key = sanitize_text_field( $input['meta_key'] );
        
        if ( ! get_post( $post_id ) ) {
            return new WP_Error( 'post_not_found', 'Post not found.' );
        }
        
        $old_value = get_post_meta( $post_id, $meta_key, true );
        $result = delete_post_meta( $post_id, $meta_key );
        
        return array(
            'success' => $result,
            'post_id' => $post_id,
            'meta_key' => $meta_key,
            'deleted_value' => $old_value,
        );
    }

    public function execute_analyze_meta( $input ) {
        global $wpdb;
        
        $meta_key = sanitize_text_field( $input['meta_key'] );
        $sample_size = isset( $input['sample_size'] ) ? absint( $input['sample_size'] ) : 10;
        
        $join = "";
        $where = "";
        if ( isset( $input['post_type'] ) && ! empty( $input['post_type'] ) ) {
            $join = "INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID";
            $where = $wpdb->prepare( "AND p.post_type = %s", $input['post_type'] );
        }
        
        // Get count
        $count = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} pm $join WHERE pm.meta_key = %s $where",
            $meta_key
        ) );
        
        // Get distinct values count
        $distinct = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(DISTINCT pm.meta_value) FROM {$wpdb->postmeta} pm $join WHERE pm.meta_key = %s $where",
            $meta_key
        ) );
        
        // Get sample values
        $samples = $wpdb->get_col( $wpdb->prepare(
            "SELECT DISTINCT pm.meta_value FROM {$wpdb->postmeta} pm $join WHERE pm.meta_key = %s $where LIMIT %d",
            $meta_key, $sample_size
        ) );
        
        // Try to detect value type
        $value_types = array();
        foreach ( $samples as $sample ) {
            $unserialized = maybe_unserialize( $sample );
            $type = gettype( $unserialized );
            $value_types[ $type ] = ( $value_types[ $type ] ?? 0 ) + 1;
        }
        
        return array(
            'meta_key' => $meta_key,
            'total_count' => (int) $count,
            'distinct_values' => (int) $distinct,
            'value_types' => $value_types,
            'sample_values' => array_map( 'maybe_unserialize', $samples ),
            'post_type' => $input['post_type'] ?? 'all',
        );
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    private function get_table_name( $table, $is_full_name = false ) {
        global $wpdb;
        
        if ( $is_full_name ) {
            return $table;
        }
        
        return $wpdb->prefix . sanitize_key( $table );
    }

    private function table_exists( $table ) {
        global $wpdb;
        return $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) ) === $table;
    }
}

// Initialize
Database_Abilities_Plugin::instance();
