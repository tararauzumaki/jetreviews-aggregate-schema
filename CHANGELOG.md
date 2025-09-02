# Changelog

All notable changes to the JetReview Aggregate Schema plugin will be documented in this file.

## [2.0.0] - 2024-01-20

### Added
- **Custom Post Type Mappings**: Map individual post types (Anime Series, Anime Movies, Manga) to specific Schema.org types
- **Slim SEO Schema Integration**: Enhance existing Slim SEO Schema markup with aggregate rating data
- **Multiple Output Modes**: Choose between standalone schema, reviews-only mode, or integration with existing schema
- **reviewCount Field**: Added missing reviewCount field alongside ratingCount for full Schema.org compliance
- **Enhanced Admin Interface**: New post type mapping table with recommendations for anime sites
- **Integration Modes**: Support for working with existing schema plugins rather than creating duplicates

### Changed
- **Database Integration**: Improved JetReviews database queries for better performance
- **Schema Generation**: More flexible schema generation based on post type mappings
- **Admin Interface**: Reorganized settings page with clearer sections and integration options
- **CSS Styling**: Enhanced admin page styling with better visual hierarchy

### Fixed
- **Missing reviewCount**: Schema now includes both ratingCount and reviewCount fields
- **Post Type Flexibility**: Removed hard-coded schema type in favor of flexible mappings
- **Integration Conflicts**: Proper integration with existing schema plugins

## [1.0.0] - 2024-01-15ngelog

All notable changes to the JetReview Aggregate Schema plugin will be documented in this file.

## [1.0.0] - 2025-09-01

### Added
- Initial release of JetReview Aggregate Schema plugin
- Core functionality to extract aggregate review data from JetReviews
- Support for 1-100% rating scale as requested
- JSON-LD schema markup generation for multiple schema types:
  - CreativeWork (recommended for anime)
  - Movie
  - TVSeries  
  - Book
  - Product
- Admin settings page with comprehensive configuration options
- Automatic genre detection from common anime taxonomies
- Template functions and shortcodes for theme integration
- Widget for displaying aggregate ratings
- Debug utilities and connection testing
- AJAX functionality for admin preview and testing
- CSS styles for rating displays
- Comprehensive documentation and examples

### Features
- **Automatic Schema Generation**: Extracts data from JetReviews and creates proper JSON-LD markup
- **Multiple Display Formats**: Percentage, stars, count, or combined displays
- **Theme Integration**: Shortcodes, template functions, and widgets
- **SEO Optimized**: Proper structured data for search engines
- **Anime-Focused**: Genre support and metadata for anime databases
- **Performance Optimized**: Efficient database queries and optional caching
- **Developer Friendly**: Hooks, filters, and extensive examples

### Technical Details
- Minimum WordPress version: 5.0
- Minimum PHP version: 7.4
- Requires JetReviews plugin by Crocoblock
- Uses optimized database queries for performance
- Includes comprehensive error handling and validation
- Supports custom post types and taxonomies

### Files Structure
```
jetreview-aggregate-schema/
├── jetreview-aggregate-schema.php (Main plugin file)
├── README.md
├── INSTALL.md
├── CHANGELOG.md
├── includes/
│   ├── functions.php (Helper functions and shortcodes)
│   ├── ajax.php (AJAX handlers)
│   └── debug.php (Debug utilities)
├── assets/
│   ├── style.css (Frontend and admin styles)
│   └── admin.js (Admin JavaScript)
└── examples/
    └── usage-examples.php (Code examples)
```

### Schema Output Example
```json
{
  "@context": "https://schema.org",
  "@type": "CreativeWork", 
  "name": "Anime Title",
  "url": "https://yoursite.com/anime/title",
  "aggregateRating": {
    "@type": "AggregateRating",
    "ratingValue": 85.5,
    "bestRating": 100,
    "worstRating": 1,
    "ratingCount": 42
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

### Available Functions
- `jra_get_aggregate_rating($post_id)` - Get raw rating data
- `jra_the_aggregate_rating($post_id, $show)` - Display rating in theme
- `jra_has_aggregate_reviews($post_id)` - Check if post has reviews
- `jra_get_formatted_rating($post_id, $format)` - Get formatted rating
- `jra_output_schema_markup($post_id, $echo)` - Manual schema output

### Available Shortcodes
- `[jra_aggregate_rating]` - Display aggregate rating
- `[jra_aggregate_rating show="rating"]` - Show rating only
- `[jra_aggregate_rating show="count"]` - Show count only  
- `[jra_aggregate_rating show="stars"]` - Show star rating

### Available Hooks
- `jra_schema_data` filter - Modify schema before output
- `jra_schema_rating_conversion` filter - Custom rating conversion
- `jra_schema_genre_taxonomies` filter - Add genre taxonomies
- `jra_schema_before_output` action - Before schema output
- `jra_schema_after_output` action - After schema output

### Testing
- Compatible with Google Rich Results Test
- Validates with Schema.org validator
- Includes built-in debug tools and connection testing
- Comprehensive error handling and user feedback

### Future Planned Features
- Support for individual review schema markup
- Integration with other review systems
- Advanced caching options
- Multi-site support
- Custom rating scale configurations
- Import/export settings
- Review moderation integration

---

For technical support or feature requests, please ensure you have:
1. JetReviews plugin active and configured
2. Posts with approved reviews and ratings
3. WordPress debug logs if experiencing issues
4. Plugin debug information from settings page
