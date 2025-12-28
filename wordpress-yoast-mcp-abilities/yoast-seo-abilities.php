<?php
/**
 * Plugin Name: Yoast SEO Abilities
 * Description: Exposes Yoast SEO and RankMath functionality as WordPress Abilities for AI agents via MCP. Includes SEO metadata management and content verification.
 * Version: 1.0.0
 * Author: Lombda LLC
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Yoast_SEO_Abilities_Plugin {
    private static $instance = null;
    private static $mcp_meta = array(
        'show_in_rest' => true,
        'mcp'          => array( 'public' => true ),
    );
    private static $category = 'seo';

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
                'label'       => 'SEO',
                'description' => 'Abilities for managing SEO metadata with Yoast SEO and RankMath plugins.',
            )
        );
    }

    public function register_abilities() {
        $this->register_yoast_abilities();
        $this->register_rankmath_abilities();
        $this->register_seo_verification_abilities();
    }

    // =========================================================================
    // YOAST SEO ABILITIES
    // =========================================================================

    private function register_yoast_abilities() {
        // Set Yoast SEO Metadata
        wp_register_ability( 'seo/set-yoast-seo', array(
            'label' => 'Set Yoast SEO Metadata',
            'description' => 'Set Yoast SEO metadata for a post including title, meta description, focus keyword, Open Graph, and Twitter cards.',
            'category' => self::$category,
            'input_schema' => array(
                'type' => 'object',
                'properties' => array(
                    'post_id' => array( 'type' => 'integer', 'description' => 'The post ID to set SEO for.' ),
                    'title' => array( 'type' => 'string', 'description' => 'SEO title (appears in search results).' ),
                    'meta_description' => array( 'type' => 'string', 'description' => 'Meta description (150-160 chars recommended).' ),
                    'focus_keyword' => array( 'type' => 'string', 'description' => 'Primary focus keyword for SEO analysis.' ),
                    'related_keyphrases' => array( 'type' => 'array', 'description' => 'Additional related keyphrases.' ),
                    'canonical_url' => array( 'type' => 'string', 'description' => 'Canonical URL for duplicate content.' ),
                    'noindex' => array( 'type' => 'boolean', 'description' => 'Set to true to prevent indexing.' ),
                    'nofollow' => array( 'type' => 'boolean', 'description' => 'Set to true to prevent following links.' ),
                    'og_title' => array( 'type' => 'string', 'description' => 'Open Graph title for social sharing.' ),
                    'og_description' => array( 'type' => 'string', 'description' => 'Open Graph description.' ),
                    'og_image' => array( 'type' => 'string', 'description' => 'Open Graph image URL.' ),
                    'twitter_title' => array( 'type' => 'string', 'description' => 'Twitter card title.' ),
                    'twitter_description' => array( 'type' => 'string', 'description' => 'Twitter card description.' ),
                    'twitter_image' => array( 'type' => 'string', 'description' => 'Twitter card image URL.' ),
                ),
                'required' => array( 'post_id' ),
            ),
            'output_schema' => array( 'type' => 'object' ),
            'execute_callback' => array( $this, 'execute_set_yoast_seo' ),
            'permission_callback' => function() { return current_user_can( 'edit_posts' ); },
            'meta' => self::$mcp_meta,
        ) );

        // Get Yoast SEO Metadata
        wp_register_ability( 'seo/get-yoast-seo', array(
            'label' => 'Get Yoast SEO Metadata',
            'description' => 'Retrieve current Yoast SEO metadata for a post.',
            'category' => self::$category,
            'input_schema' => array(
                'type' => 'object',
                'properties' => array(
                    'post_id' => array( 'type' => 'integer', 'description' => 'The post ID to get SEO for.' ),
                ),
                'required' => array( 'post_id' ),
            ),
            'output_schema' => array( 'type' => 'object' ),
            'execute_callback' => array( $this, 'execute_get_yoast_seo' ),
            'permission_callback' => function() { return current_user_can( 'edit_posts' ); },
            'meta' => self::$mcp_meta,
        ) );

        // Bulk Set Yoast SEO Metadata
        wp_register_ability( 'seo/bulk-set-yoast-seo', array(
            'label' => 'Bulk Set Yoast SEO Metadata',
            'description' => 'Set Yoast SEO metadata for multiple posts at once.',
            'category' => self::$category,
            'input_schema' => array(
                'type' => 'object',
                'properties' => array(
                    'posts' => array(
                        'type' => 'array',
                        'description' => 'Array of posts with SEO data. Each: {post_id, title, meta_description, focus_keyword, ...}',
                        'items' => array(
                            'type' => 'object',
                            'properties' => array(
                                'post_id' => array( 'type' => 'integer' ),
                                'title' => array( 'type' => 'string' ),
                                'meta_description' => array( 'type' => 'string' ),
                                'focus_keyword' => array( 'type' => 'string' ),
                            ),
                            'required' => array( 'post_id' ),
                        ),
                    ),
                ),
                'required' => array( 'posts' ),
            ),
            'output_schema' => array( 'type' => 'object' ),
            'execute_callback' => array( $this, 'execute_bulk_set_yoast_seo' ),
            'permission_callback' => function() { return current_user_can( 'edit_posts' ); },
            'meta' => self::$mcp_meta,
        ) );

        // Get Posts Missing SEO
        wp_register_ability( 'seo/get-posts-missing-seo', array(
            'label' => 'Get Posts Missing SEO Metadata',
            'description' => 'Find posts that are missing Yoast SEO metadata (title, description, or focus keyword).',
            'category' => self::$category,
            'input_schema' => array(
                'type' => 'object',
                'properties' => array(
                    'post_type' => array( 'type' => 'string', 'description' => 'Post type to check. Default: post.', 'default' => 'post' ),
                    'per_page' => array( 'type' => 'integer', 'description' => 'Number of results. Default: 50.', 'default' => 50 ),
                    'missing' => array( 'type' => 'string', 'description' => 'What to check: "title", "description", "focus_keyword", or "any". Default: any.', 'default' => 'any' ),
                ),
            ),
            'output_schema' => array( 'type' => 'object' ),
            'execute_callback' => array( $this, 'execute_get_posts_missing_seo' ),
            'permission_callback' => function() { return current_user_can( 'edit_posts' ); },
            'meta' => self::$mcp_meta,
        ) );
    }

    // =========================================================================
    // RANKMATH SEO ABILITIES
    // =========================================================================

    private function register_rankmath_abilities() {
        // Set RankMath SEO Metadata
        wp_register_ability( 'seo/set-rankmath-seo', array(
            'label' => 'Set RankMath SEO Metadata',
            'description' => 'Set RankMath SEO metadata for a post including title, description, focus keyword, and social settings.',
            'category' => self::$category,
            'input_schema' => array(
                'type' => 'object',
                'properties' => array(
                    'post_id' => array( 'type' => 'integer', 'description' => 'The post ID to set SEO for.' ),
                    'title' => array( 'type' => 'string', 'description' => 'SEO title.' ),
                    'description' => array( 'type' => 'string', 'description' => 'Meta description.' ),
                    'focus_keyword' => array( 'type' => 'string', 'description' => 'Primary focus keyword.' ),
                    'robots_index' => array( 'type' => 'string', 'description' => 'index or noindex.' ),
                    'canonical_url' => array( 'type' => 'string', 'description' => 'Canonical URL.' ),
                    'og_title' => array( 'type' => 'string', 'description' => 'Facebook/Open Graph title.' ),
                    'og_description' => array( 'type' => 'string', 'description' => 'Facebook/Open Graph description.' ),
                    'og_image' => array( 'type' => 'string', 'description' => 'Facebook/Open Graph image URL.' ),
                ),
                'required' => array( 'post_id' ),
            ),
            'output_schema' => array( 'type' => 'object' ),
            'execute_callback' => array( $this, 'execute_set_rankmath_seo' ),
            'permission_callback' => function() { return current_user_can( 'edit_posts' ); },
            'meta' => self::$mcp_meta,
        ) );

        // Get RankMath SEO Metadata
        wp_register_ability( 'seo/get-rankmath-seo', array(
            'label' => 'Get RankMath SEO Metadata',
            'description' => 'Retrieve current RankMath SEO metadata for a post.',
            'category' => self::$category,
            'input_schema' => array(
                'type' => 'object',
                'properties' => array(
                    'post_id' => array( 'type' => 'integer', 'description' => 'The post ID to get SEO for.' ),
                ),
                'required' => array( 'post_id' ),
            ),
            'output_schema' => array( 'type' => 'object' ),
            'execute_callback' => array( $this, 'execute_get_rankmath_seo' ),
            'permission_callback' => function() { return current_user_can( 'edit_posts' ); },
            'meta' => self::$mcp_meta,
        ) );
    }

    // =========================================================================
    // SEO VERIFICATION ABILITIES
    // =========================================================================

    private function register_seo_verification_abilities() {
        // Verify Blog SEO Structure
        wp_register_ability( 'seo/verify-seo-structure', array(
            'label' => 'Verify Blog SEO Structure',
            'description' => 'Validate blog post structure for SEO quality, checking title length, meta description, keywords, headings, and more.',
            'category' => self::$category,
            'input_schema' => array(
                'type' => 'object',
                'properties' => array(
                    'post_id' => array( 'type' => 'integer', 'description' => 'Post ID to verify (optional if providing content directly).' ),
                    'title' => array( 'type' => 'string', 'description' => 'Post title to verify.' ),
                    'content' => array( 'type' => 'string', 'description' => 'Post content to analyze.' ),
                    'meta_description' => array( 'type' => 'string', 'description' => 'Meta description to check.' ),
                    'focus_keyword' => array( 'type' => 'string', 'description' => 'Focus keyword to verify in content.' ),
                ),
            ),
            'output_schema' => array( 'type' => 'object' ),
            'execute_callback' => array( $this, 'execute_verify_seo_structure' ),
            'permission_callback' => function() { return current_user_can( 'edit_posts' ); },
            'meta' => self::$mcp_meta,
        ) );

        // Analyze Keyword Density
        wp_register_ability( 'seo/analyze-keyword-density', array(
            'label' => 'Analyze Keyword Density',
            'description' => 'Analyze keyword density and placement in content for SEO optimization.',
            'category' => self::$category,
            'input_schema' => array(
                'type' => 'object',
                'properties' => array(
                    'post_id' => array( 'type' => 'integer', 'description' => 'Post ID to analyze.' ),
                    'content' => array( 'type' => 'string', 'description' => 'Content to analyze (alternative to post_id).' ),
                    'keywords' => array( 'type' => 'array', 'description' => 'Keywords to check for.', 'items' => array( 'type' => 'string' ) ),
                ),
            ),
            'output_schema' => array( 'type' => 'object' ),
            'execute_callback' => array( $this, 'execute_analyze_keyword_density' ),
            'permission_callback' => function() { return current_user_can( 'edit_posts' ); },
            'meta' => self::$mcp_meta,
        ) );

        // Check Active SEO Plugin
        wp_register_ability( 'seo/check-active-plugin', array(
            'label' => 'Check Active SEO Plugin',
            'description' => 'Check which SEO plugin is active (Yoast, RankMath, or none).',
            'category' => self::$category,
            'input_schema' => array(
                'type' => 'object',
                'properties' => array(),
            ),
            'output_schema' => array( 'type' => 'object' ),
            'execute_callback' => array( $this, 'execute_check_active_plugin' ),
            'permission_callback' => function() { return current_user_can( 'edit_posts' ); },
            'meta' => self::$mcp_meta,
        ) );
    }

    // =========================================================================
    // YOAST EXECUTE CALLBACKS
    // =========================================================================

    public function execute_set_yoast_seo( $input ) {
        $post_id = absint( $input['post_id'] );
        $post = get_post( $post_id );
        if ( ! $post ) {
            return new WP_Error( 'not_found', 'Post not found.' );
        }

        $updated_fields = array();

        // Yoast SEO meta keys
        if ( isset( $input['title'] ) ) {
            update_post_meta( $post_id, '_yoast_wpseo_title', sanitize_text_field( $input['title'] ) );
            $updated_fields[] = 'title';
        }
        if ( isset( $input['meta_description'] ) ) {
            update_post_meta( $post_id, '_yoast_wpseo_metadesc', sanitize_textarea_field( $input['meta_description'] ) );
            $updated_fields[] = 'meta_description';
        }
        if ( isset( $input['focus_keyword'] ) ) {
            update_post_meta( $post_id, '_yoast_wpseo_focuskw', sanitize_text_field( $input['focus_keyword'] ) );
            $updated_fields[] = 'focus_keyword';
        }
        if ( isset( $input['related_keyphrases'] ) && is_array( $input['related_keyphrases'] ) ) {
            $keyphrases = array_map( function( $kw ) {
                return array( 'keyword' => sanitize_text_field( $kw ) );
            }, $input['related_keyphrases'] );
            update_post_meta( $post_id, '_yoast_wpseo_focuskeywords', wp_json_encode( $keyphrases ) );
            $updated_fields[] = 'related_keyphrases';
        }
        if ( isset( $input['canonical_url'] ) ) {
            update_post_meta( $post_id, '_yoast_wpseo_canonical', esc_url_raw( $input['canonical_url'] ) );
            $updated_fields[] = 'canonical_url';
        }
        if ( isset( $input['noindex'] ) ) {
            update_post_meta( $post_id, '_yoast_wpseo_meta-robots-noindex', $input['noindex'] ? '1' : '0' );
            $updated_fields[] = 'noindex';
        }
        if ( isset( $input['nofollow'] ) ) {
            update_post_meta( $post_id, '_yoast_wpseo_meta-robots-nofollow', $input['nofollow'] ? '1' : '0' );
            $updated_fields[] = 'nofollow';
        }
        if ( isset( $input['og_title'] ) ) {
            update_post_meta( $post_id, '_yoast_wpseo_opengraph-title', sanitize_text_field( $input['og_title'] ) );
            $updated_fields[] = 'og_title';
        }
        if ( isset( $input['og_description'] ) ) {
            update_post_meta( $post_id, '_yoast_wpseo_opengraph-description', sanitize_textarea_field( $input['og_description'] ) );
            $updated_fields[] = 'og_description';
        }
        if ( isset( $input['og_image'] ) ) {
            update_post_meta( $post_id, '_yoast_wpseo_opengraph-image', esc_url_raw( $input['og_image'] ) );
            $updated_fields[] = 'og_image';
        }
        if ( isset( $input['twitter_title'] ) ) {
            update_post_meta( $post_id, '_yoast_wpseo_twitter-title', sanitize_text_field( $input['twitter_title'] ) );
            $updated_fields[] = 'twitter_title';
        }
        if ( isset( $input['twitter_description'] ) ) {
            update_post_meta( $post_id, '_yoast_wpseo_twitter-description', sanitize_textarea_field( $input['twitter_description'] ) );
            $updated_fields[] = 'twitter_description';
        }
        if ( isset( $input['twitter_image'] ) ) {
            update_post_meta( $post_id, '_yoast_wpseo_twitter-image', esc_url_raw( $input['twitter_image'] ) );
            $updated_fields[] = 'twitter_image';
        }

        return array(
            'success' => true,
            'post_id' => $post_id,
            'updated_fields' => $updated_fields,
            'message' => 'Yoast SEO metadata updated successfully.',
        );
    }

    public function execute_get_yoast_seo( $input ) {
        $post_id = absint( $input['post_id'] );
        $post = get_post( $post_id );
        if ( ! $post ) {
            return new WP_Error( 'not_found', 'Post not found.' );
        }

        $seo_data = array(
            'post_id' => $post_id,
            'post_title' => $post->post_title,
            'title' => get_post_meta( $post_id, '_yoast_wpseo_title', true ),
            'meta_description' => get_post_meta( $post_id, '_yoast_wpseo_metadesc', true ),
            'focus_keyword' => get_post_meta( $post_id, '_yoast_wpseo_focuskw', true ),
            'related_keyphrases' => json_decode( get_post_meta( $post_id, '_yoast_wpseo_focuskeywords', true ), true ),
            'canonical_url' => get_post_meta( $post_id, '_yoast_wpseo_canonical', true ),
            'noindex' => get_post_meta( $post_id, '_yoast_wpseo_meta-robots-noindex', true ) === '1',
            'nofollow' => get_post_meta( $post_id, '_yoast_wpseo_meta-robots-nofollow', true ) === '1',
            'og_title' => get_post_meta( $post_id, '_yoast_wpseo_opengraph-title', true ),
            'og_description' => get_post_meta( $post_id, '_yoast_wpseo_opengraph-description', true ),
            'og_image' => get_post_meta( $post_id, '_yoast_wpseo_opengraph-image', true ),
            'twitter_title' => get_post_meta( $post_id, '_yoast_wpseo_twitter-title', true ),
            'twitter_description' => get_post_meta( $post_id, '_yoast_wpseo_twitter-description', true ),
            'twitter_image' => get_post_meta( $post_id, '_yoast_wpseo_twitter-image', true ),
        );

        // Check if Yoast is active
        $seo_data['yoast_active'] = class_exists( 'WPSEO_Meta' );

        return $seo_data;
    }

    public function execute_bulk_set_yoast_seo( $input ) {
        if ( empty( $input['posts'] ) || ! is_array( $input['posts'] ) ) {
            return new WP_Error( 'invalid_input', 'Posts array is required.' );
        }

        $results = array();
        $success_count = 0;
        $error_count = 0;

        foreach ( $input['posts'] as $post_data ) {
            $result = $this->execute_set_yoast_seo( $post_data );
            if ( is_wp_error( $result ) ) {
                $results[] = array(
                    'post_id' => isset( $post_data['post_id'] ) ? $post_data['post_id'] : null,
                    'success' => false,
                    'error' => $result->get_error_message(),
                );
                $error_count++;
            } else {
                $results[] = $result;
                $success_count++;
            }
        }

        return array(
            'success' => $error_count === 0,
            'total' => count( $input['posts'] ),
            'success_count' => $success_count,
            'error_count' => $error_count,
            'results' => $results,
        );
    }

    public function execute_get_posts_missing_seo( $input ) {
        $post_type = isset( $input['post_type'] ) ? sanitize_text_field( $input['post_type'] ) : 'post';
        $per_page = isset( $input['per_page'] ) ? absint( $input['per_page'] ) : 50;
        $missing = isset( $input['missing'] ) ? sanitize_text_field( $input['missing'] ) : 'any';

        $meta_query = array( 'relation' => 'OR' );

        if ( $missing === 'title' || $missing === 'any' ) {
            $meta_query[] = array(
                'relation' => 'OR',
                array( 'key' => '_yoast_wpseo_title', 'compare' => 'NOT EXISTS' ),
                array( 'key' => '_yoast_wpseo_title', 'value' => '', 'compare' => '=' ),
            );
        }
        if ( $missing === 'description' || $missing === 'any' ) {
            $meta_query[] = array(
                'relation' => 'OR',
                array( 'key' => '_yoast_wpseo_metadesc', 'compare' => 'NOT EXISTS' ),
                array( 'key' => '_yoast_wpseo_metadesc', 'value' => '', 'compare' => '=' ),
            );
        }
        if ( $missing === 'focus_keyword' || $missing === 'any' ) {
            $meta_query[] = array(
                'relation' => 'OR',
                array( 'key' => '_yoast_wpseo_focuskw', 'compare' => 'NOT EXISTS' ),
                array( 'key' => '_yoast_wpseo_focuskw', 'value' => '', 'compare' => '=' ),
            );
        }

        $query = new WP_Query( array(
            'post_type' => $post_type,
            'posts_per_page' => $per_page,
            'post_status' => 'publish',
            'meta_query' => $meta_query,
        ) );

        $posts = array();
        foreach ( $query->posts as $post ) {
            $posts[] = array(
                'id' => $post->ID,
                'title' => $post->post_title,
                'post_type' => $post->post_type,
                'permalink' => get_permalink( $post->ID ),
                'seo_title' => get_post_meta( $post->ID, '_yoast_wpseo_title', true ),
                'meta_description' => get_post_meta( $post->ID, '_yoast_wpseo_metadesc', true ),
                'focus_keyword' => get_post_meta( $post->ID, '_yoast_wpseo_focuskw', true ),
            );
        }

        return array(
            'total_found' => $query->found_posts,
            'returned' => count( $posts ),
            'posts' => $posts,
        );
    }

    // =========================================================================
    // RANKMATH EXECUTE CALLBACKS
    // =========================================================================

    public function execute_set_rankmath_seo( $input ) {
        $post_id = absint( $input['post_id'] );
        $post = get_post( $post_id );
        if ( ! $post ) {
            return new WP_Error( 'not_found', 'Post not found.' );
        }

        $updated_fields = array();

        // RankMath meta keys
        if ( isset( $input['title'] ) ) {
            update_post_meta( $post_id, 'rank_math_title', sanitize_text_field( $input['title'] ) );
            $updated_fields[] = 'title';
        }
        if ( isset( $input['description'] ) ) {
            update_post_meta( $post_id, 'rank_math_description', sanitize_textarea_field( $input['description'] ) );
            $updated_fields[] = 'description';
        }
        if ( isset( $input['focus_keyword'] ) ) {
            update_post_meta( $post_id, 'rank_math_focus_keyword', sanitize_text_field( $input['focus_keyword'] ) );
            $updated_fields[] = 'focus_keyword';
        }
        if ( isset( $input['robots_index'] ) ) {
            update_post_meta( $post_id, 'rank_math_robots', sanitize_text_field( $input['robots_index'] ) );
            $updated_fields[] = 'robots_index';
        }
        if ( isset( $input['canonical_url'] ) ) {
            update_post_meta( $post_id, 'rank_math_canonical', esc_url_raw( $input['canonical_url'] ) );
            $updated_fields[] = 'canonical_url';
        }
        if ( isset( $input['og_title'] ) ) {
            update_post_meta( $post_id, 'rank_math_facebook_title', sanitize_text_field( $input['og_title'] ) );
            $updated_fields[] = 'og_title';
        }
        if ( isset( $input['og_description'] ) ) {
            update_post_meta( $post_id, 'rank_math_facebook_description', sanitize_textarea_field( $input['og_description'] ) );
            $updated_fields[] = 'og_description';
        }
        if ( isset( $input['og_image'] ) ) {
            update_post_meta( $post_id, 'rank_math_facebook_image', esc_url_raw( $input['og_image'] ) );
            $updated_fields[] = 'og_image';
        }

        return array(
            'success' => true,
            'post_id' => $post_id,
            'updated_fields' => $updated_fields,
            'message' => 'RankMath SEO metadata updated successfully.',
        );
    }

    public function execute_get_rankmath_seo( $input ) {
        $post_id = absint( $input['post_id'] );
        $post = get_post( $post_id );
        if ( ! $post ) {
            return new WP_Error( 'not_found', 'Post not found.' );
        }

        $seo_data = array(
            'post_id' => $post_id,
            'post_title' => $post->post_title,
            'title' => get_post_meta( $post_id, 'rank_math_title', true ),
            'description' => get_post_meta( $post_id, 'rank_math_description', true ),
            'focus_keyword' => get_post_meta( $post_id, 'rank_math_focus_keyword', true ),
            'robots' => get_post_meta( $post_id, 'rank_math_robots', true ),
            'canonical_url' => get_post_meta( $post_id, 'rank_math_canonical', true ),
            'og_title' => get_post_meta( $post_id, 'rank_math_facebook_title', true ),
            'og_description' => get_post_meta( $post_id, 'rank_math_facebook_description', true ),
            'og_image' => get_post_meta( $post_id, 'rank_math_facebook_image', true ),
        );

        // Check if RankMath is active
        $seo_data['rankmath_active'] = class_exists( 'RankMath' );

        return $seo_data;
    }

    // =========================================================================
    // SEO VERIFICATION EXECUTE CALLBACKS
    // =========================================================================

    public function execute_verify_seo_structure( $input ) {
        $errors = array();
        $warnings = array();
        $suggestions = array();
        $score = 100;

        // Get content from post if post_id provided
        $title = isset( $input['title'] ) ? $input['title'] : '';
        $content = isset( $input['content'] ) ? $input['content'] : '';
        $meta_description = isset( $input['meta_description'] ) ? $input['meta_description'] : '';
        $focus_keyword = isset( $input['focus_keyword'] ) ? strtolower( $input['focus_keyword'] ) : '';

        if ( isset( $input['post_id'] ) ) {
            $post = get_post( absint( $input['post_id'] ) );
            if ( $post ) {
                if ( empty( $title ) ) $title = $post->post_title;
                if ( empty( $content ) ) $content = $post->post_content;
                if ( empty( $meta_description ) ) $meta_description = get_post_meta( $post->ID, '_yoast_wpseo_metadesc', true );
                if ( empty( $focus_keyword ) ) $focus_keyword = strtolower( get_post_meta( $post->ID, '_yoast_wpseo_focuskw', true ) );
            }
        }

        // Title checks
        if ( empty( $title ) ) {
            $errors[] = 'Title is missing';
            $score -= 20;
        } else {
            $title_len = strlen( $title );
            if ( $title_len < 30 ) {
                $warnings[] = "Title is short ($title_len chars). Recommended: 50-60 characters.";
                $score -= 5;
            }
            if ( $title_len > 60 ) {
                $warnings[] = "Title is long ($title_len chars). May be truncated in search results.";
                $score -= 5;
            }
            if ( $focus_keyword && strpos( strtolower( $title ), $focus_keyword ) === false ) {
                $warnings[] = 'Focus keyword not found in title.';
                $score -= 10;
            }
        }

        // Meta description checks
        if ( empty( $meta_description ) ) {
            $warnings[] = 'Meta description is missing.';
            $score -= 10;
        } else {
            $desc_len = strlen( $meta_description );
            if ( $desc_len < 120 ) {
                $suggestions[] = "Meta description is short ($desc_len chars). Recommended: 150-160 characters.";
            }
            if ( $desc_len > 160 ) {
                $warnings[] = "Meta description is long ($desc_len chars). May be truncated.";
                $score -= 5;
            }
            if ( $focus_keyword && strpos( strtolower( $meta_description ), $focus_keyword ) === false ) {
                $suggestions[] = 'Consider including focus keyword in meta description.';
            }
        }

        // Content checks
        if ( empty( $content ) ) {
            $errors[] = 'Content is missing.';
            $score -= 30;
        } else {
            $word_count = str_word_count( strip_tags( $content ) );
            if ( $word_count < 300 ) {
                $warnings[] = "Content is thin ($word_count words). Recommended: 600+ words.";
                $score -= 15;
            }
            if ( $word_count < 100 ) {
                $errors[] = "Content is very short ($word_count words).";
                $score -= 10;
            }

            // Heading structure
            $h2_count = preg_match_all( '/<h2|^## /im', $content );
            if ( $h2_count < 2 ) {
                $suggestions[] = 'Add more H2 headings for better content structure.';
            }

            // Focus keyword in content
            if ( $focus_keyword ) {
                $keyword_count = substr_count( strtolower( strip_tags( $content ) ), $focus_keyword );
                if ( $keyword_count === 0 ) {
                    $warnings[] = 'Focus keyword not found in content.';
                    $score -= 15;
                } elseif ( $keyword_count < 3 ) {
                    $suggestions[] = "Focus keyword appears only $keyword_count time(s). Consider using it more naturally.";
                }
            }

            // Check for images
            $has_images = preg_match( '/<img|!\[/i', $content );
            if ( ! $has_images ) {
                $suggestions[] = 'Consider adding images to improve engagement.';
            }

            // Check for internal/external links
            $has_links = preg_match( '/<a |]\(/i', $content );
            if ( ! $has_links ) {
                $suggestions[] = 'Consider adding internal or external links.';
            }
        }

        return array(
            'is_valid' => count( $errors ) === 0,
            'score' => max( 0, $score ),
            'errors' => $errors,
            'warnings' => $warnings,
            'suggestions' => $suggestions,
            'details' => array(
                'title_length' => strlen( $title ),
                'meta_description_length' => strlen( $meta_description ),
                'word_count' => ! empty( $content ) ? str_word_count( strip_tags( $content ) ) : 0,
                'has_focus_keyword' => ! empty( $focus_keyword ),
            ),
        );
    }

    public function execute_analyze_keyword_density( $input ) {
        $content = '';

        if ( isset( $input['post_id'] ) ) {
            $post = get_post( absint( $input['post_id'] ) );
            if ( $post ) {
                $content = $post->post_content;
            }
        }

        if ( isset( $input['content'] ) ) {
            $content = $input['content'];
        }

        if ( empty( $content ) ) {
            return new WP_Error( 'no_content', 'No content to analyze.' );
        }

        $plain_content = strtolower( strip_tags( $content ) );
        $word_count = str_word_count( $plain_content );
        $keywords = isset( $input['keywords'] ) ? $input['keywords'] : array();

        // If no keywords provided, get from Yoast
        if ( empty( $keywords ) && isset( $input['post_id'] ) ) {
            $focus_kw = get_post_meta( absint( $input['post_id'] ), '_yoast_wpseo_focuskw', true );
            if ( $focus_kw ) {
                $keywords[] = $focus_kw;
            }
        }

        $analysis = array();
        foreach ( $keywords as $keyword ) {
            $kw_lower = strtolower( $keyword );
            $count = substr_count( $plain_content, $kw_lower );
            $density = $word_count > 0 ? round( ( $count / $word_count ) * 100, 2 ) : 0;

            // Check placement
            $in_first_paragraph = false;
            $in_headings = false;

            // Check first 200 chars
            if ( strpos( substr( $plain_content, 0, 200 ), $kw_lower ) !== false ) {
                $in_first_paragraph = true;
            }

            // Check headings
            if ( preg_match( '/<h[1-6][^>]*>.*?' . preg_quote( $kw_lower, '/' ) . '.*?<\/h[1-6]>/is', strtolower( $content ) ) ) {
                $in_headings = true;
            }

            $analysis[] = array(
                'keyword' => $keyword,
                'count' => $count,
                'density_percent' => $density,
                'in_first_paragraph' => $in_first_paragraph,
                'in_headings' => $in_headings,
                'recommendation' => $this->get_density_recommendation( $density ),
            );
        }

        return array(
            'word_count' => $word_count,
            'keywords_analyzed' => count( $keywords ),
            'analysis' => $analysis,
        );
    }

    private function get_density_recommendation( $density ) {
        if ( $density === 0 ) {
            return 'Keyword not found. Add it to your content.';
        } elseif ( $density < 0.5 ) {
            return 'Keyword density is low. Consider using the keyword more frequently.';
        } elseif ( $density <= 2.5 ) {
            return 'Good keyword density.';
        } else {
            return 'Keyword density is high. Reduce usage to avoid keyword stuffing.';
        }
    }

    public function execute_check_active_plugin( $input ) {
        $yoast_active = class_exists( 'WPSEO_Meta' ) || defined( 'WPSEO_VERSION' );
        $rankmath_active = class_exists( 'RankMath' ) || defined( 'RANK_MATH_VERSION' );

        $active_plugin = 'none';
        if ( $yoast_active ) {
            $active_plugin = 'yoast';
        } elseif ( $rankmath_active ) {
            $active_plugin = 'rankmath';
        }

        return array(
            'active_plugin' => $active_plugin,
            'yoast_active' => $yoast_active,
            'yoast_version' => defined( 'WPSEO_VERSION' ) ? WPSEO_VERSION : null,
            'rankmath_active' => $rankmath_active,
            'rankmath_version' => defined( 'RANK_MATH_VERSION' ) ? RANK_MATH_VERSION : null,
        );
    }
}

// Initialize plugin
add_action( 'plugins_loaded', function() {
    Yoast_SEO_Abilities_Plugin::instance();
} );
