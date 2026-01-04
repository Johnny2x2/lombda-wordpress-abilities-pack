<?php
/**
 * Plugin Name: WordPress Content Abilities
 * Description: Exposes native WordPress content management functionality (posts, categories, tags, media) as WordPress Abilities for AI agents via MCP.
 * Version: 1.0.0
 * Author: Lombda LLC
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WordPress_Content_Abilities_Plugin {
    private static $instance = null;
    private static $mcp_meta = array(
        'show_in_rest' => true,
        'mcp'          => array( 'public' => true ),
    );
    private static $category = 'wordpress-content';

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
                'label'       => 'WordPress Content',
                'description' => 'Native WordPress content management abilities for posts, categories, tags, and media.',
            )
        );
    }

    public function register_abilities() {
        $this->register_taxonomy_abilities();
        $this->register_media_abilities();
        $this->register_content_abilities();
    }

    // =========================================================================
    // TAXONOMY ABILITIES (Categories & Tags)
    // =========================================================================

    private function register_taxonomy_abilities() {
        // Get Categories
        wp_register_ability( 'wordpress/get-categories', array(
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
        wp_register_ability( 'wordpress/create-category', array(
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

        // Update Category
        wp_register_ability( 'wordpress/update-category', array(
            'label' => 'Update WordPress Category',
            'description' => 'Update an existing WordPress category.',
            'category' => self::$category,
            'input_schema' => array(
                'type' => 'object',
                'properties' => array(
                    'id' => array( 'type' => 'integer', 'description' => 'Category ID to update.' ),
                    'name' => array( 'type' => 'string', 'description' => 'New category name.' ),
                    'slug' => array( 'type' => 'string', 'description' => 'New category slug.' ),
                    'description' => array( 'type' => 'string', 'description' => 'New category description.' ),
                    'parent' => array( 'type' => 'integer', 'description' => 'New parent category ID.' ),
                ),
                'required' => array( 'id' ),
            ),
            'output_schema' => array( 'type' => 'object' ),
            'execute_callback' => function( $input ) {
                $term_id = absint( $input['id'] );
                $term = get_term( $term_id, 'category' );
                
                if ( ! $term || is_wp_error( $term ) ) {
                    return new WP_Error( 'not_found', 'Category not found.', array( 'status' => 404 ) );
                }

                $args = array();
                if ( isset( $input['name'] ) ) $args['name'] = sanitize_text_field( $input['name'] );
                if ( isset( $input['slug'] ) ) $args['slug'] = sanitize_title( $input['slug'] );
                if ( isset( $input['description'] ) ) $args['description'] = sanitize_textarea_field( $input['description'] );
                if ( isset( $input['parent'] ) ) $args['parent'] = absint( $input['parent'] );

                $result = wp_update_term( $term_id, 'category', $args );
                if ( is_wp_error( $result ) ) {
                    return $result;
                }

                $updated_term = get_term( $term_id, 'category' );
                return array(
                    'success' => true,
                    'category' => array(
                        'id' => $updated_term->term_id,
                        'name' => $updated_term->name,
                        'slug' => $updated_term->slug,
                        'description' => $updated_term->description,
                        'parent' => $updated_term->parent,
                    ),
                );
            },
            'permission_callback' => function() { return current_user_can( 'manage_categories' ); },
            'meta' => self::$mcp_meta,
        ) );

        // Delete Category
        wp_register_ability( 'wordpress/delete-category', array(
            'label' => 'Delete WordPress Category',
            'description' => 'Delete a WordPress category.',
            'category' => self::$category,
            'input_schema' => array(
                'type' => 'object',
                'properties' => array(
                    'id' => array( 'type' => 'integer', 'description' => 'Category ID to delete.' ),
                ),
                'required' => array( 'id' ),
            ),
            'output_schema' => array( 'type' => 'object' ),
            'execute_callback' => function( $input ) {
                $term_id = absint( $input['id'] );
                $term = get_term( $term_id, 'category' );
                
                if ( ! $term || is_wp_error( $term ) ) {
                    return new WP_Error( 'not_found', 'Category not found.', array( 'status' => 404 ) );
                }

                $result = wp_delete_term( $term_id, 'category' );
                if ( is_wp_error( $result ) ) {
                    return $result;
                }

                return array(
                    'success' => true,
                    'deleted_id' => $term_id,
                );
            },
            'permission_callback' => function() { return current_user_can( 'manage_categories' ); },
            'meta' => self::$mcp_meta,
        ) );

        // Get Tags
        wp_register_ability( 'wordpress/get-tags', array(
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
        wp_register_ability( 'wordpress/create-tag', array(
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

        // Update Tag
        wp_register_ability( 'wordpress/update-tag', array(
            'label' => 'Update WordPress Tag',
            'description' => 'Update an existing WordPress tag.',
            'category' => self::$category,
            'input_schema' => array(
                'type' => 'object',
                'properties' => array(
                    'id' => array( 'type' => 'integer', 'description' => 'Tag ID to update.' ),
                    'name' => array( 'type' => 'string', 'description' => 'New tag name.' ),
                    'slug' => array( 'type' => 'string', 'description' => 'New tag slug.' ),
                    'description' => array( 'type' => 'string', 'description' => 'New tag description.' ),
                ),
                'required' => array( 'id' ),
            ),
            'output_schema' => array( 'type' => 'object' ),
            'execute_callback' => function( $input ) {
                $term_id = absint( $input['id'] );
                $term = get_term( $term_id, 'post_tag' );
                
                if ( ! $term || is_wp_error( $term ) ) {
                    return new WP_Error( 'not_found', 'Tag not found.', array( 'status' => 404 ) );
                }

                $args = array();
                if ( isset( $input['name'] ) ) $args['name'] = sanitize_text_field( $input['name'] );
                if ( isset( $input['slug'] ) ) $args['slug'] = sanitize_title( $input['slug'] );
                if ( isset( $input['description'] ) ) $args['description'] = sanitize_textarea_field( $input['description'] );

                $result = wp_update_term( $term_id, 'post_tag', $args );
                if ( is_wp_error( $result ) ) {
                    return $result;
                }

                $updated_term = get_term( $term_id, 'post_tag' );
                return array(
                    'success' => true,
                    'tag' => array(
                        'id' => $updated_term->term_id,
                        'name' => $updated_term->name,
                        'slug' => $updated_term->slug,
                        'description' => $updated_term->description,
                    ),
                );
            },
            'permission_callback' => function() { return current_user_can( 'manage_categories' ); },
            'meta' => self::$mcp_meta,
        ) );

        // Delete Tag
        wp_register_ability( 'wordpress/delete-tag', array(
            'label' => 'Delete WordPress Tag',
            'description' => 'Delete a WordPress tag.',
            'category' => self::$category,
            'input_schema' => array(
                'type' => 'object',
                'properties' => array(
                    'id' => array( 'type' => 'integer', 'description' => 'Tag ID to delete.' ),
                ),
                'required' => array( 'id' ),
            ),
            'output_schema' => array( 'type' => 'object' ),
            'execute_callback' => function( $input ) {
                $term_id = absint( $input['id'] );
                $term = get_term( $term_id, 'post_tag' );
                
                if ( ! $term || is_wp_error( $term ) ) {
                    return new WP_Error( 'not_found', 'Tag not found.', array( 'status' => 404 ) );
                }

                $result = wp_delete_term( $term_id, 'post_tag' );
                if ( is_wp_error( $result ) ) {
                    return $result;
                }

                return array(
                    'success' => true,
                    'deleted_id' => $term_id,
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
        wp_register_ability( 'wordpress/get-media', array(
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

        // Get Media Item
        wp_register_ability( 'wordpress/get-media-item', array(
            'label' => 'Get Media Item',
            'description' => 'Retrieve a single media item by ID with full details.',
            'category' => self::$category,
            'input_schema' => array(
                'type' => 'object',
                'properties' => array(
                    'id' => array( 'type' => 'integer', 'description' => 'Media attachment ID.' ),
                ),
                'required' => array( 'id' ),
            ),
            'output_schema' => array( 'type' => 'object' ),
            'execute_callback' => function( $input ) {
                $media_id = absint( $input['id'] );
                $attachment = get_post( $media_id );
                
                if ( ! $attachment || $attachment->post_type !== 'attachment' ) {
                    return new WP_Error( 'not_found', 'Media item not found.', array( 'status' => 404 ) );
                }

                $metadata = wp_get_attachment_metadata( $media_id );
                
                return array(
                    'id' => $attachment->ID,
                    'title' => $attachment->post_title,
                    'url' => wp_get_attachment_url( $media_id ),
                    'mime_type' => $attachment->post_mime_type,
                    'alt_text' => get_post_meta( $media_id, '_wp_attachment_image_alt', true ),
                    'caption' => $attachment->post_excerpt,
                    'description' => $attachment->post_content,
                    'date' => $attachment->post_date,
                    'metadata' => $metadata ? $metadata : array(),
                );
            },
            'permission_callback' => function() { return current_user_can( 'upload_files' ); },
            'meta' => self::$mcp_meta,
        ) );

        // Upload Media from URL
        wp_register_ability( 'wordpress/upload-media-url', array(
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

        // Update Media Item
        wp_register_ability( 'wordpress/update-media', array(
            'label' => 'Update Media Item',
            'description' => 'Update media item metadata (title, alt text, caption, description).',
            'category' => self::$category,
            'input_schema' => array(
                'type' => 'object',
                'properties' => array(
                    'id' => array( 'type' => 'integer', 'description' => 'Media attachment ID.' ),
                    'title' => array( 'type' => 'string', 'description' => 'New title.' ),
                    'alt_text' => array( 'type' => 'string', 'description' => 'New alt text.' ),
                    'caption' => array( 'type' => 'string', 'description' => 'New caption.' ),
                    'description' => array( 'type' => 'string', 'description' => 'New description.' ),
                ),
                'required' => array( 'id' ),
            ),
            'output_schema' => array( 'type' => 'object' ),
            'execute_callback' => function( $input ) {
                $media_id = absint( $input['id'] );
                $attachment = get_post( $media_id );
                
                if ( ! $attachment || $attachment->post_type !== 'attachment' ) {
                    return new WP_Error( 'not_found', 'Media item not found.', array( 'status' => 404 ) );
                }

                $update_data = array( 'ID' => $media_id );
                if ( isset( $input['title'] ) ) {
                    $update_data['post_title'] = sanitize_text_field( $input['title'] );
                }
                if ( isset( $input['caption'] ) ) {
                    $update_data['post_excerpt'] = sanitize_textarea_field( $input['caption'] );
                }
                if ( isset( $input['description'] ) ) {
                    $update_data['post_content'] = sanitize_textarea_field( $input['description'] );
                }

                if ( count( $update_data ) > 1 ) {
                    $result = wp_update_post( $update_data, true );
                    if ( is_wp_error( $result ) ) {
                        return $result;
                    }
                }

                if ( isset( $input['alt_text'] ) ) {
                    update_post_meta( $media_id, '_wp_attachment_image_alt', sanitize_text_field( $input['alt_text'] ) );
                }

                return array(
                    'success' => true,
                    'id' => $media_id,
                    'url' => wp_get_attachment_url( $media_id ),
                );
            },
            'permission_callback' => function() { return current_user_can( 'upload_files' ); },
            'meta' => self::$mcp_meta,
        ) );

        // Delete Media Item
        wp_register_ability( 'wordpress/delete-media', array(
            'label' => 'Delete Media Item',
            'description' => 'Delete a media item from the library.',
            'category' => self::$category,
            'input_schema' => array(
                'type' => 'object',
                'properties' => array(
                    'id' => array( 'type' => 'integer', 'description' => 'Media attachment ID to delete.' ),
                    'force' => array( 'type' => 'boolean', 'description' => 'Force permanent deletion. Default: true.', 'default' => true ),
                ),
                'required' => array( 'id' ),
            ),
            'output_schema' => array( 'type' => 'object' ),
            'execute_callback' => function( $input ) {
                $media_id = absint( $input['id'] );
                $attachment = get_post( $media_id );
                
                if ( ! $attachment || $attachment->post_type !== 'attachment' ) {
                    return new WP_Error( 'not_found', 'Media item not found.', array( 'status' => 404 ) );
                }

                $force = isset( $input['force'] ) ? (bool) $input['force'] : true;
                $result = wp_delete_attachment( $media_id, $force );

                if ( ! $result ) {
                    return new WP_Error( 'delete_failed', 'Failed to delete media item.', array( 'status' => 500 ) );
                }

                return array(
                    'success' => true,
                    'deleted_id' => $media_id,
                );
            },
            'permission_callback' => function() { return current_user_can( 'delete_posts' ); },
            'meta' => self::$mcp_meta,
        ) );

        // Set Featured Image
        wp_register_ability( 'wordpress/set-featured-image', array(
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

        // Remove Featured Image
        wp_register_ability( 'wordpress/remove-featured-image', array(
            'label' => 'Remove Featured Image',
            'description' => 'Remove the featured image from a post.',
            'category' => self::$category,
            'input_schema' => array(
                'type' => 'object',
                'properties' => array(
                    'post_id' => array( 'type' => 'integer', 'description' => 'Post ID to remove featured image from.' ),
                ),
                'required' => array( 'post_id' ),
            ),
            'output_schema' => array( 'type' => 'object' ),
            'execute_callback' => function( $input ) {
                $post_id = absint( $input['post_id'] );

                $post = get_post( $post_id );
                if ( ! $post ) {
                    return new WP_Error( 'not_found', 'Post not found.' );
                }

                $result = delete_post_thumbnail( $post_id );
                return array(
                    'success' => true,
                    'post_id' => $post_id,
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
    // CONTENT ABILITIES (Posts & Pages)
    // =========================================================================

    private function register_content_abilities() {
        // Convert Markdown to HTML
        wp_register_ability( 'wordpress/convert-markdown', array(
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
                $html = $this->convert_markdown_to_html( $markdown );

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
        wp_register_ability( 'wordpress/create-post', array(
            'label' => 'Create WordPress Post',
            'description' => 'Create a complete WordPress post with title, content, categories, tags, and featured image.',
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
                ),
                'required' => array( 'title', 'content' ),
            ),
            'output_schema' => array( 'type' => 'object' ),
            'execute_callback' => array( $this, 'execute_create_post' ),
            'permission_callback' => function() { return current_user_can( 'publish_posts' ); },
            'meta' => self::$mcp_meta,
        ) );

        // Get Post
        wp_register_ability( 'wordpress/get-post', array(
            'label' => 'Get WordPress Post',
            'description' => 'Retrieve a WordPress post by ID.',
            'category' => self::$category,
            'input_schema' => array(
                'type' => 'object',
                'properties' => array(
                    'id' => array( 'type' => 'integer', 'description' => 'Post ID.' ),
                ),
                'required' => array( 'id' ),
            ),
            'output_schema' => array( 'type' => 'object' ),
            'execute_callback' => function( $input ) {
                $post_id = absint( $input['id'] );
                $post = get_post( $post_id );
                
                if ( ! $post ) {
                    return new WP_Error( 'not_found', 'Post not found.', array( 'status' => 404 ) );
                }

                return array(
                    'id' => $post->ID,
                    'title' => $post->post_title,
                    'content' => $post->post_content,
                    'excerpt' => $post->post_excerpt,
                    'status' => $post->post_status,
                    'type' => $post->post_type,
                    'date' => $post->post_date,
                    'modified' => $post->post_modified,
                    'slug' => $post->post_name,
                    'link' => get_permalink( $post_id ),
                    'edit_url' => get_edit_post_link( $post_id, 'raw' ),
                    'categories' => wp_get_post_categories( $post_id ),
                    'tags' => wp_get_post_tags( $post_id, array( 'fields' => 'ids' ) ),
                    'featured_media' => get_post_thumbnail_id( $post_id ),
                );
            },
            'permission_callback' => function() { return current_user_can( 'edit_posts' ); },
            'meta' => self::$mcp_meta,
        ) );

        // List Posts
        wp_register_ability( 'wordpress/list-posts', array(
            'label' => 'List WordPress Posts',
            'description' => 'Retrieve a list of WordPress posts with filtering options.',
            'category' => self::$category,
            'input_schema' => array(
                'type' => 'object',
                'properties' => array(
                    'per_page' => array( 'type' => 'integer', 'description' => 'Number of results. Default: 20.', 'default' => 20 ),
                    'status' => array( 'type' => 'string', 'description' => 'Post status filter: publish, draft, pending, any. Default: any.' ),
                    'post_type' => array( 'type' => 'string', 'description' => 'Post type. Default: post.', 'default' => 'post' ),
                    'search' => array( 'type' => 'string', 'description' => 'Search posts by keyword.' ),
                    'category' => array( 'type' => 'integer', 'description' => 'Filter by category ID.' ),
                    'tag' => array( 'type' => 'integer', 'description' => 'Filter by tag ID.' ),
                    'orderby' => array( 'type' => 'string', 'description' => 'Order by: date, title, modified. Default: date.', 'default' => 'date' ),
                    'order' => array( 'type' => 'string', 'description' => 'Order direction: ASC, DESC. Default: DESC.', 'default' => 'DESC' ),
                ),
            ),
            'output_schema' => array( 'type' => 'object' ),
            'execute_callback' => function( $input ) {
                $args = array(
                    'post_type' => isset( $input['post_type'] ) ? sanitize_key( $input['post_type'] ) : 'post',
                    'posts_per_page' => isset( $input['per_page'] ) ? absint( $input['per_page'] ) : 20,
                    'post_status' => isset( $input['status'] ) ? sanitize_key( $input['status'] ) : 'any',
                    'orderby' => isset( $input['orderby'] ) ? sanitize_key( $input['orderby'] ) : 'date',
                    'order' => isset( $input['order'] ) ? strtoupper( sanitize_key( $input['order'] ) ) : 'DESC',
                );

                if ( isset( $input['search'] ) ) {
                    $args['s'] = sanitize_text_field( $input['search'] );
                }
                if ( isset( $input['category'] ) ) {
                    $args['cat'] = absint( $input['category'] );
                }
                if ( isset( $input['tag'] ) ) {
                    $args['tag_id'] = absint( $input['tag'] );
                }

                $query = new WP_Query( $args );
                $posts = array();
                
                foreach ( $query->posts as $post ) {
                    $posts[] = array(
                        'id' => $post->ID,
                        'title' => $post->post_title,
                        'excerpt' => $post->post_excerpt,
                        'status' => $post->post_status,
                        'date' => $post->post_date,
                        'link' => get_permalink( $post->ID ),
                        'edit_url' => get_edit_post_link( $post->ID, 'raw' ),
                    );
                }

                return array(
                    'posts' => $posts,
                    'count' => count( $posts ),
                    'total' => $query->found_posts,
                );
            },
            'permission_callback' => function() { return current_user_can( 'edit_posts' ); },
            'meta' => self::$mcp_meta,
        ) );

        // Update Post
        wp_register_ability( 'wordpress/update-post', array(
            'label' => 'Update WordPress Post',
            'description' => 'Update an existing WordPress post.',
            'category' => self::$category,
            'input_schema' => array(
                'type' => 'object',
                'properties' => array(
                    'id' => array( 'type' => 'integer', 'description' => 'Post ID to update.' ),
                    'title' => array( 'type' => 'string', 'description' => 'New title.' ),
                    'content' => array( 'type' => 'string', 'description' => 'New content (HTML or Markdown).' ),
                    'content_type' => array( 'type' => 'string', 'description' => 'Content format: html or markdown. Default: html.', 'default' => 'html' ),
                    'status' => array( 'type' => 'string', 'description' => 'New status: draft, publish, pending.' ),
                    'excerpt' => array( 'type' => 'string', 'description' => 'New excerpt.' ),
                    'categories' => array( 'type' => 'array', 'description' => 'New category IDs.' ),
                    'tags' => array( 'type' => 'array', 'description' => 'New tag IDs.' ),
                    'featured_media' => array( 'type' => 'integer', 'description' => 'New featured image attachment ID.' ),
                ),
                'required' => array( 'id' ),
            ),
            'output_schema' => array( 'type' => 'object' ),
            'execute_callback' => array( $this, 'execute_update_post' ),
            'permission_callback' => function() { return current_user_can( 'edit_posts' ); },
            'meta' => self::$mcp_meta,
        ) );

        // Delete Post
        wp_register_ability( 'wordpress/delete-post', array(
            'label' => 'Delete WordPress Post',
            'description' => 'Delete a WordPress post.',
            'category' => self::$category,
            'input_schema' => array(
                'type' => 'object',
                'properties' => array(
                    'id' => array( 'type' => 'integer', 'description' => 'Post ID to delete.' ),
                    'force' => array( 'type' => 'boolean', 'description' => 'Bypass trash and force deletion. Default: false.', 'default' => false ),
                ),
                'required' => array( 'id' ),
            ),
            'output_schema' => array( 'type' => 'object' ),
            'execute_callback' => function( $input ) {
                $post_id = absint( $input['id'] );
                $post = get_post( $post_id );
                
                if ( ! $post ) {
                    return new WP_Error( 'not_found', 'Post not found.', array( 'status' => 404 ) );
                }

                $force = isset( $input['force'] ) && $input['force'];
                $result = wp_delete_post( $post_id, $force );

                if ( ! $result ) {
                    return new WP_Error( 'delete_failed', 'Failed to delete post.', array( 'status' => 500 ) );
                }

                return array(
                    'success' => true,
                    'deleted_id' => $post_id,
                    'trashed' => ! $force,
                );
            },
            'permission_callback' => function() { return current_user_can( 'delete_posts' ); },
            'meta' => self::$mcp_meta,
        ) );

        // Create Page
        wp_register_ability( 'wordpress/create-page', array(
            'label' => 'Create WordPress Page',
            'description' => 'Create a new WordPress page.',
            'category' => self::$category,
            'input_schema' => array(
                'type' => 'object',
                'properties' => array(
                    'title' => array( 'type' => 'string', 'description' => 'Page title.' ),
                    'content' => array( 'type' => 'string', 'description' => 'Page content (HTML or Markdown).' ),
                    'content_type' => array( 'type' => 'string', 'description' => 'Content format: html or markdown. Default: html.', 'default' => 'html' ),
                    'status' => array( 'type' => 'string', 'description' => 'Page status: draft, publish, pending. Default: draft.', 'default' => 'draft' ),
                    'parent' => array( 'type' => 'integer', 'description' => 'Parent page ID for hierarchical pages.' ),
                    'template' => array( 'type' => 'string', 'description' => 'Page template to use.' ),
                    'featured_media' => array( 'type' => 'integer', 'description' => 'Featured image attachment ID.' ),
                ),
                'required' => array( 'title' ),
            ),
            'output_schema' => array( 'type' => 'object' ),
            'execute_callback' => function( $input ) {
                $content = isset( $input['content'] ) ? $input['content'] : '';

                // Convert markdown if needed
                if ( isset( $input['content_type'] ) && $input['content_type'] === 'markdown' && ! empty( $content ) ) {
                    $content = $this->convert_markdown_to_html( $content );
                }

                $post_data = array(
                    'post_title' => sanitize_text_field( $input['title'] ),
                    'post_content' => wp_kses_post( $content ),
                    'post_status' => isset( $input['status'] ) ? sanitize_key( $input['status'] ) : 'draft',
                    'post_type' => 'page',
                );

                if ( isset( $input['parent'] ) ) {
                    $post_data['post_parent'] = absint( $input['parent'] );
                }

                $post_id = wp_insert_post( $post_data, true );
                if ( is_wp_error( $post_id ) ) {
                    return $post_id;
                }

                // Set page template
                if ( isset( $input['template'] ) ) {
                    update_post_meta( $post_id, '_wp_page_template', sanitize_text_field( $input['template'] ) );
                }

                // Set featured image
                if ( isset( $input['featured_media'] ) ) {
                    set_post_thumbnail( $post_id, absint( $input['featured_media'] ) );
                }

                return array(
                    'success' => true,
                    'page_id' => $post_id,
                    'link' => get_permalink( $post_id ),
                    'edit_url' => get_edit_post_link( $post_id, 'raw' ),
                );
            },
            'permission_callback' => function() { return current_user_can( 'publish_pages' ); },
            'meta' => self::$mcp_meta,
        ) );
    }

    public function execute_create_post( $input ) {
        $content = $input['content'];

        // Convert markdown if needed
        if ( isset( $input['content_type'] ) && $input['content_type'] === 'markdown' ) {
            $content = $this->convert_markdown_to_html( $content );
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

        return array(
            'success' => true,
            'post_id' => $post_id,
            'link' => get_permalink( $post_id ),
            'edit_url' => get_edit_post_link( $post_id, 'raw' ),
        );
    }

    public function execute_update_post( $input ) {
        $post_id = absint( $input['id'] );
        $post = get_post( $post_id );
        
        if ( ! $post ) {
            return new WP_Error( 'not_found', 'Post not found.', array( 'status' => 404 ) );
        }

        $post_data = array( 'ID' => $post_id );

        if ( isset( $input['title'] ) ) {
            $post_data['post_title'] = sanitize_text_field( $input['title'] );
        }
        if ( isset( $input['content'] ) ) {
            $content = $input['content'];
            if ( isset( $input['content_type'] ) && $input['content_type'] === 'markdown' ) {
                $content = $this->convert_markdown_to_html( $content );
            }
            $post_data['post_content'] = wp_kses_post( $content );
        }
        if ( isset( $input['status'] ) ) {
            $post_data['post_status'] = sanitize_key( $input['status'] );
        }
        if ( isset( $input['excerpt'] ) ) {
            $post_data['post_excerpt'] = sanitize_textarea_field( $input['excerpt'] );
        }
        if ( isset( $input['categories'] ) && is_array( $input['categories'] ) ) {
            $post_data['post_category'] = array_map( 'absint', $input['categories'] );
        }
        if ( isset( $input['tags'] ) && is_array( $input['tags'] ) ) {
            $post_data['tags_input'] = array_map( 'absint', $input['tags'] );
        }

        $result = wp_update_post( $post_data, true );
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        // Set featured image
        if ( isset( $input['featured_media'] ) ) {
            set_post_thumbnail( $post_id, absint( $input['featured_media'] ) );
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

        return $html;
    }
}

WordPress_Content_Abilities_Plugin::instance();
