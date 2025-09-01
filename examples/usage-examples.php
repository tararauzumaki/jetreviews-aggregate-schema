<?php
/**
 * Example usage for JetReview Aggregate Schema Plugin
 * 
 * This file demonstrates various ways to use the plugin
 * in your WordPress theme or other plugins.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * BASIC USAGE EXAMPLES
 */

// 1. Display aggregate rating in your theme template
function my_theme_display_rating() {
    if (function_exists('jra_the_aggregate_rating')) {
        echo '<div class="anime-rating">';
        jra_the_aggregate_rating();
        echo '</div>';
    }
}

// 2. Get rating data for custom display
function my_custom_rating_display() {
    if (function_exists('jra_get_aggregate_rating')) {
        $rating_data = jra_get_aggregate_rating();
        
        if ($rating_data) {
            ?>
            <div class="custom-anime-rating">
                <div class="score"><?php echo round($rating_data['average_rating'], 1); ?>%</div>
                <div class="details">
                    Based on <?php echo $rating_data['review_count']; ?> reviews
                </div>
            </div>
            <?php
        }
    }
}

// 3. Shortcode usage in content
// [jra_aggregate_rating show="all"]
// [jra_aggregate_rating show="rating"]
// [jra_aggregate_rating show="count"]
// [jra_aggregate_rating show="stars"]

/**
 * ADVANCED CUSTOMIZATION EXAMPLES
 */

// 4. Modify schema data before output
add_filter('jra_schema_data', 'customize_anime_schema', 10, 2);
function customize_anime_schema($schema, $post_id) {
    // Add anime-specific data
    $anime_year = get_post_meta($post_id, 'anime_year', true);
    if ($anime_year) {
        $schema['datePublished'] = $anime_year . '-01-01';
    }
    
    // Add studio information
    $studio = get_post_meta($post_id, 'anime_studio', true);
    if ($studio) {
        $schema['productionCompany'] = array(
            '@type' => 'Organization',
            'name' => $studio
        );
    }
    
    // Add episode count for TV series
    if ($schema['@type'] === 'TVSeries') {
        $episodes = get_post_meta($post_id, 'episode_count', true);
        if ($episodes) {
            $schema['numberOfEpisodes'] = intval($episodes);
        }
    }
    
    return $schema;
}

// 5. Custom rating display with stars
function display_anime_rating_with_stars($post_id = null) {
    if (!function_exists('jra_get_aggregate_rating')) {
        return;
    }
    
    $rating_data = jra_get_aggregate_rating($post_id);
    if (!$rating_data) {
        return;
    }
    
    // JetReviews already stores ratings as percentages (1-100)
    $percentage = $rating_data['average_rating'];
    $stars = round($percentage / 20); // Convert percentage to 5-star scale
    $count = $rating_data['review_count'];
    
    ?>
    <div class="anime-rating-stars">
        <div class="stars-container">
            <?php for ($i = 1; $i <= 5; $i++): ?>
                <span class="star <?php echo $i <= $stars ? 'filled' : 'empty'; ?>">
                    <?php echo $i <= $stars ? '★' : '☆'; ?>
                </span>
            <?php endfor; ?>
        </div>
        <div class="rating-text">
            <?php echo $percentage; ?>% (<?php echo $count; ?> reviews)
        </div>
    </div>
    <?php
}

// 6. Check if post has reviews before displaying related content
function show_rating_dependent_content() {
    if (function_exists('jra_has_aggregate_reviews') && jra_has_aggregate_reviews()) {
        echo '<div class="highly-rated-badge">Highly Rated Anime!</div>';
    }
}

// 7. Custom widget for sidebar rating display
class Custom_Anime_Rating_Widget extends WP_Widget {
    
    public function __construct() {
        parent::__construct(
            'custom_anime_rating',
            'Anime Rating Display',
            array('description' => 'Display anime rating in sidebar')
        );
    }
    
    public function widget($args, $instance) {
        if (!is_singular() || !function_exists('jra_get_aggregate_rating')) {
            return;
        }
        
        $rating_data = jra_get_aggregate_rating();
        if (!$rating_data) {
            return;
        }
        
        echo $args['before_widget'];
        echo $args['before_title'] . 'User Rating' . $args['after_title'];
        
        ?>
        <div class="sidebar-anime-rating">
            <div class="big-score"><?php echo round($rating_data['average_rating'], 1); ?>%</div>
            <div class="rating-bar">
                <div class="fill" style="width: <?php echo $rating_data['average_rating']; ?>%"></div>
            </div>
            <div class="review-count"><?php echo $rating_data['review_count']; ?> reviews</div>
        </div>
        <?php
        
        echo $args['after_widget'];
    }
}

// Register the custom widget
add_action('widgets_init', function() {
    register_widget('Custom_Anime_Rating_Widget');
});

/**
 * THEME INTEGRATION EXAMPLES
 */

// 8. Add rating to post excerpts
add_filter('the_excerpt', 'add_rating_to_excerpt');
function add_rating_to_excerpt($excerpt) {
    if (is_singular() || !function_exists('jra_get_formatted_rating')) {
        return $excerpt;
    }
    
    $rating = jra_get_formatted_rating(get_the_ID(), 'percentage');
    if ($rating) {
        $excerpt .= '<div class="excerpt-rating">Rating: ' . $rating . '</div>';
    }
    
    return $excerpt;
}

// 9. Modify post title to include rating
add_filter('the_title', 'add_rating_to_title', 10, 2);
function add_rating_to_title($title, $post_id) {
    // Only on single posts and if it's the main title
    if (!is_singular() || !in_the_loop() || !is_main_query()) {
        return $title;
    }
    
    if (function_exists('jra_get_formatted_rating')) {
        $rating = jra_get_formatted_rating($post_id, 'percentage');
        if ($rating) {
            $title .= ' <span class="title-rating">(' . $rating . ')</span>';
        }
    }
    
    return $title;
}

// 10. Add rating to RSS feeds
add_filter('the_content_feed', 'add_rating_to_feed');
function add_rating_to_feed($content) {
    if (function_exists('jra_get_aggregate_rating')) {
        $rating_data = jra_get_aggregate_rating();
        if ($rating_data) {
            $rating_info = '<p><strong>User Rating:</strong> ' . 
                          round($rating_data['average_rating'], 1) . '% (' . 
                          $rating_data['review_count'] . ' reviews)</p>';
            $content = $rating_info . $content;
        }
    }
    return $content;
}

/**
 * CONDITIONAL CONTENT EXAMPLES
 */

// 11. Show different content based on rating
function rating_based_recommendations() {
    if (!function_exists('jra_get_average_rating')) {
        return;
    }
    
    $rating = jra_get_average_rating();
    
    if ($rating >= 90) {
        echo '<div class="recommendation masterpiece">⭐ Masterpiece - Must Watch!</div>';
    } elseif ($rating >= 80) {
        echo '<div class="recommendation great">👍 Great anime - Highly recommended</div>';
    } elseif ($rating >= 70) {
        echo '<div class="recommendation good">👌 Good anime - Worth watching</div>';
    } elseif ($rating >= 60) {
        echo '<div class="recommendation average">😐 Average - Your mileage may vary</div>';
    } else {
        echo '<div class="recommendation poor">👎 Below average - Proceed with caution</div>';
    }
}

// 12. Related posts based on similar ratings
function get_similarly_rated_anime($post_id = null, $limit = 5) {
    if (!function_exists('jra_get_average_rating')) {
        return array();
    }
    
    $current_rating = jra_get_average_rating($post_id);
    if (!$current_rating) {
        return array();
    }
    
    // Find posts with similar ratings (±10%)
    $min_rating = $current_rating - 10;
    $max_rating = $current_rating + 10;
    
    global $wpdb;
    
    // This is a simplified example - you'd need to join with your rating tables
    $similar_posts = get_posts(array(
        'numberposts' => $limit,
        'post__not_in' => array($post_id ?: get_the_ID()),
        'meta_query' => array(
            array(
                'key' => '_jra_cached_rating',
                'value' => array($min_rating, $max_rating),
                'compare' => 'BETWEEN',
                'type' => 'NUMERIC'
            )
        )
    ));
    
    return $similar_posts;
}

/**
 * CACHING EXAMPLES
 */

// 13. Cache rating data for performance
function get_cached_rating_data($post_id = null) {
    if (!$post_id) {
        $post_id = get_the_ID();
    }
    
    $cache_key = 'jra_rating_' . $post_id;
    $cached_data = get_transient($cache_key);
    
    if ($cached_data === false && function_exists('jra_get_aggregate_rating')) {
        $cached_data = jra_get_aggregate_rating($post_id);
        if ($cached_data) {
            // Cache for 1 hour
            set_transient($cache_key, $cached_data, HOUR_IN_SECONDS);
        }
    }
    
    return $cached_data;
}

// 14. Clear rating cache when reviews are updated
add_action('jet_reviews_review_saved', 'clear_rating_cache');
function clear_rating_cache($review_data) {
    if (isset($review_data['post_id'])) {
        $cache_key = 'jra_rating_' . $review_data['post_id'];
        delete_transient($cache_key);
    }
}

/**
 * CSS STYLING EXAMPLES
 */

// 15. Add inline styles for rating displays
function add_rating_styles() {
    ?>
    <style>
    .anime-rating-stars .star.filled { color: #ffc107; }
    .anime-rating-stars .star.empty { color: #e0e0e0; }
    .big-score { font-size: 2em; font-weight: bold; color: #ff6b35; }
    .rating-bar { background: #eee; height: 8px; border-radius: 4px; overflow: hidden; }
    .rating-bar .fill { background: linear-gradient(90deg, #ff4444, #ffaa00, #00aa00); height: 100%; }
    .recommendation.masterpiece { background: #d4edda; color: #155724; padding: 10px; border-radius: 5px; }
    .recommendation.great { background: #cce5ff; color: #004085; padding: 10px; border-radius: 5px; }
    </style>
    <?php
}
add_action('wp_head', 'add_rating_styles');

/**
 * DEBUGGING HELPERS
 */

// 16. Debug rating data (only for administrators)
function debug_rating_data($post_id = null) {
    if (!current_user_can('manage_options') || !function_exists('jra_get_aggregate_rating')) {
        return;
    }
    
    $data = jra_get_aggregate_rating($post_id);
    echo '<pre>Rating Debug: ';
    print_r($data);
    echo '</pre>';
}

// Usage examples in your theme files:
/*
<!-- In single.php or content-single.php -->
<?php my_theme_display_rating(); ?>

<!-- In sidebar.php -->
<?php show_rating_dependent_content(); ?>

<!-- In archive.php -->
<?php if (function_exists('jra_get_formatted_rating')): ?>
    <div class="archive-rating"><?php echo jra_get_formatted_rating(get_the_ID(), 'stars'); ?>/5</div>
<?php endif; ?>

<!-- Using shortcode in content -->
Rate this anime: [jra_aggregate_rating show="stars"]

<!-- In header.php for JSON-LD (automatic, but you can also call manually) -->
<?php if (function_exists('jra_output_schema_markup')) jra_output_schema_markup(); ?>
*/
