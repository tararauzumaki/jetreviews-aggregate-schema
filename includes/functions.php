<?php
/**
 * Helper functions for JetReview Aggregate Schema
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get aggregate rating data for a specific post
 * 
 * @param int $post_id Post ID
 * @return array|false Aggregate data or false if no data
 */
function jra_get_aggregate_rating($post_id = null) {
    $plugin = JetReview_Aggregate_Schema::get_instance();
    return $plugin->get_jetreview_aggregate_data($post_id);
}

/**
 * Display aggregate rating shortcode
 * 
 * Usage: [jra_aggregate_rating post_id="123" show="stars"]
 * 
 * @param array $atts Shortcode attributes
 * @return string HTML output
 */
function jra_aggregate_rating_shortcode($atts) {
    $atts = shortcode_atts(array(
        'post_id' => get_the_ID(),
        'show' => 'all', // all, rating, count, stars
        'class' => 'jra-aggregate-rating'
    ), $atts);
    
    $data = jra_get_aggregate_rating($atts['post_id']);
    
    if (!$data) {
        return '';
    }
    
    $output = '<div class="' . esc_attr($atts['class']) . '">';
    
    switch ($atts['show']) {
        case 'rating':
            $output .= '<span class="jra-rating">' . round($data['average_rating'], 1) . '%</span>';
            break;
            
        case 'count':
            $output .= '<span class="jra-count">' . $data['review_count'] . ' ' . _n('review', 'reviews', $data['review_count'], 'jetreview-aggregate-schema') . '</span>';
            break;
            
        case 'stars':
            $stars = round($data['average_rating'] / 20); // Convert to 5-star scale
            $output .= '<span class="jra-stars">';
            for ($i = 1; $i <= 5; $i++) {
                if ($i <= $stars) {
                    $output .= '<span class="star filled">★</span>';
                } else {
                    $output .= '<span class="star empty">☆</span>';
                }
            }
            $output .= '</span>';
            break;
            
        default: // 'all'
            $output .= '<span class="jra-rating">' . round($data['average_rating'], 1) . '%</span> ';
            $output .= '<span class="jra-separator">-</span> ';
            $output .= '<span class="jra-count">' . $data['review_count'] . ' ' . _n('review', 'reviews', $data['review_count'], 'jetreview-aggregate-schema') . '</span>';
            break;
    }
    
    $output .= '</div>';
    
    return $output;
}
add_shortcode('jra_aggregate_rating', 'jra_aggregate_rating_shortcode');

/**
 * Get schema markup for a specific post
 * 
 * @param int $post_id Post ID
 * @return array|false Schema data or false
 */
function jra_get_schema_markup($post_id = null) {
    $plugin = JetReview_Aggregate_Schema::get_instance();
    return $plugin->generate_aggregate_schema($post_id);
}

/**
 * Output schema markup manually
 * 
 * @param int $post_id Post ID
 * @param bool $echo Whether to echo or return
 * @return string|void Schema markup
 */
function jra_output_schema_markup($post_id = null, $echo = true) {
    $schema = jra_get_schema_markup($post_id);
    
    if (!$schema) {
        return '';
    }
    
    $output = "\n<!-- JetReview Aggregate Schema -->\n";
    $output .= '<script type="application/ld+json">' . "\n";
    $output .= wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n";
    $output .= '</script>' . "\n";
    $output .= "<!-- /JetReview Aggregate Schema -->\n\n";
    
    if ($echo) {
        echo $output;
    } else {
        return $output;
    }
}

/**
 * Check if a post has aggregate reviews
 * 
 * @param int $post_id Post ID
 * @return bool True if has reviews
 */
function jra_has_aggregate_reviews($post_id = null) {
    $data = jra_get_aggregate_rating($post_id);
    return $data && $data['review_count'] > 0;
}

/**
 * Get formatted rating display
 * 
 * @param int $post_id Post ID
 * @param string $format Format: percentage, stars, decimal
 * @return string Formatted rating
 */
function jra_get_formatted_rating($post_id = null, $format = 'percentage') {
    $data = jra_get_aggregate_rating($post_id);
    
    if (!$data) {
        return '';
    }
    
    // JetReviews already stores ratings as percentages (1-100)
    $percentage = $data['average_rating'];
    
    switch ($format) {
        case 'percentage':
            return round($percentage, 1) . '%';
            
        case 'decimal':
            return round($percentage / 10, 1); // Convert to 1-10 scale
            
        case 'stars':
            return round($percentage / 20, 1); // Convert to 5-star scale
            
        default:
            return $percentage;
    }
}

/**
 * Template tag for displaying aggregate rating in themes
 */
function jra_the_aggregate_rating($post_id = null, $show = 'all') {
    echo jra_aggregate_rating_shortcode(array(
        'post_id' => $post_id ?: get_the_ID(),
        'show' => $show
    ));
}

/**
 * Get review count
 * 
 * @param int $post_id Post ID
 * @return int Review count
 */
function jra_get_review_count($post_id = null) {
    $data = jra_get_aggregate_rating($post_id);
    return $data ? $data['review_count'] : 0;
}

/**
 * Get average rating
 * 
 * @param int $post_id Post ID
 * @return float Average rating
 */
function jra_get_average_rating($post_id = null) {
    $data = jra_get_aggregate_rating($post_id);
    return $data ? $data['average_rating'] : 0;
}

/**
 * Widget for displaying aggregate ratings
 */
class JRA_Aggregate_Rating_Widget extends WP_Widget {
    
    public function __construct() {
        parent::__construct(
            'jra_aggregate_rating',
            __('Aggregate Rating', 'jetreview-aggregate-schema'),
            array('description' => __('Display aggregate rating from JetReviews', 'jetreview-aggregate-schema'))
        );
    }
    
    public function widget($args, $instance) {
        if (!is_singular()) {
            return;
        }
        
        $data = jra_get_aggregate_rating();
        if (!$data) {
            return;
        }
        
        $title = !empty($instance['title']) ? $instance['title'] : __('User Rating', 'jetreview-aggregate-schema');
        $show_format = !empty($instance['format']) ? $instance['format'] : 'all';
        
        echo $args['before_widget'];
        
        if ($title) {
            echo $args['before_title'] . apply_filters('widget_title', $title) . $args['after_title'];
        }
        
        echo jra_aggregate_rating_shortcode(array(
            'show' => $show_format,
            'class' => 'jra-widget-rating'
        ));
        
        echo $args['after_widget'];
    }
    
    public function form($instance) {
        $title = !empty($instance['title']) ? $instance['title'] : __('User Rating', 'jetreview-aggregate-schema');
        $format = !empty($instance['format']) ? $instance['format'] : 'all';
        ?>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('title')); ?>"><?php _e('Title:'); ?></label>
            <input class="widefat" id="<?php echo esc_attr($this->get_field_id('title')); ?>" name="<?php echo esc_attr($this->get_field_name('title')); ?>" type="text" value="<?php echo esc_attr($title); ?>">
        </p>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('format')); ?>"><?php _e('Display Format:', 'jetreview-aggregate-schema'); ?></label>
            <select class="widefat" id="<?php echo esc_attr($this->get_field_id('format')); ?>" name="<?php echo esc_attr($this->get_field_name('format')); ?>">
                <option value="all" <?php selected($format, 'all'); ?>><?php _e('Rating + Count', 'jetreview-aggregate-schema'); ?></option>
                <option value="rating" <?php selected($format, 'rating'); ?>><?php _e('Rating Only', 'jetreview-aggregate-schema'); ?></option>
                <option value="count" <?php selected($format, 'count'); ?>><?php _e('Count Only', 'jetreview-aggregate-schema'); ?></option>
                <option value="stars" <?php selected($format, 'stars'); ?>><?php _e('Star Rating', 'jetreview-aggregate-schema'); ?></option>
            </select>
        </p>
        <?php
    }
    
    public function update($new_instance, $old_instance) {
        $instance = array();
        $instance['title'] = (!empty($new_instance['title'])) ? sanitize_text_field($new_instance['title']) : '';
        $instance['format'] = (!empty($new_instance['format'])) ? sanitize_text_field($new_instance['format']) : 'all';
        return $instance;
    }
}

// Register widget
add_action('widgets_init', function() {
    register_widget('JRA_Aggregate_Rating_Widget');
});
