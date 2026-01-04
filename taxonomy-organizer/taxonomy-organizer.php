<?php
/**
 * Plugin Name: Taxonomy Organizer
 * Plugin URI: https://example.com/taxonomy-organizer
 * Description: Easily organize taxonomy terms with drag-and-drop interface and quick parent change options.
 * Version: 1.0.0
 * Author: Santron
 * License: GPL v2 or later
 * Text Domain: taxonomy-organizer
 */

if (!defined('ABSPATH')) {
    exit;
}

define('TAXORG_VERSION', '1.0.0');
define('TAXORG_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('TAXORG_PLUGIN_URL', plugin_dir_url(__FILE__));

class Taxonomy_Organizer {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_taxorg_update_term_parent', array($this, 'ajax_update_term_parent'));
        add_action('wp_ajax_taxorg_update_term_order', array($this, 'ajax_update_term_order'));
        add_action('wp_ajax_taxorg_get_terms', array($this, 'ajax_get_terms'));
        add_action('wp_ajax_taxorg_bulk_update_parents', array($this, 'ajax_bulk_update_parents'));
        add_action('wp_ajax_taxorg_add_term', array($this, 'ajax_add_term'));
        
        // Add quick edit parent change to term rows
        add_filter('tag_row_actions', array($this, 'add_quick_parent_action'), 10, 2);
        add_filter('category_row_actions', array($this, 'add_quick_parent_action'), 10, 2);
        
        // Add inline parent change modal
        add_action('admin_footer-edit-tags.php', array($this, 'add_parent_change_modal'));
    }
    
    public function add_admin_menu() {
        add_management_page(
            __('Taxonomy Organizer', 'taxonomy-organizer'),
            __('Taxonomy Organizer', 'taxonomy-organizer'),
            'manage_categories',
            'taxonomy-organizer',
            array($this, 'render_admin_page')
        );
    }
    
    public function enqueue_scripts($hook) {
        // Load on our admin page or on edit-tags.php
        if ($hook !== 'tools_page_taxonomy-organizer' && $hook !== 'edit-tags.php') {
            return;
        }
        
        wp_enqueue_style(
            'taxorg-styles',
            TAXORG_PLUGIN_URL . 'assets/css/taxonomy-organizer.css',
            array(),
            TAXORG_VERSION
        );
        
        wp_enqueue_script('jquery-ui-sortable');
        wp_enqueue_script('jquery-ui-draggable');
        wp_enqueue_script('jquery-ui-droppable');
        
        wp_enqueue_script(
            'taxorg-scripts',
            TAXORG_PLUGIN_URL . 'assets/js/taxonomy-organizer.js',
            array('jquery', 'jquery-ui-sortable', 'jquery-ui-draggable', 'jquery-ui-droppable'),
            TAXORG_VERSION,
            true
        );
        
        wp_localize_script('taxorg-scripts', 'taxorgData', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('taxorg_nonce'),
            'strings' => array(
                'confirmMove' => __('Move this term under a new parent?', 'taxonomy-organizer'),
                'success' => __('Term updated successfully!', 'taxonomy-organizer'),
                'error' => __('Error updating term. Please try again.', 'taxonomy-organizer'),
                'loading' => __('Loading...', 'taxonomy-organizer'),
                'noParent' => __('— No Parent —', 'taxonomy-organizer'),
                'selectParent' => __('Select new parent:', 'taxonomy-organizer'),
                'searchPlaceholder' => __('Search or select parent...', 'taxonomy-organizer'),
            )
        ));
    }
    
    public function render_admin_page() {
        $taxonomies = get_taxonomies(array('hierarchical' => true), 'objects');
        $current_taxonomy = isset($_GET['taxonomy']) ? sanitize_key($_GET['taxonomy']) : 'category';
        
        if (!isset($taxonomies[$current_taxonomy])) {
            $current_taxonomy = 'category';
        }
        
        ?>
        <div class="wrap taxorg-wrap">
            <h1><?php _e('Taxonomy Organizer', 'taxonomy-organizer'); ?></h1>
            <p class="description"><?php _e('Drag and drop terms to reorganize their hierarchy. Drop a term onto another to make it a child.', 'taxonomy-organizer'); ?></p>
            
            <div class="taxorg-controls">
                <label for="taxorg-taxonomy-select"><?php _e('Select Taxonomy:', 'taxonomy-organizer'); ?></label>
                <select id="taxorg-taxonomy-select">
                    <?php foreach ($taxonomies as $tax_slug => $tax_obj): ?>
                        <option value="<?php echo esc_attr($tax_slug); ?>" <?php selected($current_taxonomy, $tax_slug); ?>>
                            <?php echo esc_html($tax_obj->labels->name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <button type="button" id="taxorg-expand-all" class="button"><?php _e('Expand All', 'taxonomy-organizer'); ?></button>
                <button type="button" id="taxorg-collapse-all" class="button"><?php _e('Collapse All', 'taxonomy-organizer'); ?></button>
                <button type="button" id="taxorg-add-term" class="button button-primary"><span class="dashicons dashicons-plus-alt2"></span> <?php _e('Add Term', 'taxonomy-organizer'); ?></button>
            </div>
            
            <div class="taxorg-container">
                <div class="taxorg-sidebar">
                    <h3><?php _e('Drop Zone', 'taxonomy-organizer'); ?></h3>
                    <div id="taxorg-root-drop" class="taxorg-root-drop">
                        <p><?php _e('Drop here to make a top-level term (no parent)', 'taxonomy-organizer'); ?></p>
                    </div>
                    
                    <div class="taxorg-help">
                        <h4><?php _e('How to use:', 'taxonomy-organizer'); ?></h4>
                        <ul>
                            <li><?php _e('Drag a term onto another term to make it a child', 'taxonomy-organizer'); ?></li>
                            <li><?php _e('Drop on the zone above to remove parent', 'taxonomy-organizer'); ?></li>
                            <li><?php _e('Click the arrow to expand/collapse children', 'taxonomy-organizer'); ?></li>
                            <li><?php _e('Use the quick menu icon to change parent directly', 'taxonomy-organizer'); ?></li>
                        </ul>
                    </div>
                </div>
                
                <div class="taxorg-main">
                    <div id="taxorg-tree" class="taxorg-tree" data-taxonomy="<?php echo esc_attr($current_taxonomy); ?>">
                        <div class="taxorg-loading"><?php _e('Loading terms...', 'taxonomy-organizer'); ?></div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Quick Parent Change Modal -->
        <div id="taxorg-parent-modal" class="taxorg-modal" style="display:none;">
            <div class="taxorg-modal-content">
                <span class="taxorg-modal-close">&times;</span>
                <h2><?php _e('Change Parent', 'taxonomy-organizer'); ?></h2>
                <p class="taxorg-modal-term-name"></p>
                <div class="taxorg-modal-body">
                    <label for="taxorg-new-parent"><?php _e('Select new parent:', 'taxonomy-organizer'); ?></label>
                    <!-- Searchable dropdown will be inserted here by JavaScript -->
                </div>
                <div class="taxorg-modal-footer">
                    <button type="button" class="button button-secondary taxorg-modal-cancel"><?php _e('Cancel', 'taxonomy-organizer'); ?></button>
                    <button type="button" class="button button-primary taxorg-modal-save"><?php _e('Save Changes', 'taxonomy-organizer'); ?></button>
                </div>
                <input type="hidden" id="taxorg-modal-term-id" value="">
                <input type="hidden" id="taxorg-modal-taxonomy" value="">
            </div>
        </div>
        
        <!-- Add New Term Modal -->
        <div id="taxorg-add-modal" class="taxorg-modal" style="display:none;">
            <div class="taxorg-modal-content">
                <span class="taxorg-modal-close">&times;</span>
                <h2><?php _e('Add New Term', 'taxonomy-organizer'); ?></h2>
                <div class="taxorg-modal-body">
                    <div class="taxorg-form-field">
                        <label for="taxorg-new-term-name"><?php _e('Name:', 'taxonomy-organizer'); ?></label>
                        <input type="text" id="taxorg-new-term-name" class="widefat" placeholder="<?php esc_attr_e('Enter term name', 'taxonomy-organizer'); ?>">
                    </div>
                    <div class="taxorg-form-field">
                        <label for="taxorg-new-term-slug"><?php _e('Slug (optional):', 'taxonomy-organizer'); ?></label>
                        <input type="text" id="taxorg-new-term-slug" class="widefat" placeholder="<?php esc_attr_e('Auto-generated if empty', 'taxonomy-organizer'); ?>">
                    </div>
                    <div class="taxorg-form-field">
                        <label for="taxorg-new-term-parent"><?php _e('Parent:', 'taxonomy-organizer'); ?></label>
                        <!-- Searchable dropdown will be inserted here by JavaScript -->
                    </div>
                    <div class="taxorg-form-field">
                        <label for="taxorg-new-term-description"><?php _e('Description (optional):', 'taxonomy-organizer'); ?></label>
                        <textarea id="taxorg-new-term-description" class="widefat" rows="3" placeholder="<?php esc_attr_e('Enter description', 'taxonomy-organizer'); ?>"></textarea>
                    </div>
                </div>
                <div class="taxorg-modal-footer">
                    <button type="button" class="button button-secondary taxorg-modal-cancel"><?php _e('Cancel', 'taxonomy-organizer'); ?></button>
                    <button type="button" class="button button-primary taxorg-add-modal-save"><?php _e('Add Term', 'taxonomy-organizer'); ?></button>
                </div>
            </div>
        </div>
        
        <!-- Quick Add Inline Form -->
        <div id="taxorg-quick-add-form" class="taxorg-quick-add-form" style="display:none;">
            <input type="text" id="taxorg-quick-add-name" placeholder="<?php esc_attr_e('Enter term name...', 'taxonomy-organizer'); ?>" autocomplete="off">
            <button type="button" class="button button-primary taxorg-quick-add-save">
                <span class="dashicons dashicons-yes"></span>
            </button>
            <button type="button" class="button taxorg-quick-add-cancel">
                <span class="dashicons dashicons-no-alt"></span>
            </button>
            <input type="hidden" id="taxorg-quick-add-parent" value="">
            <input type="hidden" id="taxorg-quick-add-context-term" value="">
        </div>
        <?php
    }
    
    public function ajax_get_terms() {
        check_ajax_referer('taxorg_nonce', 'nonce');
        
        if (!current_user_can('manage_categories')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $taxonomy = isset($_POST['taxonomy']) ? sanitize_key($_POST['taxonomy']) : 'category';
        
        $terms = get_terms(array(
            'taxonomy' => $taxonomy,
            'hide_empty' => false,
            'orderby' => 'name',
            'order' => 'ASC',
        ));
        
        if (is_wp_error($terms)) {
            wp_send_json_error($terms->get_error_message());
        }
        
        $tree = $this->build_term_tree($terms);
        $html = $this->render_term_tree($tree, $taxonomy);
        
        // Build hierarchical terms list for dropdowns
        $hierarchical_terms = $this->get_hierarchical_terms_list($terms);
        
        wp_send_json_success(array(
            'html' => $html,
            'terms' => $terms,
            'hierarchicalTerms' => $hierarchical_terms,
        ));
    }
    
    private function get_hierarchical_terms_list($terms, $parent_id = 0, $depth = 0) {
        $result = array();
        
        foreach ($terms as $term) {
            if ($term->parent == $parent_id) {
                $result[] = array(
                    'term_id' => $term->term_id,
                    'name' => $term->name,
                    'parent' => $term->parent,
                    'depth' => $depth,
                );
                // Recursively add children
                $children = $this->get_hierarchical_terms_list($terms, $term->term_id, $depth + 1);
                $result = array_merge($result, $children);
            }
        }
        
        return $result;
    }
    
    private function build_term_tree($terms, $parent_id = 0) {
        $tree = array();
        
        foreach ($terms as $term) {
            if ($term->parent == $parent_id) {
                $children = $this->build_term_tree($terms, $term->term_id);
                $term->children = $children;
                $tree[] = $term;
            }
        }
        
        return $tree;
    }
    
    private function render_term_tree($terms, $taxonomy, $level = 0) {
        if (empty($terms)) {
            return '';
        }
        
        $html = '<ul class="taxorg-term-list" data-level="' . $level . '">';
        
        foreach ($terms as $term) {
            $has_children = !empty($term->children);
            $term_class = 'taxorg-term-item';
            if ($has_children) {
                $term_class .= ' has-children';
            }
            
            $html .= '<li class="' . esc_attr($term_class) . '" data-term-id="' . esc_attr($term->term_id) . '" data-parent-id="' . esc_attr($term->parent) . '">';
            $html .= '<div class="taxorg-term-row">';
            
            if ($has_children) {
                $html .= '<span class="taxorg-toggle dashicons dashicons-arrow-down-alt2"></span>';
            } else {
                $html .= '<span class="taxorg-toggle-placeholder"></span>';
            }
            
            $html .= '<span class="taxorg-drag-handle dashicons dashicons-move"></span>';
            $html .= '<span class="taxorg-term-name">' . esc_html($term->name) . '</span>';
            $html .= '<span class="taxorg-term-count">(' . intval($term->count) . ')</span>';
            $html .= '<span class="taxorg-term-actions">';
            $html .= '<button type="button" class="taxorg-quick-add button-link" title="' . esc_attr__('Add Term', 'taxonomy-organizer') . '" data-has-children="' . ($has_children ? '1' : '0') . '">';
            $html .= '<span class="dashicons dashicons-plus-alt"></span>';
            $html .= '</button>';
            $html .= '<button type="button" class="taxorg-quick-parent button-link" title="' . esc_attr__('Change Parent', 'taxonomy-organizer') . '">';
            $html .= '<span class="dashicons dashicons-networking"></span>';
            $html .= '</button>';
            $html .= '<a href="' . esc_url(get_edit_term_link($term->term_id, $taxonomy)) . '" class="taxorg-edit-link" title="' . esc_attr__('Edit Term', 'taxonomy-organizer') . '">';
            $html .= '<span class="dashicons dashicons-edit"></span>';
            $html .= '</a>';
            $html .= '</span>';
            $html .= '</div>';
            
            if ($has_children) {
                $html .= $this->render_term_tree($term->children, $taxonomy, $level + 1);
            }
            
            $html .= '</li>';
        }
        
        $html .= '</ul>';
        
        return $html;
    }
    
    public function ajax_update_term_parent() {
        check_ajax_referer('taxorg_nonce', 'nonce');
        
        if (!current_user_can('manage_categories')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $term_id = isset($_POST['term_id']) ? intval($_POST['term_id']) : 0;
        $new_parent = isset($_POST['new_parent']) ? intval($_POST['new_parent']) : 0;
        $taxonomy = isset($_POST['taxonomy']) ? sanitize_key($_POST['taxonomy']) : 'category';
        
        if (!$term_id) {
            wp_send_json_error('Invalid term ID');
        }
        
        // Prevent setting a term as its own parent
        if ($term_id === $new_parent) {
            wp_send_json_error('A term cannot be its own parent');
        }
        
        // Prevent circular references
        if ($new_parent > 0) {
            $ancestors = get_ancestors($new_parent, $taxonomy);
            if (in_array($term_id, $ancestors)) {
                wp_send_json_error('Cannot create circular reference');
            }
        }
        
        $result = wp_update_term($term_id, $taxonomy, array(
            'parent' => $new_parent,
        ));
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success(array(
            'message' => __('Term parent updated successfully', 'taxonomy-organizer'),
            'term_id' => $term_id,
            'new_parent' => $new_parent,
        ));
    }
    
    public function ajax_bulk_update_parents() {
        check_ajax_referer('taxorg_nonce', 'nonce');
        
        if (!current_user_can('manage_categories')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $updates = isset($_POST['updates']) ? $_POST['updates'] : array();
        $taxonomy = isset($_POST['taxonomy']) ? sanitize_key($_POST['taxonomy']) : 'category';
        
        $results = array();
        
        foreach ($updates as $update) {
            $term_id = intval($update['term_id']);
            $new_parent = intval($update['new_parent']);
            
            $result = wp_update_term($term_id, $taxonomy, array(
                'parent' => $new_parent,
            ));
            
            $results[] = array(
                'term_id' => $term_id,
                'success' => !is_wp_error($result),
            );
        }
        
        wp_send_json_success($results);
    }
    
    public function ajax_add_term() {
        check_ajax_referer('taxorg_nonce', 'nonce');
        
        if (!current_user_can('manage_categories')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
        $slug = isset($_POST['slug']) ? sanitize_title($_POST['slug']) : '';
        $parent = isset($_POST['parent']) ? intval($_POST['parent']) : 0;
        $description = isset($_POST['description']) ? sanitize_textarea_field($_POST['description']) : '';
        $taxonomy = isset($_POST['taxonomy']) ? sanitize_key($_POST['taxonomy']) : 'category';
        
        if (empty($name)) {
            wp_send_json_error(__('Term name is required', 'taxonomy-organizer'));
        }
        
        $args = array(
            'parent' => $parent,
            'description' => $description,
        );
        
        if (!empty($slug)) {
            $args['slug'] = $slug;
        }
        
        $result = wp_insert_term($name, $taxonomy, $args);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        $term = get_term($result['term_id'], $taxonomy);
        
        wp_send_json_success(array(
            'message' => __('Term added successfully', 'taxonomy-organizer'),
            'term_id' => $result['term_id'],
            'term' => $term,
        ));
    }
    
    public function ajax_update_term_order() {
        check_ajax_referer('taxorg_nonce', 'nonce');
        
        if (!current_user_can('manage_categories')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        // Term order is stored in term meta if needed
        $order = isset($_POST['order']) ? $_POST['order'] : array();
        $taxonomy = isset($_POST['taxonomy']) ? sanitize_key($_POST['taxonomy']) : 'category';
        
        foreach ($order as $position => $term_id) {
            update_term_meta(intval($term_id), 'taxorg_order', intval($position));
        }
        
        wp_send_json_success(array(
            'message' => __('Term order updated', 'taxonomy-organizer'),
        ));
    }
    
    public function add_quick_parent_action($actions, $term) {
        if (!current_user_can('manage_categories')) {
            return $actions;
        }
        
        $taxonomy = $term->taxonomy;
        $tax_obj = get_taxonomy($taxonomy);
        
        // Only for hierarchical taxonomies
        if (!$tax_obj || !$tax_obj->hierarchical) {
            return $actions;
        }
        
        $actions['change_parent'] = sprintf(
            '<a href="#" class="taxorg-inline-change-parent" data-term-id="%d" data-taxonomy="%s" data-term-name="%s">%s</a>',
            $term->term_id,
            esc_attr($taxonomy),
            esc_attr($term->name),
            __('Change Parent', 'taxonomy-organizer')
        );
        
        return $actions;
    }
    
    public function add_parent_change_modal() {
        $screen = get_current_screen();
        if (!$screen || $screen->base !== 'edit-tags') {
            return;
        }
        
        $taxonomy = $screen->taxonomy;
        $tax_obj = get_taxonomy($taxonomy);
        
        if (!$tax_obj || !$tax_obj->hierarchical) {
            return;
        }
        
        $terms = get_terms(array(
            'taxonomy' => $taxonomy,
            'hide_empty' => false,
            'orderby' => 'name',
        ));
        ?>
        <div id="taxorg-inline-parent-modal" class="taxorg-modal" style="display:none;">
            <div class="taxorg-modal-content">
                <span class="taxorg-modal-close">&times;</span>
                <h2><?php _e('Change Parent', 'taxonomy-organizer'); ?></h2>
                <p class="taxorg-modal-term-name"></p>
                <div class="taxorg-modal-body">
                    <label for="taxorg-inline-new-parent"><?php _e('Select new parent:', 'taxonomy-organizer'); ?></label>
                    <select id="taxorg-inline-new-parent" class="taxorg-parent-select">
                        <option value="0"><?php _e('— No Parent —', 'taxonomy-organizer'); ?></option>
                        <?php foreach ($terms as $term): ?>
                            <option value="<?php echo esc_attr($term->term_id); ?>">
                                <?php echo esc_html($term->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="taxorg-modal-footer">
                    <button type="button" class="button button-secondary taxorg-modal-cancel"><?php _e('Cancel', 'taxonomy-organizer'); ?></button>
                    <button type="button" class="button button-primary taxorg-inline-modal-save"><?php _e('Save Changes', 'taxonomy-organizer'); ?></button>
                </div>
                <input type="hidden" id="taxorg-inline-modal-term-id" value="">
                <input type="hidden" id="taxorg-inline-modal-taxonomy" value="<?php echo esc_attr($taxonomy); ?>">
            </div>
        </div>
        <?php
    }
}

// Initialize the plugin
add_action('plugins_loaded', array('Taxonomy_Organizer', 'get_instance'));
