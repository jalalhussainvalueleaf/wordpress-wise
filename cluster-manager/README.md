# Cluster Manager Plugin

A comprehensive WordPress plugin for managing cluster posts with Advanced Custom Fields (ACF) support and automatic shortcode generation.

## Features

- **Custom Post Type**: Creates a "Clusters" post type with custom taxonomies
- **ACF Integration**: Full support for Advanced Custom Fields with predefined field groups
- **Shortcode Generation**: Automatic shortcode generation for each cluster post
- **Admin Interface**: Comprehensive admin interface for managing clusters
- **Multiple Display Options**: Various shortcode styles and display options
- **Gallery Support**: Built-in image gallery functionality
- **Content Blocks**: Flexible content block system with repeater fields

## Installation

1. Upload the `cluster-manager` folder to your `/wp-content/plugins/` directory
2. Activate the plugin through the WordPress admin dashboard
3. Ensure Advanced Custom Fields (ACF) plugin is installed and activated
4. Visit the Clusters menu to start creating cluster posts

## Usage

### Creating Clusters

1. Navigate to **Clusters** → **Add New** in the WordPress admin
2. Fill in the basic post information (title, content, etc.)
3. Use the ACF fields to add:
   - Subtitle and description
   - Image gallery
   - Video URLs
   - Content blocks (text, images, videos, quotes, dividers)
   - Settings and configuration options

### Shortcodes

#### Single Cluster Display
```php
[cluster id="123"]
[cluster id="123" style="compact" show_title="true" show_content="true"]
```

#### Cluster List
```php
[cluster_list number="5" style="grid" category="featured"]
[cluster_list tag="important" featured="true" show_excerpt="true"]
```

#### Featured Clusters
```php
[cluster_featured number="3" style="featured"]
```

### Shortcode Parameters

#### [cluster] Shortcode
- `id` - Cluster post ID (required)
- `title` - Cluster title (alternative to ID)
- `style` - Display style: default, compact, featured
- `show_title` - Show/hide title (true/false)
- `show_content` - Show/hide content (true/false)
- `show_meta` - Show/hide meta information (true/false)
- `show_gallery` - Show/hide gallery (true/false)

#### [cluster_list] Shortcode
- `number` - Number of clusters to display
- `category` - Filter by category slug
- `tag` - Filter by tag slug
- `featured` - Show only featured clusters (true/false)
- `orderby` - Sort by: date, title, menu_order, rand
- `order` - Sort order: ASC, DESC
- `style` - Display style: grid, list
- `show_excerpt` - Show/hide excerpts (true/false)
- `show_thumbnail` - Show/hide thumbnails (true/false)
- `show_meta` - Show/hide meta information (true/false)

## ACF Field Groups

### Basic Information
- Subtitle
- Description
- Auto-generated Shortcode (read-only)

### Media & Gallery
- Image Gallery
- Video URL (YouTube/Vimeo support)

### Settings & Configuration
- Featured Cluster toggle
- Display Order
- Custom CSS

### Content Blocks (Repeater)
- Block Type selector (text, image, video, quote, divider)
- Block Title
- Block Content (WYSIWYG editor)
- Block-specific fields (image, video URL)

## Admin Features

### Settings Page
- Configure default display styles
- Enable/disable TinyMCE shortcode button
- Set gallery thumbnail sizes
- Auto-generate excerpts option

### Tools Page
- Bulk regenerate shortcodes
- Generate missing excerpts
- View shortcode examples

### Custom Columns
- Shortcode display in post list
- Featured status indicator
- Category and tag display

### Meta Boxes
- Shortcode display and copy functionality
- Live preview of cluster display

## Development

### File Structure
```
cluster-manager/
├── cluster-manager.php (Main plugin file)
├── includes/
│   ├── class-cluster-manager-cpt.php (CPT registration)
│   ├── class-cluster-manager-acf.php (ACF integration)
│   ├── class-cluster-manager-shortcode.php (Shortcode handling)
│   └── class-cluster-manager-admin.php (Admin interface)
├── assets/
│   ├── css/
│   │   └── cluster-manager.css (Frontend styles)
│   └── js/
│       ├── cluster-manager.js (Frontend scripts)
│       └── cluster-admin.js (Admin scripts)
└── README.md (This file)
```

### Hooks and Filters

#### Actions
- `cluster_manager_before_render` - Before cluster shortcode renders
- `cluster_manager_after_render` - After cluster shortcode renders
- `cluster_manager_save_settings` - When settings are saved

#### Filters
- `cluster_manager_shortcode_defaults` - Modify default shortcode parameters
- `cluster_manager_styles` - Add custom display styles
- `cluster_manager_content_blocks` - Modify available content block types

### Extending the Plugin

#### Custom Display Styles
```php
function custom_cluster_style($styles) {
    $styles['my_style'] = 'My Custom Style';
    return $styles;
}
add_filter('cluster_manager_styles', 'custom_cluster_style');
```

#### Custom Content Block Types
```php
function custom_content_blocks($blocks) {
    $blocks['custom_type'] = 'Custom Block Type';
    return $blocks;
}
add_filter('cluster_manager_content_blocks', 'custom_content_blocks');
```

## Requirements

- WordPress 5.0+
- PHP 7.4+
- Advanced Custom Fields (ACF) 5.0+

## Changelog

### Version 1.0.0
- Initial release
- Custom post type registration
- ACF field groups integration
- Shortcode generation system
- Admin interface
- Frontend display styles

## Support

For support and bug reports, please visit the plugin repository or contact the developer.

## License

This plugin is licensed under the GPL v2 or later.

---

**Note**: This plugin requires the Advanced Custom Fields plugin to be installed and activated for full functionality.
