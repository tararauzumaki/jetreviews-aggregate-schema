# Installation Guide

## Quick Start

1. **Upload Plugin Files**
   - Upload the entire `jetreview-aggregate-schema` folder to `/wp-content/plugins/`
   - Or install via WordPress admin: Plugins > Add New > Upload Plugin

2. **Activate Plugin**
   - Go to Plugins page in WordPress admin
   - Click "Activate" for "JetReview Aggregate Schema"

3. **Configure Settings**
   - Navigate to Settings > JetReview Schema
   - Choose your preferred schema type (CreativeWork recommended for anime)
   - Select post types where schema should appear
   - Set your organization name
   - Save changes

4. **Verify Installation**
   - Visit any post/page with JetReviews
   - View page source and look for JSON-LD schema in the `<head>` section
   - Test with Google Rich Results Test tool

## Requirements Check

Before installation, ensure:

- ✅ WordPress 5.0 or higher
- ✅ PHP 7.4 or higher  
- ✅ JetReviews plugin installed and active
- ✅ Posts/pages with JetReviews data

## Configuration Options

### Schema Type
Choose the most appropriate schema type for your content:
- **CreativeWork** - General creative content (recommended for anime)
- **Movie** - For anime movies specifically
- **TVSeries** - For anime TV series
- **Book** - For manga/light novels
- **Product** - For merchandise/products

### Post Types
Enable schema markup for specific post types:
- Posts (default)
- Pages
- Custom post types (anime, movies, etc.)

### Organization Name
Your website/organization name that appears in schema markup. This helps with branding and credibility.

## Troubleshooting

### No Schema Appearing
1. Check that JetReviews is active
2. Verify the post has approved reviews with ratings
3. Ensure the post type is enabled in settings
4. Check browser console for JavaScript errors

### Wrong Rating Values
The plugin expects JetReviews to use 1-100 scale. If your reviews use a different scale, you may need customization.

### Database Connection Issues
1. Verify JetReviews tables exist in database
2. Check database connection in plugin debug panel
3. Ensure proper database permissions

### Testing Schema
Always test your schema markup with:
- [Google Rich Results Test](https://search.google.com/test/rich-results)
- [Schema.org Validator](https://validator.schema.org/)

## Advanced Configuration

### Hooks and Filters

Customize schema data:
```php
add_filter('jra_schema_data', function($schema, $post_id) {
    // Add custom fields
    $schema['custom_field'] = 'custom_value';
    return $schema;
}, 10, 2);
```

Modify rating conversion:
```php
add_filter('jra_schema_rating_conversion', function($converted_rating, $original_rating) {
    // Custom rating conversion logic
    return $converted_rating * 10; // Convert 1-10 to 1-100
}, 10, 2);
```

### Custom Taxonomies
Add support for custom anime genre taxonomies:
```php
add_filter('jra_schema_genre_taxonomies', function($taxonomies) {
    $taxonomies[] = 'anime_genre';
    $taxonomies[] = 'custom_category';
    return $taxonomies;
});
```

## Support

For technical support:
1. Check the debug information in plugin settings
2. Enable WordPress debug mode to see detailed logs
3. Test with sample posts to isolate issues
4. Verify JetReviews configuration and data

## Updates

When updating the plugin:
1. Backup your website
2. Update plugin files
3. Check settings page for any new options
4. Test schema output after update
5. Clear any caching plugins
