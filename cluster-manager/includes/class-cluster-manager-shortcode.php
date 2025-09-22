<?php
/**
 * Cluster Manager Shortcode Class
 *
 * Handles shortcode generation and rendering for clusters
 */

if (!defined('ABSPATH')) {
    exit;
}

class Cluster_Manager_Shortcode {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', array($this, 'register_shortcodes'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    /**
     * Register shortcodes
     */
    public function register_shortcodes() {
        add_shortcode('cluster', array($this, 'render_cluster_shortcode'));
        add_shortcode('cluster_list', array($this, 'render_cluster_list_shortcode'));
        add_shortcode('cluster_featured', array($this, 'render_featured_clusters_shortcode'));
    }

    /**
     * Render single cluster shortcode
     */
    public function render_cluster_shortcode($atts) {
        $atts = shortcode_atts(array(
            'id' => 0,
            'title' => '',
            'style' => 'default',
            'show_title' => 'true',
            'show_content' => 'true',
            'show_meta' => 'true',
            'show_gallery' => 'true',
        ), $atts, 'cluster');

        if (!$atts['id'] && !$atts['title']) {
            return '';
        }

        // Get cluster post
        $query_args = array(
            'post_type' => 'cluster',
            'post_status' => 'publish',
            'posts_per_page' => 1,
        );

        if ($atts['id']) {
            $query_args['p'] = intval($atts['id']);
        } else {
            $query_args['title'] = sanitize_text_field($atts['title']);
        }

        $clusters = get_posts($query_args);

        if (empty($clusters)) {
            return '';
        }

        $cluster = $clusters[0];

        // Get ACF fields
        $subtitle = get_field('cluster_subtitle', $cluster->ID);
        $description = get_field('cluster_description', $cluster->ID);
        $gallery = get_field('cluster_gallery', $cluster->ID);
        $video_url = get_field('cluster_video_url', $cluster->ID);
        $content_blocks = get_field('cluster_content_blocks', $cluster->ID);
        $custom_css = get_field('cluster_custom_css', $cluster->ID);

        ob_start();

        // Add custom CSS if provided
        if ($custom_css) {
            echo '<style>' . wp_strip_all_tags($custom_css) . '</style>';
        }

        ?>
        <div class="cluster-manager cluster-style-<?php echo esc_attr($atts['style']); ?>" data-cluster-id="<?php echo esc_attr($cluster->ID); ?>">

            <?php if ($atts['show_title'] === 'true' && $cluster->post_title): ?>
            <header class="cluster-header">
                <h2 class="cluster-title"><?php echo esc_html($cluster->post_title); ?></h2>
                <?php if ($subtitle): ?>
                <p class="cluster-subtitle"><?php echo esc_html($subtitle); ?></p>
                <?php endif; ?>
            </header>
            <?php endif; ?>

            <?php if ($atts['show_content'] === 'true'): ?>
            <div class="cluster-content">

                <?php if ($description): ?>
                <div class="cluster-description">
                    <?php echo wpautop(esc_html($description)); ?>
                </div>
                <?php endif; ?>

                <?php if ($gallery && $atts['show_gallery'] === 'true'): ?>
                <div class="cluster-gallery">
                    <?php foreach ($gallery as $image): ?>
                    <div class="cluster-gallery-item">
                        <img src="<?php echo esc_url($image['url']); ?>" alt="<?php echo esc_attr($image['alt'] ?: $cluster->post_title); ?>" />
                        <?php if ($image['caption']): ?>
                        <p class="cluster-gallery-caption"><?php echo esc_html($image['caption']); ?></p>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <?php if ($video_url): ?>
                <div class="cluster-video">
                    <?php echo $this->get_video_embed($video_url); ?>
                </div>
                <?php endif; ?>

                <?php if ($content_blocks): ?>
                <div class="cluster-content-blocks">
                    <?php foreach ($content_blocks as $block): ?>
                    <div class="content-block content-block-<?php echo esc_attr($block['block_type']); ?>">
                        <?php if ($block['block_title']): ?>
                        <h3 class="block-title"><?php echo esc_html($block['block_title']); ?></h3>
                        <?php endif; ?>

                        <?php if ($block['block_type'] === 'text' || $block['block_type'] === 'quote'): ?>
                        <div class="block-content">
                            <?php echo wpautop($block['block_content']); ?>
                        </div>
                        <?php elseif ($block['block_type'] === 'image' && $block['block_image']): ?>
                        <div class="block-image">
                            <img src="<?php echo esc_url($block['block_image']['url']); ?>" alt="<?php echo esc_attr($block['block_image']['alt'] ?: $block['block_title']); ?>" />
                        </div>
                        <?php elseif ($block['block_type'] === 'video' && $block['block_video']): ?>
                        <div class="block-video">
                            <?php echo $this->get_video_embed($block['block_video']); ?>
                        </div>
                        <?php elseif ($block['block_type'] === 'divider'): ?>
                        <hr class="block-divider" />
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <?php if ($cluster->post_content): ?>
                <div class="cluster-excerpt">
                    <?php echo wpautop($cluster->post_excerpt ?: wp_trim_words($cluster->post_content, 30)); ?>
                </div>
                <?php endif; ?>

            </div>
            <?php endif; ?>

            <?php if ($atts['show_meta'] === 'true'): ?>
            <footer class="cluster-meta">
                <div class="cluster-date">
                    <span class="meta-label"><?php _e('Published:', 'cluster-manager'); ?></span>
                    <time datetime="<?php echo esc_attr(get_the_date('c', $cluster)); ?>"><?php echo esc_html(get_the_date('', $cluster)); ?></time>
                </div>
                <?php
                $categories = get_the_terms($cluster->ID, 'cluster_category');
                if ($categories && !is_wp_error($categories)):
                ?>
                <div class="cluster-categories">
                    <span class="meta-label"><?php _e('Categories:', 'cluster-manager'); ?></span>
                    <?php echo get_the_term_list($cluster->ID, 'cluster_category', '', ', '); ?>
                </div>
                <?php endif; ?>

                <?php
                $tags = get_the_terms($cluster->ID, 'cluster_tag');
                if ($tags && !is_wp_error($tags)):
                ?>
                <div class="cluster-tags">
                    <span class="meta-label"><?php _e('Tags:', 'cluster-manager'); ?></span>
                    <?php echo get_the_term_list($cluster->ID, 'cluster_tag', '', ', '); ?>
                </div>
                <?php endif; ?>
            </footer>
            <?php endif; ?>

        </div>
        <?php

        return ob_get_clean();
    }

    /**
     * Render cluster list shortcode
     */
    public function render_cluster_list_shortcode($atts) {
        $atts = shortcode_atts(array(
            'number' => 10,
            'category' => '',
            'tag' => '',
            'featured' => 'false',
            'orderby' => 'date',
            'order' => 'DESC',
            'style' => 'grid',
            'show_excerpt' => 'true',
            'show_thumbnail' => 'true',
            'show_meta' => 'true',
        ), $atts, 'cluster_list');

        $query_args = array(
            'post_type' => 'cluster',
            'post_status' => 'publish',
            'posts_per_page' => intval($atts['number']),
            'orderby' => sanitize_text_field($atts['orderby']),
            'order' => sanitize_text_field($atts['order']),
            'meta_query' => array(),
        );

        if ($atts['category']) {
            $query_args['tax_query'][] = array(
                'taxonomy' => 'cluster_category',
                'field' => 'slug',
                'terms' => sanitize_text_field($atts['category']),
            );
        }

        if ($atts['tag']) {
            $query_args['tax_query'][] = array(
                'taxonomy' => 'cluster_tag',
                'field' => 'slug',
                'terms' => sanitize_text_field($atts['tag']),
            );
        }

        if ($atts['featured'] === 'true') {
            $query_args['meta_query'][] = array(
                'key' => 'cluster_featured',
                'value' => '1',
                'compare' => '=',
            );
        }

        $clusters = get_posts($query_args);

        if (empty($clusters)) {
            return '<p>' . __('No clusters found.', 'cluster-manager') . '</p>';
        }

        ob_start();
        ?>
        <div class="cluster-manager-list cluster-list-style-<?php echo esc_attr($atts['style']); ?>">

            <?php foreach ($clusters as $cluster): ?>
            <article class="cluster-item" data-cluster-id="<?php echo esc_attr($cluster->ID); ?>">
                <div class="cluster-item-inner">

                    <?php if ($atts['show_thumbnail'] === 'true' && has_post_thumbnail($cluster->ID)): ?>
                    <div class="cluster-thumbnail">
                        <a href="<?php echo get_permalink($cluster->ID); ?>">
                            <?php echo get_the_post_thumbnail($cluster->ID, 'medium'); ?>
                        </a>
                    </div>
                    <?php endif; ?>

                    <div class="cluster-content">
                        <h3 class="cluster-title">
                            <a href="<?php echo get_permalink($cluster->ID); ?>">
                                <?php echo esc_html($cluster->post_title); ?>
                            </a>
                        </h3>

                        <?php if ($atts['show_excerpt'] === 'true'): ?>
                        <div class="cluster-excerpt">
                            <?php echo wpautop(wp_trim_words($cluster->post_excerpt ?: $cluster->post_content, 20)); ?>
                        </div>
                        <?php endif; ?>

                        <?php if ($atts['show_meta'] === 'true'): ?>
                        <div class="cluster-meta">
                            <time datetime="<?php echo esc_attr(get_the_date('c', $cluster)); ?>">
                                <?php echo esc_html(get_the_date('', $cluster)); ?>
                            </time>
                        </div>
                        <?php endif; ?>
                    </div>

                </div>
            </article>
            <?php endforeach; ?>

        </div>
        <?php

        return ob_get_clean();
    }

    /**
     * Render featured clusters shortcode
     */
    public function render_featured_clusters_shortcode($atts) {
        $atts['featured'] = 'true';
        return $this->render_cluster_list_shortcode($atts);
    }

    /**
     * Get video embed code
     */
    private function get_video_embed($url) {
        $embed_code = '';

        // YouTube
        if (preg_match('/youtube\.com\/watch\?v=([^\&\n]+)|youtu\.be\/([^\n]+)/', $url, $matches)) {
            $video_id = $matches[1] ?: $matches[2];
            $embed_code = '<iframe width="560" height="315" src="https://www.youtube.com/embed/' . esc_attr($video_id) . '" frameborder="0" allowfullscreen></iframe>';
        }
        // Vimeo
        elseif (preg_match('/vimeo\.com\/(\d+)/', $url, $matches)) {
            $video_id = $matches[1];
            $embed_code = '<iframe width="560" height="315" src="https://player.vimeo.com/video/' . esc_attr($video_id) . '" frameborder="0" allowfullscreen></iframe>';
        }

        return $embed_code;
    }

    /**
     * Enqueue scripts and styles
     */
    public function enqueue_scripts() {
        wp_enqueue_style(
            'cluster-manager-styles',
            plugin_dir_url(dirname(__FILE__)) . 'assets/css/cluster-manager.css',
            array(),
            Cluster_Manager_Plugin::VERSION
        );

        wp_enqueue_script(
            'cluster-manager-scripts',
            plugin_dir_url(dirname(__FILE__)) . 'assets/js/cluster-manager.js',
            array('jquery'),
            Cluster_Manager_Plugin::VERSION,
            true
        );

        wp_localize_script('cluster-manager-scripts', 'cluster_manager', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cluster_manager_nonce'),
        ));
    }
}
