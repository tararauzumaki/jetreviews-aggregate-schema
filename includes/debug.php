<?php
/**
 * Debugging and testing utilities for JetReview Aggregate Schema
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Debug utilities class
 */
class JRA_Debug {
    
    /**
     * Log debug information
     */
    public static function log($message, $data = null) {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }
        
        $log_entry = '[JRA] ' . $message;
        if ($data !== null) {
            $log_entry .= ' | Data: ' . print_r($data, true);
        }
        
        error_log($log_entry);
    }
    
    /**
     * Display debug information in admin
     */
    public static function display_debug_info() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $debug_info = self::get_debug_info();
        
        ?>
        <div class="jra-debug-panel">
            <h3><?php _e('Debug Information', 'jetreview-aggregate-schema'); ?></h3>
            <div class="jra-debug-grid">
                <?php foreach ($debug_info as $section => $data): ?>
                    <div class="jra-debug-section">
                        <h4><?php echo esc_html($section); ?></h4>
                        <ul>
                            <?php foreach ($data as $key => $value): ?>
                                <li>
                                    <strong><?php echo esc_html($key); ?>:</strong>
                                    <span class="jra-debug-value <?php echo is_array($value) || is_object($value) ? 'jra-debug-complex' : ''; ?>">
                                        <?php 
                                        if (is_bool($value)) {
                                            echo $value ? 'Yes' : 'No';
                                        } elseif (is_array($value) || is_object($value)) {
                                            echo '<pre>' . esc_html(print_r($value, true)) . '</pre>';
                                        } else {
                                            echo esc_html($value);
                                        }
                                        ?>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Get comprehensive debug information
     */
    public static function get_debug_info() {
        global $wpdb;
        
        $debug_info = array();
        
        // Plugin information
        $debug_info['Plugin'] = array(
            'Version' => JRA_SCHEMA_VERSION,
            'Plugin Directory' => JRA_SCHEMA_PLUGIN_DIR,
            'Plugin URL' => JRA_SCHEMA_PLUGIN_URL
        );
        
        // WordPress information
        $debug_info['WordPress'] = array(
            'Version' => get_bloginfo('version'),
            'Site URL' => site_url(),
            'Admin Email' => get_option('admin_email'),
            'Timezone' => get_option('timezone_string')
        );
        
        // JetReviews information
        $jetreview_active = class_exists('Jet_Reviews');
        $debug_info['JetReviews'] = array(
            'Plugin Active' => $jetreview_active,
            'Version' => $jetreview_active ? (defined('JET_REVIEWS_VERSION') ? JET_REVIEWS_VERSION : 'Unknown') : 'N/A'
        );
        
        // Database information
        $reviews_table = $wpdb->prefix . 'jet_reviews';
        $meta_table = $wpdb->prefix . 'jet_review_meta';
        
        $reviews_exists = $wpdb->get_var("SHOW TABLES LIKE '$reviews_table'") == $reviews_table;
        $meta_exists = $wpdb->get_var("SHOW TABLES LIKE '$meta_table'") == $meta_table;
        
        $debug_info['Database'] = array(
            'Reviews Table Exists' => $reviews_exists,
            'Meta Table Exists' => $meta_exists,
            'Reviews Table' => $reviews_table,
            'Meta Table' => $meta_table
        );
        
        if ($reviews_exists) {
            $total_reviews = $wpdb->get_var("SELECT COUNT(*) FROM $reviews_table");
            $approved_reviews = $wpdb->get_var("SELECT COUNT(*) FROM $reviews_table WHERE approved = '1'");
            
            $debug_info['Database']['Total Reviews'] = $total_reviews;
            $debug_info['Database']['Approved Reviews'] = $approved_reviews;
        }
        
        // Plugin settings
        $settings = get_option('jra_schema_settings', array());
        $debug_info['Settings'] = $settings;
        
        // Current post information (if on singular page)
        if (is_singular()) {
            $post_id = get_the_ID();
            $plugin = JetReview_Aggregate_Schema::get_instance();
            $aggregate_data = $plugin->get_jetreview_aggregate_data($post_id);
            
            $debug_info['Current Post'] = array(
                'Post ID' => $post_id,
                'Post Type' => get_post_type(),
                'Post Title' => get_the_title(),
                'Has Aggregate Data' => $aggregate_data ? 'Yes' : 'No'
            );
            
            if ($aggregate_data) {
                $debug_info['Current Post']['Review Count'] = $aggregate_data['review_count'];
                $debug_info['Current Post']['Average Rating'] = $aggregate_data['average_rating'];
            }
        }
        
        // Server information
        $debug_info['Server'] = array(
            'PHP Version' => PHP_VERSION,
            'MySQL Version' => $wpdb->db_version(),
            'Memory Limit' => ini_get('memory_limit'),
            'Max Execution Time' => ini_get('max_execution_time')
        );
        
        return $debug_info;
    }
    
    /**
     * Test schema generation for a specific post
     */
    public static function test_schema_generation($post_id) {
        $plugin = JetReview_Aggregate_Schema::get_instance();
        
        $results = array(
            'post_id' => $post_id,
            'post_title' => get_the_title($post_id),
            'post_type' => get_post_type($post_id),
            'aggregate_data' => null,
            'schema_generated' => false,
            'schema_data' => null,
            'errors' => array()
        );
        
        try {
            // Test aggregate data retrieval
            $aggregate_data = $plugin->get_jetreview_aggregate_data($post_id);
            $results['aggregate_data'] = $aggregate_data;
            
            if (!$aggregate_data) {
                $results['errors'][] = 'No aggregate review data found for this post';
                return $results;
            }
            
            // Test schema generation
            $schema = $plugin->generate_aggregate_schema($post_id);
            if ($schema) {
                $results['schema_generated'] = true;
                $results['schema_data'] = $schema;
            } else {
                $results['errors'][] = 'Schema generation failed despite having aggregate data';
            }
            
        } catch (Exception $e) {
            $results['errors'][] = 'Exception: ' . $e->getMessage();
        }
        
        return $results;
    }
    
    /**
     * Validate schema markup
     */
    public static function validate_schema($schema) {
        $validation_results = array(
            'valid' => true,
            'warnings' => array(),
            'errors' => array()
        );
        
        // Required fields check
        $required_fields = array('@context', '@type', 'name', 'aggregateRating');
        foreach ($required_fields as $field) {
            if (!isset($schema[$field])) {
                $validation_results['errors'][] = "Missing required field: $field";
                $validation_results['valid'] = false;
            }
        }
        
        // AggregateRating validation
        if (isset($schema['aggregateRating'])) {
            $rating = $schema['aggregateRating'];
            $required_rating_fields = array('@type', 'ratingValue', 'ratingCount');
            
            foreach ($required_rating_fields as $field) {
                if (!isset($rating[$field])) {
                    $validation_results['errors'][] = "Missing required aggregateRating field: $field";
                    $validation_results['valid'] = false;
                }
            }
            
            // Validate rating values
            if (isset($rating['ratingValue'])) {
                $rating_value = floatval($rating['ratingValue']);
                if ($rating_value < 1 || $rating_value > 100) {
                    $validation_results['warnings'][] = 'Rating value should be between 1-100 for percentage scale';
                }
            }
            
            if (isset($rating['ratingCount']) && $rating['ratingCount'] < 1) {
                $validation_results['errors'][] = 'Rating count must be at least 1';
                $validation_results['valid'] = false;
            }
        }
        
        return $validation_results;
    }
    
    /**
     * Generate test report
     */
    public static function generate_test_report() {
        $report = array(
            'timestamp' => current_time('mysql'),
            'plugin_version' => JRA_SCHEMA_VERSION,
            'wordpress_version' => get_bloginfo('version'),
            'jetreview_status' => class_exists('Jet_Reviews') ? 'Active' : 'Inactive',
            'tests' => array()
        );
        
        // Test with sample posts
        $sample_posts = get_posts(array(
            'numberposts' => 5,
            'post_status' => 'publish'
        ));
        
        foreach ($sample_posts as $post) {
            $test_result = self::test_schema_generation($post->ID);
            $report['tests'][] = $test_result;
        }
        
        return $report;
    }
}

// Add debug information to admin if WP_DEBUG is enabled
if (defined('WP_DEBUG') && WP_DEBUG && is_admin()) {
    add_action('admin_footer', function() {
        if (isset($_GET['page']) && $_GET['page'] === 'jetreview-aggregate-schema') {
            echo '<style>
                .jra-debug-panel { margin-top: 20px; background: #f9f9f9; padding: 20px; border-radius: 5px; }
                .jra-debug-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; }
                .jra-debug-section { background: white; padding: 15px; border-radius: 3px; }
                .jra-debug-section ul { margin: 0; }
                .jra-debug-section li { margin-bottom: 8px; }
                .jra-debug-value pre { font-size: 11px; max-height: 100px; overflow-y: auto; }
            </style>';
            JRA_Debug::display_debug_info();
        }
    });
}
