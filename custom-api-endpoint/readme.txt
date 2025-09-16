=== MAD Custom API Endpoint ===
Contributors: jalalhussain
Tags: api, rest api, endpoint, custom, posts, pages, categories, tags, search
Requires at least: 5.0
Tested up to: 6.5
Stable tag: 1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Provides a custom REST API endpoint for posts, pages, search, categories, and tags with extensible features.

== Description ==
This plugin provides a custom REST API endpoint for WordPress, allowing you to fetch posts, pages, categories, tags, and perform searches with extended data. Useful for headless WordPress, JAMstack, and custom integrations.

== Installation ==
1. Upload the plugin files to the `/wp-content/plugins/custom-api-endpoint` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Use the custom API endpoints as documented in your application.

== Frequently Asked Questions ==
= What endpoints are available? =
The plugin provides endpoints for posts, pages, search, categories, and tags under `/wp-json/customapi/v1/`.

= Can I extend the API? =
Yes, you can add more endpoints or filters using WordPress hooks and the REST API.

== Screenshots ==
1. Example API response for posts endpoint.
2. Settings page for configuring the API.

== Changelog ==
= 1.0 =
* Initial release.

== Upgrade Notice ==
= 1.0 =
Initial release. 