<?php
/**
 * Cluster Manager CPT Class
 *
 * Handles the custom post type registration and management
 */

if (!defined('ABSPATH')) {
    exit;
}

class Cluster_Manager_CPT {

    /**
     * Post type name
     */
    const POST_TYPE = 'cluster';

    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', array($this, 'register_post_type'));
        add_action('init', array($this, 'register_taxonomies'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('save_post', array($this, 'save_post'), 10, 2);
        add_filter('post_updated_messages', array($this, 'post_updated_messages'));
    }

    /**
     * Register the cluster post type
     */
    public function register_post_type() {
        $labels = array(
            'name'                  => _x('Clusters', 'Post type general name', 'cluster-manager'),
            'singular_name'         => _x('Cluster', 'Post type singular name', 'cluster-manager'),
            'menu_name'             => _x('Clusters', 'Admin Menu text', 'cluster-manager'),
            'name_admin_bar'        => _x('Cluster', 'Add New on Toolbar', 'cluster-manager'),
            'add_new'               => __('Add New', 'cluster-manager'),
            'add_new_item'          => __('Add New Cluster', 'cluster-manager'),
            'new_item'              => __('New Cluster', 'cluster-manager'),
            'edit_item'             => __('Edit Cluster', 'cluster-manager'),
            'view_item'             => __('View Cluster', 'cluster-manager'),
            'all_items'             => __('All Clusters', 'cluster-manager'),
            'search_items'          => __('Search Clusters', 'cluster-manager'),
            'parent_item_colon'     => __('Parent Clusters:', 'cluster-manager'),
            'not_found'             => __('No clusters found.', 'cluster-manager'),
            'not_found_in_trash'    => __('No clusters found in Trash.', 'cluster-manager'),
            'featured_image'        => _x('Cluster Featured Image', 'Overrides the "Featured Image" phrase for this post type. Added in 4.3', 'cluster-manager'),
            'set_featured_image'    => _x('Set featured image', 'Overrides the "Set featured image" phrase for this post type. Added in 4.3', 'cluster-manager'),
            'remove_featured_image' => _x('Remove featured image', 'Overrides the "Remove featured image" phrase for this post type. Added in 4.3', 'cluster-manager'),
            'use_featured_image'    => _x('Use as featured image', 'Overrides the "Use as featured image" phrase for this post type. Added in 4.3', 'cluster-manager'),
            'archives'              => _x('Cluster archives', 'The post type archive label used in nav menus. Default "Post Archives". Added in 4.4', 'cluster-manager'),
            'insert_into_item'      => _x('Insert into cluster', 'Overrides the "Insert into post"/"Insert into page" phrase (used when inserting media into a post). Added in 4.4', 'cluster-manager'),
            'uploaded_to_this_item' => _x('Uploaded to this cluster', 'Overrides the "Uploaded to this post"/"Uploaded to this page" phrase (used when viewing media attached to a post). Added in 4.4', 'cluster-manager'),
            'filter_items_list'     => _x('Filter clusters list', 'Screen reader text for the filter links heading on the post type listing screen. Default "Filter posts list"/"Filter pages list". Added in 4.4', 'cluster-manager'),
            'items_list_navigation' => _x('Clusters list navigation', 'Screen reader text for the pagination heading on the post type listing screen. Default "Posts list navigation"/"Pages list navigation". Added in 4.4', 'cluster-manager'),
            'items_list'            => _x('Clusters list', 'Screen reader text for the items list heading on the post type listing screen. Default "Posts list"/"Pages list". Added in 4.4', 'cluster-manager'),
        );

        $args = array(
            'labels'             => $labels,
            'description'        => __('A custom post type for managing clusters', 'cluster-manager'),
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => array('slug' => 'cluster'),
            'capability_type'    => 'post',
            'has_archive'        => true,
            'hierarchical'       => false,
            'menu_position'      => null,
            'menu_icon'          => 'dashicons-groups',
            'supports'           => array('title', 'editor', 'author', 'thumbnail', 'excerpt', 'custom-fields', 'page-attributes', 'post-formats'),
            'show_in_rest'       => true,
        );

        register_post_type(self::POST_TYPE, $args);
    }

    /**
     * Register taxonomies for clusters
     */
    public function register_taxonomies() {
        // Cluster Categories
        $cat_labels = array(
            'name'              => _x('Cluster Categories', 'taxonomy general name', 'cluster-manager'),
            'singular_name'     => _x('Cluster Category', 'taxonomy singular name', 'cluster-manager'),
            'search_items'      => __('Search Cluster Categories', 'cluster-manager'),
            'all_items'         => __('All Cluster Categories', 'cluster-manager'),
            'parent_item'       => __('Parent Cluster Category', 'cluster-manager'),
            'parent_item_colon' => __('Parent Cluster Category:', 'cluster-manager'),
            'edit_item'         => __('Edit Cluster Category', 'cluster-manager'),
            'update_item'       => __('Update Cluster Category', 'cluster-manager'),
            'add_new_item'      => __('Add New Cluster Category', 'cluster-manager'),
            'new_item_name'     => __('New Cluster Category Name', 'cluster-manager'),
            'menu_name'         => __('Categories', 'cluster-manager'),
        );

        register_taxonomy('cluster_category', array(self::POST_TYPE), array(
            'hierarchical'      => true,
            'labels'            => $cat_labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => array('slug' => 'cluster-category'),
            'show_in_rest'      => true,
        ));

        // Cluster Tags
        $tag_labels = array(
            'name'              => _x('Cluster Tags', 'taxonomy general name', 'cluster-manager'),
            'singular_name'     => _x('Cluster Tag', 'taxonomy singular name', 'cluster-manager'),
            'search_items'      => __('Search Cluster Tags', 'cluster-manager'),
            'popular_items'     => __('Popular Cluster Tags', 'cluster-manager'),
            'all_items'         => __('All Cluster Tags', 'cluster-manager'),
            'parent_item'       => null,
            'parent_item_colon' => null,
            'edit_item'         => __('Edit Cluster Tag', 'cluster-manager'),
            'update_item'       => __('Update Cluster Tag', 'cluster-manager'),
            'add_new_item'      => __('Add New Cluster Tag', 'cluster-manager'),
            'new_item_name'     => __('New Cluster Tag Name', 'cluster-manager'),
            'separate_items_with_commas' => __('Separate cluster tags with commas', 'cluster-manager'),
            'add_or_remove_items' => __('Add or remove cluster tags', 'cluster-manager'),
            'choose_from_most_used' => __('Choose from the most used cluster tags', 'cluster-manager'),
            'not_found'         => __('No cluster tags found.', 'cluster-manager'),
            'menu_name'         => __('Tags', 'cluster-manager'),
        );

        register_taxonomy('cluster_tag', array(self::POST_TYPE), array(
            'hierarchical'      => false,
            'labels'            => $tag_labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => array('slug' => 'cluster-tag'),
            'show_in_rest'      => true,
        ));
    }

    /**
     * Add admin menu for clusters
     */
    public function add_admin_menu() {
        // Settings page is now handled by the Admin class
        // This method can be used for CPT-specific admin menu items in the future
    }

    /**
     * Save post handler
     */
    public function save_post($post_id, $post) {
        if (self::POST_TYPE !== $post->post_type) {
            return;
        }

        // Generate shortcode for the cluster
        $this->generate_shortcode($post_id, $post);
    }

    /**
     * Generate shortcode for cluster post
     */
    private function generate_shortcode($post_id, $post) {
        $shortcode = '[cluster id="' . $post_id . '" title="' . esc_attr($post->post_title) . '"]';

        // Store the shortcode in post meta
        update_post_meta($post_id, '_cluster_shortcode', $shortcode);

        // Also store a simplified shortcode
        $simple_shortcode = '[cluster-' . $post_id . ']';
        update_post_meta($post_id, '_cluster_simple_shortcode', $simple_shortcode);
    }

    /**
     * Custom post updated messages
     */
    public function post_updated_messages($messages) {
        $post = get_post();
        $post_type = get_post_type($post);
        $post_type_object = get_post_type_object($post_type);

        if (self::POST_TYPE === $post_type) {
            $messages[$post_type] = array(
                0  => '', // Unused. Messages start at index 1.
                1  => __('Cluster updated.', 'cluster-manager'),
                2  => __('Custom field updated.', 'cluster-manager'),
                3  => __('Custom field deleted.', 'cluster-manager'),
                4  => __('Cluster updated.', 'cluster-manager'),
                5  => isset($_GET['revision']) ? sprintf(__('Cluster restored to revision from %s', 'cluster-manager'), wp_post_revision_title((int) $_GET['revision'], false)) : false,
                6  => __('Cluster published.', 'cluster-manager'),
                7  => __('Cluster saved.', 'cluster-manager'),
                8  => __('Cluster submitted.', 'cluster-manager'),
                9  => sprintf(
                    __('Cluster scheduled for: <strong>%1$s</strong>.', 'cluster-manager'),
                    date_i18n(__('M j, Y @ G:i', 'cluster-manager'), strtotime($post->post_date))
                ),
                10 => __('Cluster draft updated.', 'cluster-manager'),
            );

            if (isset($messages[$post_type][6])) {
                $permalink = get_permalink($post->ID);
                $view_link = sprintf(' <a href="%s">%s</a>', esc_url($permalink), __('View cluster', 'cluster-manager'));
                $messages[$post_type][6] .= $view_link;
            }
        }

        return $messages;
    }
}
