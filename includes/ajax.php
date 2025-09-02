<?php
/**
 * AJAX handlers for JetReview Aggregate Schema
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * AJAX handler class
 */
class JRA_AJAX_Handler {
    
    public function __construct() {
        add_action('wp_ajax_jra_get_schema_preview', array($this, 'get_schema_preview'));
        add_action('wp_ajax_jra_test_jetreview_connection', array($this, 'test_jetreview_connection'));
        add_action('wp_ajax_jra_refresh_schema_cache', array($this, 'refresh_schema_cache'));
    }
    
    /**
     * Get schema preview for admin
     */
    public function get_schema_preview() {
        check_ajax_referer('jra_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(-1);
        }
        
        $post_id = intval($_POST['post_id']);
        if (!$post_id) {
            wp_send_json_error('Invalid post ID');
        }
        
        $plugin = JetReview_Aggregate_Schema::get_instance();
        $schema = $plugin->generate_aggregate_schema($post_id);
        
        if (!$schema) {
            wp_send_json_error('No aggregate review data found');
        }
        
        $formatted_schema = wp_json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        wp_send_json_success($formatted_schema);
    }
    
    /**
     * Test JetReview connection
     */
    public function test_jetreview_connection() {
        check_ajax_referer('jra_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(-1);
        }
        
        global $wpdb;
        
        $results = array(
            'jetreview_active' => class_exists('Jet_Reviews'),
            'tables_exist' => false,
            'sample_data' => false,
            'review_count' => 0
        );
        
        // Check if tables exist
        $reviews_table = $wpdb->prefix . 'jet_reviews';
        $meta_table = $wpdb->prefix . 'jet_review_meta';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$reviews_table'") == $reviews_table &&
            $wpdb->get_var("SHOW TABLES LIKE '$meta_table'") == $meta_table) {
            $results['tables_exist'] = true;
            
            // Count total reviews
            $review_count = $wpdb->get_var("SELECT COUNT(*) FROM $reviews_table WHERE approved = '1'");
            $results['review_count'] = intval($review_count);
            
            // Check for sample data
            if ($review_count > 0) {
                $sample = $wpdb->get_row("
                    SELECT post_id, rating
                    FROM $reviews_table
                    WHERE approved = '1' AND rating > 0
                    LIMIT 1
                ");
                
                if ($sample) {
                    $results['sample_data'] = array(
                        'post_id' => $sample->post_id,
                        'rating' => $sample->rating,
                        'post_title' => get_the_title($sample->post_id)
                    );
                }
            }
        }
        
        wp_send_json_success($results);
    }
    
    /**
     * Refresh schema cache
     */
    public function refresh_schema_cache() {
        check_ajax_referer('jra_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(-1);
        }
        
        // Clear any transients or cache related to schema
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_jra_schema_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_jra_schema_%'");
        
        wp_send_json_success('Cache cleared successfully');
    }
}

new JRA_AJAX_Handler();
