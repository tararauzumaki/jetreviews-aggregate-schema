# JetReviews Aggregate Schema

A WordPress plugin that generates aggregate review schema markup using data from JetReviews, specifically designed for anime database sites with 1-100% rating scale.

## Features

- **Automatic Schema Generation**: Extracts review data from JetReviews and creates proper JSON-LD schema markup
- **Direct 1-100% Rating Scale**: Uses JetReviews ratings directly as they are already stored in percentage format
- **Custom Post Type Mappings**: Map Anime Series, Anime Movies, and Manga to appropriate schema types (TVSeries, Movie, Book)
- **Slim SEO Schema Integration**: Enhance existing Slim SEO Schema with aggregate rating data
- **Multiple Output Modes**: Standalone, reviews-only, or integration with existing schema plugins
- **Schema.org Compliance**: Includes both ratingCount and reviewCount fields
- **SEO Optimized**: Improves search engine visibility with rich snippets for anime databases
- **Flexible Integration**: Works standalone or enhances existing schema markup

## Requirements

- WordPress 5.0+
- PHP 7.4+
- JetReviews plugin (by Crocoblock)

## Installation

1. Upload the plugin folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Ensure JetReviews is installed and active
4. Configure settings under Settings > JetReview Schema

## Configuration

### Settings Page

Access the settings page via **Settings > JetReview Schema** in your WordPress admin.

**Available Options:**

- **Post Type Mappings**: Map each custom post type to its appropriate Schema.org type
  - Anime Series → TVSeries
  - Anime Movies → Movie  
  - Manga → Book
  - Use "No Schema (Reviews Only)" for integration with existing schema plugins

- **Slim SEO Schema Integration**: Enable to enhance existing Slim SEO Schema markup with review data

- **Organization Settings**: Your site/organization details for schema markup

- **Debug Tools**: Test database connection and preview generated schema

### Schema Output

The plugin automatically adds JSON-LD structured data to the `<head>` section of enabled post types when:

- The post has approved JetReviews
- At least one review exists with a rating
- The current page is a singular post/page

## Schema Structure

The generated schema includes:

```json
{
  "@context": "https://schema.org",
  "@type": "TVSeries",
  "name": "Anime Series Title",
  "url": "https://yoursite.com/anime/series-title",
  "aggregateRating": {
    "@type": "AggregateRating",
    "ratingValue": 85.5,
    "bestRating": 100,
    "worstRating": 1,
    "ratingCount": 42,
    "reviewCount": 38
  },
  "genre": ["Action", "Drama"],
  "datePublished": "2023-01-15",
  "author": {
    "@type": "Organization", 
    "name": "Your Anime Database"
  },
  "image": "https://yoursite.com/image.jpg",
  "description": "Anime description..."
}
```

**For Slim SEO Schema Integration:**
When integrated with Slim SEO Schema, only the `aggregateRating` property is added to your existing schema:

```json
{
  "@context": "https://schema.org",
  "@type": "TVSeries",
  "name": "Your Existing Schema Title",
  "... existing Slim SEO Schema properties ...",
  "aggregateRating": {
    "@type": "AggregateRating",
    "ratingValue": 85.5,
    "bestRating": 100,
    "worstRating": 1,
    "ratingCount": 42,
    "reviewCount": 38
  }
}
```

## Hooks and Filters

### Filters

```php
// Modify schema data before output
add_filter('jra_schema_data', function($schema, $post_id) {
    // Your modifications
    return $schema;
}, 10, 2);

// Customize rating conversion
add_filter('jra_schema_rating_conversion', function($converted_rating, $original_rating) {
    // Custom conversion logic
    return $converted_rating;
}, 10, 2);

// Modify supported taxonomies for genres
add_filter('jra_schema_genre_taxonomies', function($taxonomies) {
    $taxonomies[] = 'custom_anime_genre';
    return $taxonomies;
});
```

### Actions

```php
// Before schema output
add_action('jra_schema_before_output', function($post_id, $schema) {
    // Your code here
}, 10, 2);

// After schema output
add_action('jra_schema_after_output', function($post_id, $schema) {
    // Your code here  
}, 10, 2);
```

## Testing Schema Markup

Test your generated schema using:

- [Google Rich Results Test](https://search.google.com/test/rich-results)
- [Schema.org Validator](https://validator.schema.org/)
- [Structured Data Testing Tool](https://developers.google.com/search/docs/advanced/structured-data)

## Database Queries

The plugin queries JetReviews data using optimized queries:

```sql
SELECT 
    COUNT(*) as review_count,
    AVG(CAST(rating AS DECIMAL(10,2))) as average_rating
FROM wp_jet_reviews
WHERE post_id = %d 
AND approved = '1'
AND rating > 0
```

## Troubleshooting

### Common Issues

**Schema not appearing:**
- Verify JetReviews is active and configured
- Check that the post type is enabled in settings
- Ensure the post has approved reviews with ratings

**Incorrect ratings:**
- The plugin uses JetReviews ratings directly as they are already stored in percentage format (1-100)
- No conversion is applied to the rating values from JetReviews
- If you see unexpected values, check your JetReviews configuration

**Genre not showing:**
- Check that your anime posts use supported taxonomies: `anime_genre`, `genre`, `anime_category`, or `category`

### Debug Mode

Add this to your `wp-config.php` to enable debug logging:

```php
define('JRA_SCHEMA_DEBUG', true);
```

## Support

For issues specific to this plugin, please check:

1. WordPress debug logs
2. JetReviews configuration
3. Database table structure (`wp_jet_reviews` and `wp_jet_review_meta`)

## Changelog

### 1.0.0
- Initial release
- Support for 1-100% rating scale
- Integration with JetReviews
- Multiple schema types
- Admin settings page
- Genre detection for anime content

## License

GPL v2 or later
