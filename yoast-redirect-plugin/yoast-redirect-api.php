<?php
/**
 * Plugin Name: Yoast Redirect API
 * Plugin URI: https://example.com/yoast-redirect-api
 * Description: REST API endpoints to check Yoast SEO Premium redirects by slug
 * Version: 1.0.0
 * Author: Your Name
 * License: GPL v2 or later
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Yoast Redirect REST API
 * Provides endpoints to check redirects by slug
 */

class Yoast_Redirect_API_Plugin {

    /**
     * Initialize the plugin
     */
    public function __construct() {
        add_action('rest_api_init', array($this, 'register_rest_routes'));
    }

    /**
     * Register REST API routes
     */
    public function register_rest_routes() {
        // Single slug check endpoint
        register_rest_route('yoast-redirects/v1', '/check', [
            'methods'  => 'GET',
            'callback' => array($this, 'check_redirect'),
            'args'     => [
                'slug' => [
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
            'permission_callback' => '__return_true', // Public access
        ]);

        // Bulk slug check endpoint
        register_rest_route('yoast-redirects/v1', '/bulk-check', [
            'methods'  => 'POST',
            'callback' => array($this, 'bulk_check_redirects'),
            'args'     => [
                'slugs' => [
                    'required' => true,
                    'validate_callback' => function($value) {
                        return is_array($value) && !empty($value);
                    },
                ],
            ],
            'permission_callback' => '__return_true',
        ]);

        // Get all redirects endpoint
        register_rest_route('yoast-redirects/v1', '/all', [
            'methods' => 'GET',
            'callback' => array($this, 'get_all_redirects'),
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * Check if a specific slug has a redirect
     */
    public function check_redirect($request) {
        $slug = $request['slug'];

        // Normalize the slug
        $slug = '/' . trim($slug, '/');

        // Yoast Premium stores redirects in WordPress options
        $redirect_options = [
            'wpseo-premium-redirects-base',     // Main redirects (since v3.1)
            'wpseo-premium-redirects-export-plain', // Plain text redirects
            'wpseo-premium-redirects-export-regex', // Regex redirects
            'wpseo-premium-redirects',          // Legacy plain redirects (pre v3.1)
            'wpseo-premium-redirects-regex'     // Legacy regex redirects (pre v3.1)
        ];

        foreach ($redirect_options as $option_name) {
            $redirects = get_option($option_name, []);

            if (!is_array($redirects)) {
                continue;
            }

            foreach ($redirects as $redirect) {
                // Handle both array and object formats
                if (is_object($redirect)) {
                    $redirect = (array) $redirect;
                }

                $origin = $redirect['origin'] ?? '';

                // Normalize origin for comparison
                $origin = '/' . trim($origin, '/');

                // Check for exact match
                if ($origin === $slug) {
                    $target = $redirect['url'] ?? $redirect['target'] ?? '';
                    $type = $redirect['type'] ?? 301;

                    return [
                        'found' => true,
                        'redirect_to' => $target,
                        'status' => $type,
                        'origin' => $origin,
                        'source' => $option_name,
                    ];
                }
            }
        }

        // No redirect found
        return [
            'found' => false,
            'redirect_to' => null,
            'status' => 200,
            'message' => 'No redirect found for slug: ' . $slug,
        ];
    }

    /**
     * Bulk check multiple slugs
     */
    public function bulk_check_redirects($request) {
        $slugs = $request['slugs'];
        $results = [];

        foreach ($slugs as $slug) {
            $results[] = $this->check_redirect(['slug' => $slug]);
        }

        return [
            'total_checked' => count($slugs),
            'results' => $results,
        ];
    }

    /**
     * Get all redirects
     */
    public function get_all_redirects() {
        $all_redirects = [];

        $redirect_options = [
            'wpseo-premium-redirects-base',
            'wpseo-premium-redirects-export-plain',
            'wpseo-premium-redirects-export-regex',
            'wpseo-premium-redirects',
            'wpseo-premium-redirects-regex'
        ];

        foreach ($redirect_options as $option_name) {
            $redirects = get_option($option_name, []);

            if (!is_array($redirects)) {
                continue;
            }

            foreach ($redirects as $redirect) {
                if (is_object($redirect)) {
                    $redirect = (array) $redirect;
                }

                $formatted = [
                    'origin' => $redirect['origin'] ?? '',
                    'target' => $redirect['url'] ?? $redirect['target'] ?? '',
                    'type' => $redirect['type'] ?? 301,
                    'format' => $redirect['format'] ?? 'plain',
                    'source_option' => $option_name,
                ];

                if (!empty($formatted['origin'])) {
                    $all_redirects[] = $formatted;
                }
            }
        }

        return [
            'total' => count($all_redirects),
            'redirects' => $all_redirects,
        ];
    }
}

// Initialize the plugin
new Yoast_Redirect_API_Plugin();
