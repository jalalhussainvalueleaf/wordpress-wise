<?php
/*
Plugin Name: Dynamic Button Shortcode
Description: Provides a [dynamic_button] shortcode for rendering customizable buttons with dynamic data and JS enhancements.
Version: 1.0
Author: Jalal Hussain
*/

if (!defined('ABSPATH')) exit;

function dbs_dynamic_button_shortcode($atts) {
    $atts = shortcode_atts([
        'text' => 'Click Me',
        'url' => 'http://example.com',
        'color' => '#0073aa',
        'target' => '_self',
        'data' => '', // Any extra dynamic data
    ], $atts, 'dynamic_button');

    $text = esc_html($atts['text']);
    $url = esc_url($atts['url']);
    $color = esc_attr($atts['color']);
    $target = esc_attr($atts['target']);
    $data = esc_attr($atts['data']);

    // The JS will enhance this button if needed
    return "<a href=$url target=\"$target\" class=\"dbs-dynamic-btn\" data-info=\"$data\" style=\"display:inline-block;padding:10px 20px;background:$color;color:#fff;text-decoration:none;border-radius:4px;transition:background 0.2s;\">$text</a>";
}
add_shortcode('dynamic_button', 'dbs_dynamic_button_shortcode');

add_action('wp_enqueue_scripts', function() {
    wp_enqueue_script(
        'dbs-dynamic-btn',
        plugin_dir_url(__FILE__) . 'dbs-dynamic-btn.js',
        [],
        '1.0',
        true
    );
}); 