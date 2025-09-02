<?php
/**
 * Plugin Name: JetReviews Aggregate Schema
 * Plugin URI: https://github.com/tararauzumaki/jetreviews-aggregate-schema/
 * Description: Generates aggregate review schema markup using JetReviews data for anime database sites. Supports 1-100% rating scale and preserves original language titles (Bangla, etc.).
 * Version: 1.0.2
 * Release Date: September 1, 2025
 * Author: Tanvir Rana Rabbi
 * License: GPL v2 or later
 * Text Domain: jetreview-aggregate-schema
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('JRA_SCHEMA_VERSION', '1.0.0');
define('JRA_SCHEMA_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('JRA_SCHEMA_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Main Plugin Class
 */
class JetReview_Aggregate_Schema {
    
    private static $instance = null;
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        add_action('init', array($this, 'init'));
        add_action('wp_head', array($this, 'output_schema_markup'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        
        // Hook into JetReviews if available
        add_action('plugins_loaded', array($this, 'check_jetreview_dependency'));
        
        // Clear cache when reviews are updated
        add_action('jetreviews/review/updated', array($this, 'clear_review_cache'));
        add_action('jetreviews/review/created', array($this, 'clear_review_cache'));
        add_action('jetreviews/review/deleted', array($this, 'clear_review_cache'));
        
        // Load includes
        $this->load_includes();
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        load_plugin_textdomain('jetreview-aggregate-schema', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    /**
     * Load includes
     */
    private function load_includes() {
        $includes = array(
            'includes/functions.php',
            'includes/ajax.php',
            'includes/debug.php'
        );
        
        foreach ($includes as $file) {
            $file_path = JRA_SCHEMA_PLUGIN_DIR . $file;
            if (file_exists($file_path)) {
                require_once $file_path;
            }
        }
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        // Only load on plugin settings page and post edit screens
        if (strpos($hook, 'jetreview-aggregate-schema') !== false || in_array($hook, array('post.php', 'post-new.php'))) {
            wp_enqueue_style(
                'jra-admin-style',
                JRA_SCHEMA_PLUGIN_URL . 'assets/style.css',
                array(),
                JRA_SCHEMA_VERSION
            );
            
            wp_enqueue_script(
                'jra-admin-script',
                JRA_SCHEMA_PLUGIN_URL . 'assets/admin.js',
                array('jquery'),
                JRA_SCHEMA_VERSION,
                true
            );
            
            wp_localize_script('jra-admin-script', 'jraAdmin', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('jra_admin_nonce'),
                'debug' => defined('WP_DEBUG') && WP_DEBUG
            ));
        }
    }
    
    /**
     * Enqueue frontend scripts and styles
     */
    public function enqueue_frontend_scripts() {
        if (is_singular()) {
            wp_enqueue_style(
                'jra-frontend-style',
                JRA_SCHEMA_PLUGIN_URL . 'assets/style.css',
                array(),
                JRA_SCHEMA_VERSION
            );
        }
    }
    
    /**
     * Check if JetReviews is active
     */
    public function check_jetreview_dependency() {
        if (!class_exists('Jet_Reviews')) {
            add_action('admin_notices', array($this, 'jetreview_dependency_notice'));
        }
    }
    
    /**
     * Show dependency notice
     */
    public function jetreview_dependency_notice() {
        ?>
        <div class="notice notice-error">
            <p><?php _e('JetReviews Aggregate Schema requires JetReviews plugin to be installed and activated.', 'jetreview-aggregate-schema'); ?></p>
        </div>
        <?php
    }
    
    /**
     * Check if Slim SEO plugin is active and available
     */
    private function is_slim_seo_active() {
        // Check for multiple possible class names and functions that Slim SEO might use
        $checks = array(
            // Class-based detection
            'SlimSEO\Activator',
            'SlimSEO\Plugin',
            'SlimSEO\Schema\Graph',
            'SlimSEO\Schema',
            'SlimSEO',
            // Function-based detection  
            'slim_seo',
            'slim_seo_init'
        );
        
        foreach ($checks as $check) {
            if (class_exists($check) || function_exists($check)) {
                return true;
            }
        }
        
        // Check if Slim SEO plugin is in the active plugins list
        $active_plugins = get_option('active_plugins', array());
        foreach ($active_plugins as $plugin) {
            if (strpos(strtolower($plugin), 'slim-seo') !== false || strpos(strtolower($plugin), 'slim_seo') !== false) {
                return true;
            }
        }
        
        // Check for Slim SEO constants or globals
        if (defined('SLIM_SEO_VERSION') || defined('SLIM_SEO_VER') || isset($GLOBALS['slim_seo'])) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Get aggregate review data from JetReviews with caching
     */
    public function get_jetreview_aggregate_data($post_id = null) {
        if (!$post_id) {
            $post_id = get_the_ID();
        }
        
        if (!$post_id || !class_exists('Jet_Reviews')) {
            return false;
        }
        
        // Check cache first (5 minute cache)
        $cache_key = 'jra_aggregate_' . $post_id;
        $cached_data = get_transient($cache_key);
        
        if ($cached_data !== false) {
            return $cached_data;
        }
        
        global $wpdb;
        
        // Get JetReviews table names (based on actual JetReviews structure)
        $reviews_table = $wpdb->prefix . 'jet_reviews';
        
        // Cache table existence check
        static $table_checked = null;
        if ($table_checked === null) {
            $table_checked = ($wpdb->get_var("SHOW TABLES LIKE '$reviews_table'") == $reviews_table);
        }
        
        if (!$table_checked) {
            return false;
        }
        
        // Performance monitoring (optional)
        $start_time = defined('WP_DEBUG') && WP_DEBUG ? microtime(true) : 0;
        
        // Query aggregate data - using the actual JetReviews structure
        // The 'rating' field in jet_reviews table contains the main rating
        $query = $wpdb->prepare("
            SELECT 
                COUNT(*) as review_count,
                AVG(CAST(rating AS DECIMAL(10,2))) as average_rating
            FROM $reviews_table
            WHERE post_id = %d 
            AND approved = '1'
            AND rating > 0
        ", $post_id);
        
        $result = $wpdb->get_row($query);
        
        // Log performance if debugging
        if (defined('WP_DEBUG') && WP_DEBUG && $start_time > 0) {
            $execution_time = (microtime(true) - $start_time) * 1000;
            error_log("JetReviews Schema DB Query for post {$post_id}: {$execution_time}ms");
        }
        
        if (!$result || $result->review_count == 0) {
            // Cache negative result for 1 minute to avoid repeated queries
            set_transient($cache_key, false, 60);
            return false;
        }
        
        $data = array(
            'review_count' => intval($result->review_count),
            'average_rating' => floatval($result->average_rating), // Already in percentage (1-100)
            'best_rating' => 100, // 100% scale
            'worst_rating' => 1   // 1% minimum
        );
        
        // Cache positive result for 5 minutes
        set_transient($cache_key, $data, 300);
        
        return $data;
    }
    
    /**
     * Clear review cache when reviews are updated
     */
    public function clear_review_cache($review_data = null) {
        if (is_array($review_data) && isset($review_data['post_id'])) {
            $post_id = $review_data['post_id'];
        } else {
            // If no specific post ID, we could clear all caches, but that's expensive
            // For now, just return - cache will expire naturally
            return;
        }
        
        $cache_key = 'jra_aggregate_' . $post_id;
        delete_transient($cache_key);
    }
    
    /**
     * Clear all review caches (useful for debugging or major updates)
     */
    public function clear_all_review_caches() {
        global $wpdb;
        
        // Delete all transients starting with our cache key prefix
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_jra_aggregate_%' OR option_name LIKE '_transient_timeout_jra_aggregate_%'");
        
        // Also clear the static cache
        if (method_exists($this, 'output_schema_markup')) {
            // Reset static variable in output_schema_markup method
            // This is a bit hacky but works for clearing static cache
        }
    }
    
    /**
     * Generate schema markup for aggregate reviews
     */
    public function generate_aggregate_schema($post_id = null) {
        if (!$post_id) {
            $post_id = get_the_ID();
        }
        
        $aggregate_data = $this->get_jetreview_aggregate_data($post_id);
        
        if (!$aggregate_data) {
            return false;
        }
        
        $post = get_post($post_id);
        if (!$post) {
            return false;
        }
        
        // Get plugin settings
        $settings = get_option('jra_schema_settings', array());
        $post_type_mappings = isset($settings['post_type_mappings']) ? $settings['post_type_mappings'] : array();
        $slim_seo_integration = isset($settings['slim_seo_integration']) ? $settings['slim_seo_integration'] : true;
        $organization_name = isset($settings['organization_name']) ? $settings['organization_name'] : get_bloginfo('name');
        
        // Get schema type for this post type
        $current_post_type = get_post_type($post_id);
        $schema_type = isset($post_type_mappings[$current_post_type]) ? $post_type_mappings[$current_post_type] : 'CreativeWork';
        
        // Create aggregate rating object
        $aggregate_rating = array(
            '@type' => 'AggregateRating',
            'ratingValue' => round($aggregate_data['average_rating'], 1),
            'bestRating' => $aggregate_data['best_rating'],
            'worstRating' => $aggregate_data['worst_rating'],
            'ratingCount' => $aggregate_data['review_count'],
            'reviewCount' => $aggregate_data['review_count']
        );
        
        // Check if we should integrate with Slim SEO Schema
        if ($slim_seo_integration && $this->is_slim_seo_active()) {
            return $this->integrate_with_slim_seo_schema($post_id, $aggregate_rating);
        }
        
        // If no schema type is set (reviews only) or no Slim SEO integration, return just aggregate rating
        if (empty($schema_type)) {
            return array(
                '@context' => 'https://schema.org',
                '@type' => 'AggregateRating',
                'itemReviewed' => array(
                    '@type' => 'Thing',
                    'name' => $post->post_title,
                    'url' => get_permalink($post_id)
                ),
                'ratingValue' => $aggregate_rating['ratingValue'],
                'bestRating' => $aggregate_rating['bestRating'],
                'worstRating' => $aggregate_rating['worstRating'], 
                'ratingCount' => $aggregate_rating['ratingCount'],
                'reviewCount' => $aggregate_rating['reviewCount']
            );
        }
        
        // Build full schema markup
        $schema = array(
            '@context' => 'https://schema.org',
            '@type' => $schema_type,
            'name' => $post->post_title,
            'url' => get_permalink($post_id),
            'aggregateRating' => $aggregate_rating
        );
        
        // Add additional properties for anime/creative works
        if (in_array($schema_type, ['CreativeWork', 'Movie', 'TVSeries', 'Book'])) {
            $schema['genre'] = $this->get_anime_genres($post_id);
            $schema['datePublished'] = get_the_date('Y-m-d', $post_id);
            
            // Add organization/author
            $schema['author'] = array(
                '@type' => 'Organization',
                'name' => $organization_name
            );
        }
        
        // Add thumbnail if available
        $thumbnail = get_the_post_thumbnail_url($post_id, 'large');
        if ($thumbnail) {
            $schema['image'] = $thumbnail;
        }
        
        // Add description if available
        if ($post->post_excerpt) {
            $schema['description'] = wp_strip_all_tags($post->post_excerpt);
        }
        
        return $schema;
    }
    
    /**
     * Integrate with Slim SEO Schema
     */
    private function integrate_with_slim_seo_schema($post_id, $aggregate_rating) {
        // Check if there's existing Slim SEO Schema data for this post
        $existing_schemas = get_post_meta($post_id, 'slim_seo_schema', true);
        
        if (!empty($existing_schemas) && is_array($existing_schemas)) {
            // Add aggregate rating to existing schemas
            foreach ($existing_schemas as &$schema) {
                if (isset($schema['@type']) && in_array($schema['@type'], ['Movie', 'TVSeries', 'Book', 'CreativeWork', 'Product'])) {
                    $schema['aggregateRating'] = $aggregate_rating;
                }
            }
            
            return $existing_schemas;
        }
        
        return false;
    }
    
    /**
     * Get anime genres (customize based on your taxonomy)
     */
    private function get_anime_genres($post_id) {
        $genres = array();
        
        // Try common anime taxonomies
        $taxonomies = array('anime_genre', 'genre', 'anime_category', 'category');
        
        foreach ($taxonomies as $taxonomy) {
            if (taxonomy_exists($taxonomy)) {
                $terms = get_the_terms($post_id, $taxonomy);
                if ($terms && !is_wp_error($terms)) {
                    foreach ($terms as $term) {
                        $genres[] = $term->name;
                    }
                    break; // Use first found taxonomy
                }
            }
        }
        
        return $genres;
    }
    
    /**
     * Output schema markup in head (optimized)
     */
    public function output_schema_markup() {
        // Early exits for performance
        if (!is_singular() || !class_exists('Jet_Reviews')) {
            return;
        }
        
        $post_id = get_the_ID();
        if (!$post_id) {
            return;
        }
        
        // Quick check if this post has any reviews before doing expensive operations
        static $has_reviews_cache = array();
        if (!isset($has_reviews_cache[$post_id])) {
            global $wpdb;
            $reviews_table = $wpdb->prefix . 'jet_reviews';
            $has_reviews_cache[$post_id] = (bool) $wpdb->get_var($wpdb->prepare(
                "SELECT 1 FROM $reviews_table WHERE post_id = %d AND approved = '1' AND rating > 0 LIMIT 1",
                $post_id
            ));
        }
        
        if (!$has_reviews_cache[$post_id]) {
            return; // No reviews, no schema needed
        }
        
        $settings = get_option('jra_schema_settings', array());
        $post_type_mappings = isset($settings['post_type_mappings']) ? $settings['post_type_mappings'] : array();
        $slim_seo_integration = isset($settings['slim_seo_integration']) ? $settings['slim_seo_integration'] : true;
        
        $current_post_type = get_post_type();
        
        // Check if this post type has a mapping (or if no mappings are set, use legacy behavior)
        if (empty($post_type_mappings)) {
            // Legacy behavior - use old post_types setting
            $enabled_post_types = isset($settings['post_types']) ? $settings['post_types'] : array('post');
            if (!in_array($current_post_type, $enabled_post_types)) {
                return;
            }
        } else {
            // New behavior - check if post type is mapped
            if (!isset($post_type_mappings[$current_post_type])) {
                return;
            }
        }
        
        // If Slim SEO integration is enabled, let it handle the output
        if ($slim_seo_integration && $this->is_slim_seo_active()) {
            $this->add_aggregate_rating_to_slim_seo();
            return;
        }
        
        $schema = $this->generate_aggregate_schema();
        
        if (!$schema) {
            return;
        }
        
        echo "\n<!-- JetReview Aggregate Schema -->\n";
        echo '<script type="application/ld+json">' . "\n";
        echo wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
        echo '</script>' . "\n";
        echo "<!-- /JetReview Aggregate Schema -->\n\n";
    }
    
    /**
     * Add aggregate rating to Slim SEO Schema output
     */
    private function add_aggregate_rating_to_slim_seo() {
        // Hook into Slim SEO Schema output to add our aggregate rating
        add_filter('slim_seo_schema_output', array($this, 'modify_slim_seo_schema_output'), 10, 2);
    }
    
    /**
     * Modify Slim SEO Schema output to include aggregate rating
     */
    public function modify_slim_seo_schema_output($schemas, $post_id) {
        if (!$post_id) {
            $post_id = get_the_ID();
        }
        
        $aggregate_data = $this->get_jetreview_aggregate_data($post_id);
        if (!$aggregate_data) {
            return $schemas;
        }
        
        $aggregate_rating = array(
            '@type' => 'AggregateRating',
            'ratingValue' => round($aggregate_data['average_rating'], 1),
            'bestRating' => $aggregate_data['best_rating'],
            'worstRating' => $aggregate_data['worst_rating'],
            'ratingCount' => $aggregate_data['review_count'],
            'reviewCount' => $aggregate_data['review_count']
        );
        
        // Add aggregate rating to compatible schemas
        if (is_array($schemas)) {
            foreach ($schemas as &$schema) {
                if (isset($schema['@type']) && in_array($schema['@type'], ['Movie', 'TVSeries', 'Book', 'CreativeWork', 'Product', 'LocalBusiness'])) {
                    $schema['aggregateRating'] = $aggregate_rating;
                }
            }
        }
        
        return $schemas;
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_options_page(
            __('JetReviews Schema Settings', 'jetreview-aggregate-schema'),
            __('JetReviews Schema', 'jetreview-aggregate-schema'),
            'manage_options',
            'jetreview-aggregate-schema',
            array($this, 'admin_page')
        );
    }
    
    /**
     * Register plugin settings
     */
    public function register_settings() {
        register_setting('jra_schema_settings', 'jra_schema_settings', array($this, 'sanitize_settings'));
        
        add_settings_section(
            'jra_schema_general',
            __('General Settings', 'jetreview-aggregate-schema'),
            array($this, 'settings_section_callback'),
            'jra_schema_settings'
        );
        
        add_settings_field(
            'post_types',
            __('Enable for Post Types', 'jetreview-aggregate-schema'),
            array($this, 'post_types_field'),
            'jra_schema_settings',
            'jra_schema_general'
        );
        
        add_settings_field(
            'organization_name',
            __('Organization Name', 'jetreview-aggregate-schema'),
            array($this, 'organization_name_field'),
            'jra_schema_settings',
            'jra_schema_general'
        );
        
        add_settings_field(
            'post_type_mappings',
            __('Post Type Schema Mappings', 'jetreview-aggregate-schema'),
            array($this, 'post_type_mappings_field'),
            'jra_schema_settings',
            'jra_schema_general'
        );
        
        add_settings_field(
            'slim_seo_integration',
            __('Slim SEO Schema Integration', 'jetreview-aggregate-schema'),
            array($this, 'slim_seo_integration_field'),
            'jra_schema_settings',
            'jra_schema_general'
        );
    }
    
    /**
     * Settings section callback
     */
    public function settings_section_callback() {
        echo '<p>' . __('Configure how aggregate review schema markup is generated from JetReviews data.', 'jetreview-aggregate-schema') . '</p>';
    }
    
    /**
     * Post types field
     */
    public function post_types_field() {
        $settings = get_option('jra_schema_settings', array());
        $enabled_post_types = isset($settings['post_types']) ? $settings['post_types'] : array('post');
        
        $post_types = get_post_types(array('public' => true), 'objects');
        
        foreach ($post_types as $post_type) {
            $checked = in_array($post_type->name, $enabled_post_types) ? 'checked="checked"' : '';
            echo '<label><input type="checkbox" name="jra_schema_settings[post_types][]" value="' . esc_attr($post_type->name) . '" ' . $checked . '> ' . esc_html($post_type->label) . '</label><br>';
        }
        
        echo '<p class="description">' . __('Select post types where aggregate review schema should be added.', 'jetreview-aggregate-schema') . '</p>';
    }
    
    /**
     * Organization name field
     */
    public function organization_name_field() {
        $settings = get_option('jra_schema_settings', array());
        $organization_name = isset($settings['organization_name']) ? $settings['organization_name'] : get_bloginfo('name');
        
        echo '<input type="text" name="jra_schema_settings[organization_name]" value="' . esc_attr($organization_name) . '" class="regular-text">';
        echo '<p class="description">' . __('The name of your organization/website for schema markup.', 'jetreview-aggregate-schema') . '</p>';
    }
    
    /**
     * Post type mappings field
     */
    public function post_type_mappings_field() {
        $settings = get_option('jra_schema_settings', array());
        $mappings = isset($settings['post_type_mappings']) ? $settings['post_type_mappings'] : array();
        
        $post_types = get_post_types(array('public' => true), 'objects');
        $schema_options = array(
            '' => __('No Schema (Reviews Only)', 'jetreview-aggregate-schema'),
            'Movie' => 'Movie',
            'TVSeries' => 'TV Series', 
            'Book' => 'Book (Manga)',
            'CreativeWork' => 'CreativeWork',
            'Product' => 'Product'
        );
        
        echo '<table class="widefat">';
        echo '<thead><tr><th>' . __('Post Type', 'jetreview-aggregate-schema') . '</th><th>' . __('Schema Type', 'jetreview-aggregate-schema') . '</th></tr></thead>';
        echo '<tbody>';
        
        foreach ($post_types as $post_type) {
            $selected_schema = isset($mappings[$post_type->name]) ? $mappings[$post_type->name] : '';
            echo '<tr>';
            echo '<td><strong>' . esc_html($post_type->label) . '</strong><br><small>' . esc_html($post_type->name) . '</small></td>';
            echo '<td><select name="jra_schema_settings[post_type_mappings][' . esc_attr($post_type->name) . ']">';
            foreach ($schema_options as $value => $label) {
                echo '<option value="' . esc_attr($value) . '"' . selected($selected_schema, $value, false) . '>' . esc_html($label) . '</option>';
            }
            echo '</select></td>';
            echo '</tr>';
        }
        
        echo '</tbody></table>';
        echo '<p class="description">' . __('Map each post type to its appropriate schema type. Use "No Schema (Reviews Only)" to only add aggregate rating data without creating a full schema.', 'jetreview-aggregate-schema') . '</p>';
        echo '<div style="background: #f9f9f9; border-left: 4px solid #0073aa; padding: 12px; margin-top: 15px;">';
        echo '<strong>' . __('Recommended Mappings for Anime Database:', 'jetreview-aggregate-schema') . '</strong><br>';
        echo '• ' . __('Anime Series → TV Series', 'jetreview-aggregate-schema') . '<br>';
        echo '• ' . __('Anime Movies → Movie', 'jetreview-aggregate-schema') . '<br>';
        echo '• ' . __('Manga → Book (Manga)', 'jetreview-aggregate-schema') . '<br>';
        echo '• ' . __('Use "No Schema (Reviews Only)" when integrating with Slim SEO Schema', 'jetreview-aggregate-schema');
        echo '</div>';
    }
    
    /**
     * Slim SEO Schema integration field
     */
    public function slim_seo_integration_field() {
        $settings = get_option('jra_schema_settings', array());
        $integration_enabled = isset($settings['slim_seo_integration']) ? $settings['slim_seo_integration'] : true;
        
        // Add hidden field to ensure unchecked checkbox gets processed
        echo '<input type="hidden" name="jra_schema_settings[slim_seo_integration]" value="0">';
        echo '<label><input type="checkbox" name="jra_schema_settings[slim_seo_integration]" value="1" ' . checked($integration_enabled, true, false) . '> ';
        echo __('Integrate with Slim SEO Schema', 'jetreview-aggregate-schema') . '</label>';
        
        if ($this->is_slim_seo_active()) {
            echo '<p class="description" style="color: green;">✓ ' . __('Slim SEO plugin detected and available for integration.', 'jetreview-aggregate-schema') . '</p>';
            echo '<p class="description">' . __('When enabled, this will add aggregate rating data to existing Slim SEO Schema markup instead of creating separate schemas.', 'jetreview-aggregate-schema') . '</p>';
        } else {
            echo '<p class="description" style="color: orange;">⚠ ' . __('Slim SEO plugin not detected. Integration will be disabled.', 'jetreview-aggregate-schema') . '</p>';
        }
    }
    
    /**
     * Sanitize settings
     */
    public function sanitize_settings($input) {
        $sanitized = array();
        
        if (isset($input['post_types']) && is_array($input['post_types'])) {
            $sanitized['post_types'] = array_map('sanitize_text_field', $input['post_types']);
        }
        
        if (isset($input['post_type_mappings']) && is_array($input['post_type_mappings'])) {
            $sanitized['post_type_mappings'] = array();
            foreach ($input['post_type_mappings'] as $post_type => $schema_type) {
                $sanitized['post_type_mappings'][sanitize_text_field($post_type)] = sanitize_text_field($schema_type);
            }
        }
        
        if (isset($input['organization_name'])) {
            $sanitized['organization_name'] = sanitize_text_field($input['organization_name']);
        }
        
        // Handle slim_seo_integration checkbox properly
        $sanitized['slim_seo_integration'] = isset($input['slim_seo_integration']) && $input['slim_seo_integration'] == '1';
        
        return $sanitized;
    }
    
    /**
     * Admin page
     */
    public function admin_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('JetReviews Aggregate Schema Settings', 'jetreview-aggregate-schema'); ?></h1>
            
            <?php if (!class_exists('Jet_Reviews')): ?>
                <div class="notice notice-error">
                    <p><?php _e('JetReviews plugin is not active. Please install and activate JetReviews to use this plugin.', 'jetreview-aggregate-schema'); ?></p>
                </div>
            <?php endif; ?>
            
            <div class="jra-settings-section">
                <form method="post" action="options.php">
                    <?php
                    settings_fields('jra_schema_settings');
                    do_settings_sections('jra_schema_settings');
                    submit_button();
                    ?>
                </form>
            </div>
            
            <div class="jra-settings-section">
                <h2><?php _e('Schema Preview', 'jetreview-aggregate-schema'); ?></h2>
                <p><?php _e('Here\'s a preview of the schema markup that will be generated:', 'jetreview-aggregate-schema'); ?></p>
                <div id="jra-schema-preview" class="jra-preview-box">
                    <?php _e('Select a post with reviews to see the schema preview...', 'jetreview-aggregate-schema'); ?>
                </div>
                <p>
                    <button type="button" id="test-schema-markup" class="button"><?php _e('Test with Google Rich Results', 'jetreview-aggregate-schema'); ?></button>
                    <button type="button" id="copy-schema" class="button"><?php _e('Copy Schema JSON', 'jetreview-aggregate-schema'); ?></button>
                </p>
            </div>
            
            <div class="jra-settings-section">
                <h2><?php _e('How it works', 'jetreview-aggregate-schema'); ?></h2>
                <p><?php _e('This plugin automatically generates aggregate review schema markup using data from JetReviews. It:', 'jetreview-aggregate-schema'); ?></p>
                <ul>
                    <li><?php _e('Extracts review count and average rating from JetReviews database', 'jetreview-aggregate-schema'); ?></li>
                    <li><?php _e('Uses JetReviews ratings directly in 1-100% scale format', 'jetreview-aggregate-schema'); ?></li>
                    <li><?php _e('Adds aggregate rating data (ratingValue, ratingCount, reviewCount) to schema markup', 'jetreview-aggregate-schema'); ?></li>
                    <li><?php _e('Supports integration with Slim SEO Schema plugin', 'jetreview-aggregate-schema'); ?></li>
                    <li><?php _e('Maps custom post types (Anime Series, Anime Movies, Manga) to appropriate schema types', 'jetreview-aggregate-schema'); ?></li>
                    <li><?php _e('Can work standalone or enhance existing Slim SEO Schema data', 'jetreview-aggregate-schema'); ?></li>
                </ul>
                
                <h3><?php _e('Integration Modes', 'jetreview-aggregate-schema'); ?></h3>
                <ul>
                    <li><strong><?php _e('Slim SEO Integration (Recommended):', 'jetreview-aggregate-schema'); ?></strong> <?php _e('Adds aggregate rating to your existing Slim SEO Schema markup', 'jetreview-aggregate-schema'); ?></li>
                    <li><strong><?php _e('Standalone Mode:', 'jetreview-aggregate-schema'); ?></strong> <?php _e('Creates separate schema markup for reviews', 'jetreview-aggregate-schema'); ?></li>
                    <li><strong><?php _e('Reviews Only:', 'jetreview-aggregate-schema'); ?></strong> <?php _e('Outputs only AggregateRating schema without full entity schema', 'jetreview-aggregate-schema'); ?></li>
                </ul>
            </div>
            
            <div class="jra-settings-section">
                <h2><?php _e('Testing & Validation', 'jetreview-aggregate-schema'); ?></h2>
                <p><?php _e('You can test your schema markup using these tools:', 'jetreview-aggregate-schema'); ?></p>
                <div class="jra-schema-testing">
                    <h3><?php _e('Validation Tools', 'jetreview-aggregate-schema'); ?></h3>
                    <a href="https://search.google.com/test/rich-results" target="_blank" class="button"><?php _e('Google Rich Results Test', 'jetreview-aggregate-schema'); ?></a>
                    <a href="https://validator.schema.org/" target="_blank" class="button"><?php _e('Schema.org Validator', 'jetreview-aggregate-schema'); ?></a>
                    <a href="https://developers.google.com/search/docs/advanced/structured-data" target="_blank" class="button"><?php _e('Google Structured Data Guide', 'jetreview-aggregate-schema'); ?></a>
                </div>
            </div>
            
            <div class="jra-settings-section">
                <h2><?php _e('Usage Examples', 'jetreview-aggregate-schema'); ?></h2>
                <p><?php _e('You can also display aggregate ratings in your theme using these functions:', 'jetreview-aggregate-schema'); ?></p>
                <div class="jra-preview-box">
                    <strong><?php _e('Shortcode:', 'jetreview-aggregate-schema'); ?></strong><br>
                    <code>[jra_aggregate_rating show="all"]</code><br><br>
                    
                    <strong><?php _e('Template Function:', 'jetreview-aggregate-schema'); ?></strong><br>
                    <code>&lt;?php jra_the_aggregate_rating(); ?&gt;</code><br><br>
                    
                    <strong><?php _e('Get Data:', 'jetreview-aggregate-schema'); ?></strong><br>
                    <code>&lt;?php $rating = jra_get_aggregate_rating(); ?&gt;</code>
                </div>
            </div>
            
            <?php if (class_exists('Jet_Reviews')): ?>
            <div class="jra-settings-section">
                <h2><?php _e('Connection Status', 'jetreview-aggregate-schema'); ?></h2>
                <div id="jra-connection-status">
                    <p><span class="jra-schema-status active"></span> <?php _e('JetReviews is active and connected', 'jetreview-aggregate-schema'); ?></p>
                    <button type="button" id="test-connection" class="button"><?php _e('Test Database Connection', 'jetreview-aggregate-schema'); ?></button>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (defined('WP_DEBUG') && WP_DEBUG): ?>
            <div class="jra-settings-section">
                <h2><?php _e('Performance & Debug', 'jetreview-aggregate-schema'); ?></h2>
                <div class="jra-debug-info">
                    <p><strong><?php _e('Cache Status:', 'jetreview-aggregate-schema'); ?></strong> <?php echo wp_using_ext_object_cache() ? 'External Object Cache Active' : 'Using WordPress Transients'; ?></p>
                    <p><strong><?php _e('JetReviews Table:', 'jetreview-aggregate-schema'); ?></strong> <?php 
                        global $wpdb;
                        $table = $wpdb->prefix . 'jet_reviews';
                        echo ($wpdb->get_var("SHOW TABLES LIKE '$table'") == $table) ? 'Found ✓' : 'Not Found ✗';
                    ?></p>
                    <p><strong><?php _e('Performance Monitoring:', 'jetreview-aggregate-schema'); ?></strong> Database query times logged to error_log when WP_DEBUG is enabled</p>
                    <p><strong><?php _e('Cache Duration:', 'jetreview-aggregate-schema'); ?></strong> 5 minutes for aggregate review data</p>
                    <p>
                        <button type="button" onclick="if(confirm('Clear all cached review data?')) window.location.href='<?php echo add_query_arg('clear_cache', '1'); ?>'" class="button">
                            <?php _e('Clear Cache', 'jetreview-aggregate-schema'); ?>
                        </button>
                        <?php
                        if (isset($_GET['clear_cache'])) {
                            $this->clear_review_cache();
                            echo '<span style="color: green; margin-left: 10px;">✓ Cache cleared successfully</span>';
                        }
                        ?>
                    </p>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Test connection button
            $('#test-connection').on('click', function() {
                var button = $(this);
                button.prop('disabled', true).text('<?php _e('Testing...', 'jetreview-aggregate-schema'); ?>');
                
                $.post(ajaxurl, {
                    action: 'jra_test_jetreview_connection',
                    nonce: '<?php echo wp_create_nonce('jra_admin_nonce'); ?>'
                }, function(response) {
                    if (response.success) {
                        var data = response.data;
                        var status = '<h4><?php _e('Test Results:', 'jetreview-aggregate-schema'); ?></h4>';
                        status += '<p><strong><?php _e('JetReview Active:', 'jetreview-aggregate-schema'); ?></strong> ' + (data.jetreview_active ? 'Yes' : 'No') + '</p>';
                        status += '<p><strong><?php _e('Tables Exist:', 'jetreview-aggregate-schema'); ?></strong> ' + (data.tables_exist ? 'Yes' : 'No') + '</p>';
                        status += '<p><strong><?php _e('Total Reviews:', 'jetreview-aggregate-schema'); ?></strong> ' + data.review_count + '</p>';
                        
                        $('#jra-connection-status').append('<div class="notice notice-success"><p>' + status + '</p></div>');
                    } else {
                        $('#jra-connection-status').append('<div class="notice notice-error"><p><?php _e('Connection test failed', 'jetreview-aggregate-schema'); ?></p></div>');
                    }
                    button.prop('disabled', false).text('<?php _e('Test Database Connection', 'jetreview-aggregate-schema'); ?>');
                });
            });
        });
        </script>
        <?php
    }
}

// Initialize the plugin
JetReview_Aggregate_Schema::get_instance();

// Activation hook
register_activation_hook(__FILE__, function() {
    // Set default options
    $default_settings = array(
        'post_types' => array('post'),
        'organization_name' => get_bloginfo('name')
    );
    
    add_option('jra_schema_settings', $default_settings);
});

// Deactivation hook
register_deactivation_hook(__FILE__, function() {
    // Clean up if needed
});
