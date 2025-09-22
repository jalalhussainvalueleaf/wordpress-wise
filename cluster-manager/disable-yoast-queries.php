<?php
/**
 * Alternative Solution: Disable Yoast Primary Term functionality for cluster_category
 *
 * Add this to your theme's functions.php
 */

// Disable primary term queries for cluster_category taxonomy
add_filter('get_terms', 'filter_problematic_yoast_queries', 10, 4);

function filter_problematic_yoast_queries($terms, $taxonomies, $args, $term_query) {
    // Prevent Yoast from trying to access primary_term table for cluster_category
    if (is_array($taxonomies) && in_array('cluster_category', $taxonomies)) {
        // Remove any Yoast-specific query args that might cause database errors
        if (isset($args['meta_query'])) {
            foreach ($args['meta_query'] as $key => $meta_query) {
                if (isset($meta_query['key']) && strpos($meta_query['key'], 'wpseo') !== false) {
                    unset($args['meta_query'][$key]);
                }
            }
        }
    }
    return $terms;
}

// Alternative: Force disable Yoast primary term for cluster_category
add_action('init', 'disable_yoast_primary_term_for_clusters');

function disable_yoast_primary_term_for_clusters() {
    if (function_exists('wpseo_auto_load')) {
        // Remove cluster_category from Yoast's primary term taxonomies
        add_filter('wpseo_primary_term_taxonomies', function($taxonomies, $post_id) {
            if (get_post_type($post_id) === 'cluster') {
                $taxonomies = array_diff($taxonomies, ['cluster_category']);
            }
            return $taxonomies;
        }, 10, 2);
    }
}
?>
