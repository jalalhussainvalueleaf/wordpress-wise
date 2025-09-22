<?php
/**
 * Cluster Manager ACF Class
 *
 * Handles Advanced Custom Fields integration for clusters
 */

if (!defined('ABSPATH')) {
    exit;
}

class Cluster_Manager_ACF {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('acf/init', array($this, 'init_acf'));
        add_action('acf/include_fields', array($this, 'include_field_groups'));
        add_filter('acf/load_field/name=cluster_shortcode', array($this, 'load_shortcode_field'));
        add_action('acf/save_post', array($this, 'save_cluster_fields'), 20);

        // Create field groups on plugin initialization if ACF is active
        if (function_exists('acf_add_local_field_group')) {
            add_action('init', array($this, 'create_field_groups'), 20);
        } else {
            add_action('admin_notices', array($this, 'acf_not_active_notice'));
        }
    }

    /**
     * Initialize ACF functionality
     */
    public function init_acf() {
        // Ensure field groups are created
        $this->create_field_groups();
    }

    /**
     * Create ACF field groups for clusters
     */
    public function create_field_groups() {
        // No custom field groups - using standard WordPress fields only
        // This provides a clean, simple interface with just:
        // - Title
        // - Content editor
        // - Excerpt
        // - Featured image
        // - Categories and Tags
        return true;
    }

    /**
     * Include field groups
     */
    public function include_field_groups() {
        // This method can be used to include field groups from external files
    }

    /**
     * Load shortcode field value
     */
    public function load_shortcode_field($field) {
        // No custom fields to load
        return $field;
    }

    /**
     * Save cluster fields
     */
    public function save_cluster_fields($post_id) {
        // No custom fields to save
        return;
    }

    /**
     * Display notice if ACF is not active
     */
    public function acf_not_active_notice() {
        // ACF is now optional, no need to show error notice
        return;
    }
}
