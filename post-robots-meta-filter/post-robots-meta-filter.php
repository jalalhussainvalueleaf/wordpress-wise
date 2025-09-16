<?php
/**
 * Plugin Name: Post Robots Meta Filter
 * Description: Adds an admin filter dropdown to filter posts by noindex/nofollow robots meta (custom fields).
 * Version: 1.1
 * Author: Jalal Hussain
 */

if (!defined('ABSPATH')) exit;

// Add dropdown to filter posts
add_action('restrict_manage_posts', function () {
    global $typenow;
    if ($typenow == 'post') {
        $selected = $_GET['robots_meta_filter'] ?? '';
        ?>
        <select name="robots_meta_filter">
            <option value="">All Indexing</option>
            <option value="noindex" <?php selected($selected, 'noindex'); ?>>Noindex</option>
            <option value="nofollow" <?php selected($selected, 'nofollow'); ?>>Nofollow</option>
            <option value="noindex,nofollow" <?php selected($selected, 'noindex,nofollow'); ?>>Noindex, Nofollow</option>
        </select>
        <?php
    }
});

// Modify the query based on selected filter
add_filter('pre_get_posts', function ($query) {
    if (!is_admin() || !$query->is_main_query()) return;

    if (!empty($_GET['robots_meta_filter'])) {
        $filter = sanitize_text_field($_GET['robots_meta_filter']);
        $meta_query = ['relation' => 'AND'];

        if ($filter === 'noindex') {
            // _robots_index is '1' (noindex)
            $meta_query[] = [
                'key' => '_robots_index',
                'value' => '1',
                'compare' => '='
            ];
        } elseif ($filter === 'nofollow') {
            // _robots_follow is '1' (nofollow)
            $meta_query[] = [
                'key' => '_robots_follow',
                'value' => '1',
                'compare' => '='
            ];
        } elseif ($filter === 'noindex,nofollow') {
            // Both _robots_index and _robots_follow are '1'
            $meta_query[] = [
                'key' => '_robots_index',
                'value' => '1',
                'compare' => '='
            ];
            $meta_query[] = [
                'key' => '_robots_follow',
                'value' => '1',
                'compare' => '='
            ];
        }

        $query->set('meta_query', $meta_query);
    }
});

// Add a column to view the robot meta info
add_filter('manage_post_posts_columns', function ($columns) {
    $columns['robots_meta'] = 'Indexing';
    return $columns;
});

add_action('manage_post_posts_custom_column', function ($column, $post_id) {
    if ($column === 'robots_meta') {
        $index = get_post_meta($post_id, '_robots_index', true);
        $follow = get_post_meta($post_id, '_robots_follow', true);

        $robots = [];
        $robots[] = ($index === '1') ? 'noindex' : 'index';
        $robots[] = ($follow === '1') ? 'nofollow' : 'follow';

        echo esc_html(implode(', ', $robots));
    }
}, 10, 2);
