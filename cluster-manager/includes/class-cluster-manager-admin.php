<?php
/**
 * Cluster Manager Admin Class
 *
 * Handles the admin interface and functionality for clusters
 */

if (!defined('ABSPATH')) {
    exit;
}

class Cluster_Manager_Admin {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('admin_init', array($this, 'admin_init'));
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_post_meta'), 10, 2);
        add_filter('manage_cluster_posts_columns', array($this, 'manage_columns'));
        add_action('manage_cluster_posts_custom_column', array($this, 'custom_columns'), 10, 2);
        add_filter('manage_edit-cluster_sortable_columns', array($this, 'sortable_columns'));
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        // Add settings submenu
        add_submenu_page(
            'edit.php?post_type=cluster',
            __('Cluster Settings', 'cluster-manager'),
            __('Settings', 'cluster-manager'),
            'manage_options',
            'cluster-settings',
            array($this, 'settings_page')
        );

        // Add tools submenu
        add_submenu_page(
            'edit.php?post_type=cluster',
            __('Cluster Tools', 'cluster-manager'),
            __('Tools', 'cluster-manager'),
            'manage_options',
            'cluster-tools',
            array($this, 'tools_page')
        );

        // Add debug submenu for troubleshooting
        add_submenu_page(
            'edit.php?post_type=cluster',
            __('Cluster Debug', 'cluster-manager'),
            __('Debug', 'cluster-manager'),
            'manage_options',
            'cluster-debug',
            array($this, 'debug_page')
        );
    }

    /**
     * Debug page
     */
    public function debug_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Cluster Manager Debug Information', 'cluster-manager'); ?></h1>

            <?php
            $debug_info = array(
                'Plugin Status' => array(
                    'Plugin File' => file_exists(WP_PLUGIN_DIR . '/cluster-manager/cluster-manager.php') ? '✓ Found' : '✗ Missing',
                    'Plugin Active' => is_plugin_active('cluster-manager/cluster-manager.php') ? '✓ Active' : '✗ Inactive',
                    'Plugin Version' => get_plugin_data(WP_PLUGIN_DIR . '/cluster-manager/cluster-manager.php')['Version'] ?? 'Unknown',
                ),
                'Dependencies' => array(
                    'ACF Active' => function_exists('acf_add_local_field_group') ? '✓ Active' : '✗ Not Active',
                    'ACF Version' => defined('ACF_VERSION') ? ACF_VERSION : 'Unknown',
                ),
                'Custom Post Type' => array(
                    'CPT Registered' => post_type_exists('cluster') ? '✓ Registered' : '✗ Not Registered',
                    'Taxonomies' => array(
                        'Categories' => taxonomy_exists('cluster_category') ? '✓ Registered' : '✗ Not Registered',
                        'Tags' => taxonomy_exists('cluster_tag') ? '✓ Registered' : '✗ Not Registered',
                    ),
                ),
                'Database' => array(
                    'Field Groups Created' => get_option('cluster_manager_field_groups_created') ? '✓ Yes' : '✗ No',
                ),
                'File Structure' => array(
                    'Main File' => is_readable(WP_PLUGIN_DIR . '/cluster-manager/cluster-manager.php') ? '✓ Readable' : '✗ Not Readable',
                    'Includes Dir' => is_dir(WP_PLUGIN_DIR . '/cluster-manager/includes') ? '✓ Exists' : '✗ Missing',
                    'CPT Class' => file_exists(WP_PLUGIN_DIR . '/cluster-manager/includes/class-cluster-manager-cpt.php') ? '✓ Found' : '✗ Missing',
                    'ACF Class' => file_exists(WP_PLUGIN_DIR . '/cluster-manager/includes/class-cluster-manager-acf.php') ? '✓ Found' : '✗ Missing',
                    'Shortcode Class' => file_exists(WP_PLUGIN_DIR . '/cluster-manager/includes/class-cluster-manager-shortcode.php') ? '✓ Found' : '✗ Missing',
                    'Admin Class' => file_exists(WP_PLUGIN_DIR . '/cluster-manager/includes/class-cluster-manager-admin.php') ? '✓ Found' : '✗ Missing',
                ),
            );

            foreach ($debug_info as $section => $info) {
                echo '<div class="card" style="margin-bottom: 20px;">';
                echo '<h2>' . esc_html($section) . '</h2>';
                echo '<table class="widefat">';
                foreach ($info as $key => $value) {
                    if (is_array($value)) {
                        echo '<tr><td><strong>' . esc_html($key) . '</strong></td><td>';
                        foreach ($value as $sub_key => $sub_value) {
                            echo '<div>' . esc_html($sub_key) . ': ' . esc_html($sub_value) . '</div>';
                        }
                        echo '</td></tr>';
                    } else {
                        echo '<tr><td><strong>' . esc_html($key) . '</strong></td><td>' . esc_html($value) . '</td></tr>';
                    }
                }
                echo '</table>';
                echo '</div>';
            }
            ?>
        </div>
        <?php
    }

    /**
     * Settings page
     */
    public function settings_page() {
        if (isset($_POST['submit']) && wp_verify_nonce($_POST['cluster_settings_nonce'], 'cluster_settings')) {
            $this->save_settings();
        }

        $settings = $this->get_settings();
        ?>
        <div class="wrap">
            <h1><?php _e('Cluster Settings', 'cluster-manager'); ?></h1>

            <form method="post" action="">
                <?php wp_nonce_field('cluster_settings', 'cluster_settings_nonce'); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Default Display Style', 'cluster-manager'); ?></th>
                        <td>
                            <select name="default_style">
                                <option value="default" <?php selected($settings['default_style'], 'default'); ?>><?php _e('Default', 'cluster-manager'); ?></option>
                                <option value="compact" <?php selected($settings['default_style'], 'compact'); ?>><?php _e('Compact', 'cluster-manager'); ?></option>
                                <option value="featured" <?php selected($settings['default_style'], 'featured'); ?>><?php _e('Featured', 'cluster-manager'); ?></option>
                            </select>
                            <p class="description"><?php _e('Default style for cluster shortcodes', 'cluster-manager'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php _e('Enable Shortcode Button', 'cluster-manager'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="enable_shortcode_button" value="1" <?php checked($settings['enable_shortcode_button'], 1); ?> />
                                <?php _e('Add shortcode button to TinyMCE editor', 'cluster-manager'); ?>
                            </label>
                            <p class="description"><?php _e('Adds a button to insert cluster shortcodes in the editor', 'cluster-manager'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php _e('Auto-generate Excerpts', 'cluster-manager'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="auto_generate_excerpts" value="1" <?php checked($settings['auto_generate_excerpts'], 1); ?> />
                                <?php _e('Automatically generate excerpts from content', 'cluster-manager'); ?>
                            </label>
                            <p class="description"><?php _e('Generate excerpts automatically if none provided', 'cluster-manager'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php _e('Gallery Thumbnail Size', 'cluster-manager'); ?></th>
                        <td>
                            <select name="gallery_thumbnail_size">
                                <option value="thumbnail" <?php selected($settings['gallery_thumbnail_size'], 'thumbnail'); ?>>Thumbnail</option>
                                <option value="medium" <?php selected($settings['gallery_thumbnail_size'], 'medium'); ?>>Medium</option>
                                <option value="large" <?php selected($settings['gallery_thumbnail_size'], 'large'); ?>>Large</option>
                            </select>
                            <p class="description"><?php _e('Default thumbnail size for cluster galleries', 'cluster-manager'); ?></p>
                        </td>
                    </tr>
                </table>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Tools page
     */
    public function tools_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Cluster Tools', 'cluster-manager'); ?></h1>

            <div class="card">
                <h2><?php _e('Bulk Actions', 'cluster-manager'); ?></h2>
                <p><?php _e('Perform bulk operations on cluster posts', 'cluster-manager'); ?></p>

                <form method="post" action="">
                    <?php wp_nonce_field('cluster_tools', 'cluster_tools_nonce'); ?>

                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Regenerate Shortcodes', 'cluster-manager'); ?></th>
                            <td>
                                <button type="submit" name="regenerate_shortcodes" class="button" onclick="return confirm('<?php _e('This will regenerate shortcodes for all cluster posts. Continue?', 'cluster-manager'); ?>');">
                                    <?php _e('Regenerate All Shortcodes', 'cluster-manager'); ?>
                                </button>
                                <p class="description"><?php _e('Regenerate shortcodes for all existing cluster posts', 'cluster-manager'); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><?php _e('Generate Missing Excerpts', 'cluster-manager'); ?></th>
                            <td>
                                <button type="submit" name="generate_excerpts" class="button" onclick="return confirm('<?php _e('This will generate excerpts for posts without them. Continue?', 'cluster-manager'); ?>');">
                                    <?php _e('Generate Missing Excerpts', 'cluster-manager'); ?>
                                </button>
                                <p class="description"><?php _e('Generate excerpts for cluster posts that don\'t have them', 'cluster-manager'); ?></p>
                            </td>
                        </tr>
                    </table>
                </form>
            </div>

            <div class="card">
                <h2><?php _e('Shortcode Examples', 'cluster-manager'); ?></h2>
                <p><?php _e('Here are some examples of shortcodes you can use:', 'cluster-manager'); ?></p>

                <h3><?php _e('Single Cluster', 'cluster-manager'); ?></h3>
                <code>[cluster id="123"]</code>

                <h3><?php _e('Cluster List', 'cluster-manager'); ?></h3>
                <code>[cluster_list number="5" style="grid"]</code>

                <h3><?php _e('Featured Clusters', 'cluster-manager'); ?></h3>
                <code>[cluster_featured number="3"]</code>
            </div>
        </div>
        <?php

        // Handle form submissions
        if (isset($_POST['regenerate_shortcodes']) && wp_verify_nonce($_POST['cluster_tools_nonce'], 'cluster_tools')) {
            $this->regenerate_all_shortcodes();
        }

        if (isset($_POST['generate_excerpts']) && wp_verify_nonce($_POST['cluster_tools_nonce'], 'cluster_tools')) {
            $this->generate_missing_excerpts();
        }
    }

    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        global $post_type;

        if (($hook === 'post.php' || $hook === 'post-new.php') && $post_type === 'cluster') {
            wp_enqueue_media();
            wp_enqueue_script(
                'cluster-admin-scripts',
                plugin_dir_url(dirname(__FILE__)) . 'assets/js/cluster-admin.js',
                array('jquery'),
                '1.0.0',
                true
            );
        }
    }

    /**
     * Admin initialization
     */
    public function admin_init() {
        // Register settings
        register_setting('cluster_manager_settings', 'cluster_manager_settings');

        // Handle tool actions
        if (isset($_POST['regenerate_shortcodes']) && wp_verify_nonce($_POST['cluster_tools_nonce'], 'cluster_tools')) {
            $this->regenerate_all_shortcodes();
        }
    }

    /**
     * Add meta boxes
     */
    public function add_meta_boxes() {
        add_meta_box(
            'cluster-shortcode-box',
            __('Cluster Shortcode', 'cluster-manager'),
            array($this, 'shortcode_meta_box'),
            'cluster',
            'side',
            'high'
        );

        add_meta_box(
            'cluster-preview-box',
            __('Cluster Preview', 'cluster-manager'),
            array($this, 'preview_meta_box'),
            'cluster',
            'normal',
            'high'
        );
    }

    /**
     * Shortcode meta box
     */
    public function shortcode_meta_box($post) {
        $shortcode = get_post_meta($post->ID, '_cluster_shortcode', true);
        $simple_shortcode = get_post_meta($post->ID, '_cluster_simple_shortcode', true);
        ?>
        <div class="cluster-shortcode-box">
            <p><strong><?php _e('Shortcode:', 'cluster-manager'); ?></strong></p>
            <input type="text" readonly value="<?php echo esc_attr($shortcode ?: '[cluster id="' . $post->ID . '"]'); ?>" class="widefat" onclick="this.select();">

            <p><strong><?php _e('Simple Shortcode:', 'cluster-manager'); ?></strong></p>
            <input type="text" readonly value="<?php echo esc_attr($simple_shortcode ?: '[cluster-' . $post->ID . ']'); ?>" class="widefat" onclick="this.select();">

            <p class="description">
                <?php _e('Copy and paste these shortcodes to display this cluster anywhere on your site.', 'cluster-manager'); ?>
            </p>
        </div>
        <?php
    }

    /**
     * Preview meta box
     */
    public function preview_meta_box($post) {
        ?>
        <div class="cluster-preview-box">
            <p><strong><?php _e('Preview:', 'cluster-manager'); ?></strong></p>
            <div class="cluster-preview-content">
                <?php echo do_shortcode('[cluster id="' . $post->ID . '" show_content="false"]'); ?>
            </div>
        </div>
        <style>
        .cluster-preview-content {
            background: #f8f9fa;
            border: 1px solid #e1e1e1;
            border-radius: 4px;
            padding: 1rem;
            margin-top: 1rem;
        }
        </style>
        <?php
    }

    /**
     * Save post meta
     */
    public function save_post_meta($post_id, $post) {
        if ('cluster' !== $post->post_type) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Generate shortcode if it doesn't exist
        if (!get_post_meta($post_id, '_cluster_shortcode', true)) {
            $this->generate_shortcode($post_id, $post);
        }
    }

    /**
     * Manage columns
     */
    public function manage_columns($columns) {
        $new_columns = array();

        $new_columns['cb'] = $columns['cb'];
        $new_columns['title'] = $columns['title'];
        $new_columns['shortcode'] = __('Shortcode', 'cluster-manager');
        $new_columns['featured'] = __('Featured', 'cluster-manager');
        $new_columns['categories'] = __('Categories', 'cluster-manager');
        $new_columns['tags'] = __('Tags', 'cluster-manager');
        $new_columns['date'] = $columns['date'];

        return $new_columns;
    }

    /**
     * Custom columns
     */
    public function custom_columns($column, $post_id) {
        switch ($column) {
            case 'shortcode':
                $shortcode = get_post_meta($post_id, '_cluster_shortcode', true);
                echo '<code>' . esc_html($shortcode ?: '[cluster id="' . $post_id . '"]') . '</code>';
                break;

            case 'featured':
                $featured = get_field('cluster_featured', $post_id);
                echo $featured ? '<span class="dashicons dashicons-star-filled" style="color: #f39c12;"></span>' : '—';
                break;

            case 'categories':
                $categories = get_the_terms($post_id, 'cluster_category');
                if ($categories && !is_wp_error($categories)) {
                    $category_links = array();
                    foreach ($categories as $category) {
                        $category_links[] = '<a href="' . admin_url('edit.php?cluster_category=' . $category->slug . '&post_type=cluster') . '">' . esc_html($category->name) . '</a>';
                    }
                    echo implode(', ', $category_links);
                } else {
                    echo '—';
                }
                break;

            case 'tags':
                $tags = get_the_terms($post_id, 'cluster_tag');
                if ($tags && !is_wp_error($tags)) {
                    $tag_links = array();
                    foreach ($tags as $tag) {
                        $tag_links[] = '<a href="' . admin_url('edit.php?cluster_tag=' . $tag->slug . '&post_type=cluster') . '">' . esc_html($tag->name) . '</a>';
                    }
                    echo implode(', ', $tag_links);
                } else {
                    echo '—';
                }
                break;
        }
    }

    /**
     * Sortable columns
     */
    public function sortable_columns($columns) {
        $columns['featured'] = 'featured';
        $columns['categories'] = 'categories';
        return $columns;
    }

    /**
     * Get settings
     */
    private function get_settings() {
        $defaults = array(
            'default_style' => 'default',
            'enable_shortcode_button' => 1,
            'auto_generate_excerpts' => 0,
            'gallery_thumbnail_size' => 'medium',
        );

        $settings = get_option('cluster_manager_settings', array());
        return wp_parse_args($settings, $defaults);
    }

    /**
     * Save settings
     */
    private function save_settings() {
        $settings = array(
            'default_style' => sanitize_text_field($_POST['default_style']),
            'enable_shortcode_button' => isset($_POST['enable_shortcode_button']) ? 1 : 0,
            'auto_generate_excerpts' => isset($_POST['auto_generate_excerpts']) ? 1 : 0,
            'gallery_thumbnail_size' => sanitize_text_field($_POST['gallery_thumbnail_size']),
        );

        update_option('cluster_manager_settings', $settings);
        echo '<div class="notice notice-success"><p>' . __('Settings saved successfully!', 'cluster-manager') . '</p></div>';
    }

    /**
     * Generate shortcode
     */
    private function generate_shortcode($post_id, $post) {
        $shortcode = '[cluster id="' . $post_id . '" title="' . esc_attr($post->post_title) . '"]';
        update_post_meta($post_id, '_cluster_shortcode', $shortcode);

        $simple_shortcode = '[cluster-' . $post_id . ']';
        update_post_meta($post_id, '_cluster_simple_shortcode', $simple_shortcode);
    }

    /**
     * Regenerate all shortcodes
     */
    private function regenerate_all_shortcodes() {
        $clusters = get_posts(array(
            'post_type' => 'cluster',
            'numberposts' => -1,
            'post_status' => 'any',
        ));

        $count = 0;
        foreach ($clusters as $cluster) {
            $this->generate_shortcode($cluster->ID, $cluster);
            $count++;
        }

        echo '<div class="notice notice-success"><p>' . sprintf(__('Shortcodes regenerated for %d clusters.', 'cluster-manager'), $count) . '</p></div>';
    }

    /**
     * Generate missing excerpts
     */
    private function generate_missing_excerpts() {
        $clusters = get_posts(array(
            'post_type' => 'cluster',
            'numberposts' => -1,
            'post_status' => 'publish',
        ));

        $count = 0;
        foreach ($clusters as $cluster) {
            if (empty($cluster->post_excerpt)) {
                $excerpt = wp_trim_words(strip_shortcodes($cluster->post_content), 30);
                wp_update_post(array(
                    'ID' => $cluster->ID,
                    'post_excerpt' => $excerpt,
                ));
                $count++;
            }
        }

        echo '<div class="notice notice-success"><p>' . sprintf(__('Excerpts generated for %d clusters.', 'cluster-manager'), $count) . '</p></div>';
    }
}
