# JSON Post Importer for WordPress

A powerful WordPress plugin that allows you to import posts, pages, and custom post types from JSON data via file upload or API.

## Features

- Import posts from JSON files
- Support for all post types (posts, pages, custom post types)
- Handle featured images and media attachments
- Map JSON fields to WordPress post fields
- Support for custom fields and taxonomies
- Duplicate detection and handling
- Comprehensive error handling and logging
- REST API endpoint for programmatic imports
- Support for ACF (Advanced Custom Fields)
- Yoast SEO integration

## Installation

1. Upload the `json-post-importer` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to 'JSON Importer' in the WordPress admin to start importing

## Usage

### Importing from the Admin Interface

1. Navigate to 'JSON Importer' in your WordPress admin
2. Upload a JSON file or paste JSON data
3. Configure import options (post type, status, etc.)
4. Map JSON fields to WordPress fields
5. Preview the data
6. Run the import

### JSON Format

The plugin expects an array of post objects. Here's an example:

```json
[
  {
    "title": "Sample Post",
    "content": "This is the post content.",
    "excerpt": "This is a short excerpt.",
    "status": "publish",
    "date": "2023-01-01 12:00:00",
    "slug": "sample-post",
    "featured_image": "https://example.com/image.jpg",
    "taxonomies": {
      "category": ["Uncategorized"],
      "post_tag": ["sample", "import"]
    },
    "meta": {
      "_custom_field": "Custom value",
      "_import_id": "unique-identifier-123"
    }
  }
]
```

### Using the REST API

The plugin provides a REST API endpoint for programmatic imports:

```
POST /wp-json/json-post-importer/v1/import
```

**Headers:**
```
Content-Type: application/json
Authorization: Bearer YOUR_API_TOKEN
```

**Example Request:**
```http
POST /wp-json/json-post-importer/v1/import
Content-Type: application/json
Authorization: Bearer your_api_token

{
  "title": "API Imported Post",
  "content": "This post was imported via the API.",
  "status": "publish"
}
```

## Hooks and Filters

The plugin provides several hooks for customization:

### Actions
- `jpi_before_post_import` - Fires before a post is imported
- `jpi_after_post_import` - Fires after a post is imported
- `jpi_import_complete` - Fires when an import is complete

### Filters
- `jpi_process_content` - Filter the post content before it's saved
- `jpi_field_mappings` - Filter the default field mappings
- `jpi_import_options` - Filter the default import options

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher

## License

GPL v2 or later

## Changelog

### 1.0.0
* Initial release

## Support

For support, please open an issue on the [GitHub repository](https://github.com/yourusername/json-post-importer).
