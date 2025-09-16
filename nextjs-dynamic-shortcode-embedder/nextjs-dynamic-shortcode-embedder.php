<?php
/*
Plugin Name: MAD Next.js Dynamic Shortcode Embedder
Description: Dynamically register shortcodes for Next.js components and manage them from the admin.
Version: 2.0
Author: Jalal Hussain
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

if (!defined('ABSPATH')) exit;

// --- Option keys ---
define('NSE_SHORTCODES_OPTION', 'nse_dynamic_shortcodes');
define('NSE_COMPONENTS_OPTION', 'nse_available_components');
define('NSE_BASE_URL_OPTION', 'nse_nextjs_base_url');

// --- Helpers ---
function nse_get_components() {
    $default = ['PersonalLoan', 'FreeCredit','MobileApp','Features','Locations','Clusters','EmiCalculator','CompoundInterestCalculator','EligibilityCalculator','FDCalculator','GratuityCalculator','GSTCalculator','HomeLoanEMICalculator','IncomeTaxCalculator','PensionCalculator','RDCalculator','SimpleInterestCalculator','SIPCalculator'];
    $components = get_option(NSE_COMPONENTS_OPTION, $default);
    return array_filter(array_map('sanitize_text_field', (array)$components));
}

function nse_get_shortcodes() {
    $default = [
        ['slug' => 'personal-loan', 'component' => 'PersonalLoan', 'url' => ''],
        ['slug' => 'free-credit', 'component' => 'FreeCredit', 'url' => ''],
        ['slug' => 'mobileapp-download', 'component' => 'MobileApp', 'url' => ''],
        ['slug' => 'features-cta', 'component' => 'Features', 'url' => ''],
        ['slug' => 'location-cta', 'component' => 'Locations', 'url' => ''],
        ['slug' => 'clusters-cta', 'component' => 'Clusters', 'url' => ''],
        ['slug' => 'emi-calculator', 'component' => 'EmiCalculator', 'url' => ''],
        ['slug' => 'ci-calculator', 'component' => 'CompoundInterestCalculator', 'url' => ''],
        ['slug' => 'eligibility-calculator', 'component' => 'EligibilityCalculator', 'url' => ''],
        ['slug' => 'fd-calculator', 'component' => 'FDCalculator', 'url' => ''],
        ['slug' => 'gratuity-calculator', 'component' => 'GratuityCalculator', 'url' => ''],
        ['slug' => 'gst-calculator', 'component' => 'GSTCalculator', 'url' => ''],
        ['slug' => 'home-calculator', 'component' => 'HomeLoanEMICalculator', 'url' => ''],
        ['slug' => 'it-calculator', 'component' => 'IncomeTaxCalculator', 'url' => ''],
        ['slug' => 'pension-calculator', 'component' => 'PensionCalculator', 'url' => ''],
        ['slug' => 'rd-calculator', 'component' => 'RDCalculator', 'url' => ''],
        ['slug' => 'si-calculator', 'component' => 'SimpleInterestCalculator', 'url' => ''],
        ['slug' => 'sip-calculator', 'component' => 'SIPCalculator', 'url' => ''],
        

    ];
    $shortcodes = get_option(NSE_SHORTCODES_OPTION, $default);
    // Remove empty slugs/components
    return array_values(array_filter($shortcodes, function($s) {
        return !empty($s['slug']) && !empty($s['component']);
    }));
}

// --- Register all shortcodes ---
add_action('init', function() {
    foreach (nse_get_shortcodes() as $sc) {
        add_shortcode($sc['slug'], function($atts, $content = null, $tag = '') use ($sc) {
            $uniq = uniqid();
            $div_id = 'nextjs-' . esc_attr($sc['slug']) . '-' . $uniq;
            $atts = shortcode_atts(['url' => ''], $atts);
            $url = !empty($atts['url']) ? esc_url($atts['url']) : (isset($sc['url']) ? esc_url($sc['url']) : '');
            return '<div class="nextjs-shortcode-embed" id="' . $div_id . '" data-nextjs-component="' . esc_attr($sc['component']) . '" data-nextjs-url="' . $url . '"></div>';
        });
    }
});

// --- Admin Menu ---
add_action('admin_menu', function() {
    // Allow for admin, editor, seo_editor, seo_manager
    if (
        current_user_can('manage_options') || // Administrator
        current_user_can('edit_pages') ||     // Editor
        current_user_can('seo_editor') ||     // SEO Editor (custom role/cap)
        current_user_can('seo_manager')       // SEO Manager (custom role/cap)
    ) {
        add_menu_page(
            'Next.js Shortcode Embedder',
            'Next.js Shortcodes',
            'read', // Minimum capability, but we check above
            'nextjs-shortcode-embedder',
            'nse_admin_page',
            'dashicons-editor-code',
            60
        );
    }
});

// --- Admin Page ---
function nse_admin_page() {
    $active_tab = $_GET['tab'] ?? 'shortcodes';
    echo '<div class="wrap"><h1>Next.js Shortcode Embedder</h1>';
    echo '<nav class="nav-tab-wrapper">';
    echo '<a href="?page=nextjs-shortcode-embedder&tab=shortcodes" class="nav-tab' . ($active_tab=='shortcodes'?' nav-tab-active':'') . '">Shortcodes</a>';
    echo '<a href="?page=nextjs-shortcode-embedder&tab=components" class="nav-tab' . ($active_tab=='components'?' nav-tab-active':'') . '">Components</a>';
    echo '<a href="?page=nextjs-shortcode-embedder&tab=usage" class="nav-tab' . ($active_tab=='usage'?' nav-tab-active':'') . '">Usage</a>';
    echo '</nav>';
    if ($active_tab === 'components') {
        nse_components_tab();
    } elseif ($active_tab === 'usage') {
        nse_usage_tab();
    } else {
        nse_shortcodes_tab();
    }
    echo '</div>';
}

function nse_usage_tab() {
    ?>
    <div style="margin-top: 20px;">
        <h2>Usage</h2>
        <ul>
            <?php foreach (nse_get_shortcodes() as $sc): ?>
            <li>
                <code>[<?php echo esc_html($sc['slug']); ?> url="<?php echo esc_html($sc['url']); ?>"]</code>
                <?php if (!empty($sc['url'])): ?>
                  <!-- (default URL: <code></code>) -->
                <?php endif; ?>
                <!-- <br>
                <small>Custom URL: <code>[<?php echo esc_html($sc['slug']); ?> url="https://your-url.com"]</code></small> -->
            </li>
            <?php endforeach; ?>
        </ul>
        <p>Use these shortcodes in your post or page content. The corresponding Next.js component will be embedded at that location. You can override the default URL by passing a <code>url</code> attribute.</p>
    </div>
    <?php
}

// --- Components Tab ---
function nse_components_tab() {
    if (isset($_POST['nse_save_components']) && check_admin_referer('nse_save_components')) {
        $components = array_filter(array_map('sanitize_text_field', (array)($_POST['nse_components'] ?? [])));
        update_option(NSE_COMPONENTS_OPTION, $components);
        echo '<div class="updated notice"><p>Components updated.</p></div>';
    }
    $components = nse_get_components();
    ?>
    <form method="post">
        <?php wp_nonce_field('nse_save_components'); ?>
        <h2>Manage Next.js Components</h2>
        <table class="widefat fixed striped">
            <thead><tr><th>Component Name</th><th>Action</th></tr></thead>
            <tbody id="nse-components-tbody">
                <?php foreach ($components as $i => $comp): ?>
                <tr>
                    <td><input type="text" name="nse_components[]" value="<?php echo esc_attr($comp); ?>" required /></td>
                    <td><button type="button" class="button nse-remove-component">Remove</button></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <button type="button" class="button" id="nse-add-component">Add Component</button>
        <?php submit_button('Save Components', 'primary', 'nse_save_components'); ?>
    </form>
    <script>
    document.getElementById('nse-add-component').addEventListener('click', function() {
        var tbody = document.getElementById('nse-components-tbody');
        var row = document.createElement('tr');
        row.innerHTML = '<td><input type="text" name="nse_components[]" required /></td>' +
                        '<td><button type="button" class="button nse-remove-component">Remove</button></td>';
        tbody.appendChild(row);
    });
    document.getElementById('nse-components-tbody').addEventListener('click', function(e) {
        if (e.target.classList.contains('nse-remove-component')) {
            e.target.closest('tr').remove();
        }
    });
    </script>
    <?php
}

// --- Shortcodes Tab ---
function nse_shortcodes_tab() {
    // Handle save
    if (isset($_POST['nse_save_shortcodes']) && check_admin_referer('nse_save_shortcodes')) {
        $shortcodes = [];
        if (!empty($_POST['nse_shortcodes'])) {
            foreach ($_POST['nse_shortcodes'] as $sc) {
                $slug = sanitize_title($sc['slug']);
                $component = sanitize_text_field($sc['component']);
                $url = esc_url_raw($sc['url'] ?? '');
                if ($slug && $component) {
                    $shortcodes[] = [
                        'slug' => $slug,
                        'component' => $component,
                        'url' => $url,
                    ];
                }
            }
        }
        update_option(NSE_SHORTCODES_OPTION, $shortcodes);
        echo '<div class="updated notice"><p>Shortcodes updated.</p></div>';
    }
    $components = nse_get_components();
    $all_shortcodes = nse_get_shortcodes();
    $filter = $_GET['nse_component_filter'] ?? '';
    $shortcodes = $all_shortcodes;
    if ($filter) {
        $shortcodes = array_filter($shortcodes, function($sc) use ($filter) {
            return $sc['component'] === $filter;
        });
    }
    // Calculate counts for each component
    $component_counts = array_count_values(array_map(function($sc) { return $sc['component']; }, $all_shortcodes));
    ?>
    <form method="post">
        <?php wp_nonce_field('nse_save_shortcodes'); ?>
        <div style="margin:20px 0 10px 0;display:flex;align-items:center;gap:10px;">
            <label for="nse_component_filter"><strong>Filter by Component:</strong></label>
            <select id="nse_component_filter" onchange="location.href='?page=nextjs-shortcode-embedder&tab=shortcodes&nse_component_filter='+this.value;">
                <option value="">All Components (<?php echo count($all_shortcodes); ?>)</option>
                <?php foreach ($components as $comp): ?>
                    <option value="<?php echo esc_attr($comp); ?>" <?php selected($filter, $comp); ?>><?php echo esc_html($comp); ?> (<?php echo isset($component_counts[$comp]) ? $component_counts[$comp] : 0; ?>)</option>
                <?php endforeach; ?>
            </select>
            <button type="button" class="button" id="nse-add-shortcode">Add New Shortcode</button>
        </div>
        <table class="widefat fixed striped" id="nse-shortcodes-table">
            <thead>
                <tr>
                    <th>Shortcode</th>
                    <th>Component</th>
                    <th>URL</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($shortcodes as $i => $sc): ?>
                <tr>
                    <td><input type="text" name="nse_shortcodes[<?php echo $i; ?>][slug]" value="<?php echo esc_attr($sc['slug']); ?>" required /></td>
                    <td>
                        <select name="nse_shortcodes[<?php echo $i; ?>][component]" required>
                            <?php foreach ($components as $comp): ?>
                                <option value="<?php echo esc_attr($comp); ?>" <?php selected($sc['component'], $comp); ?>><?php echo esc_html($comp); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td><input type="text" name="nse_shortcodes[<?php echo $i; ?>][url]" value="<?php echo esc_attr($sc['url'] ?? ''); ?>" placeholder="https://..." style="width:100%" /></td>
                    <td><button type="button" class="button nse-remove-shortcode">Remove</button></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php submit_button('Save Shortcodes', 'primary', 'nse_save_shortcodes'); ?>
    </form>
    <script>
    document.getElementById('nse-add-shortcode').addEventListener('click', function() {
        var table = document.getElementById('nse-shortcodes-table').getElementsByTagName('tbody')[0];
        var rowCount = table.rows.length;
        var components = <?php echo json_encode($components); ?>;
        var selectHtml = '<select name="nse_shortcodes['+rowCount+'][component]" required>';
        for (var i=0; i<components.length; i++) {
            selectHtml += '<option value="'+components[i]+'">'+components[i]+'</option>';
        }
        selectHtml += '</select>';
        var row = table.insertRow();
        row.innerHTML = '<td><input type="text" name="nse_shortcodes['+rowCount+'][slug]" required /></td>' +
                        '<td>'+selectHtml+'</td>' +
                        '<td><input type="text" name="nse_shortcodes['+rowCount+'][url]" placeholder="https://..." style="width:100%" /></td>' +
                        '<td><button type="button" class="button nse-remove-shortcode">Remove</button></td>';
    });
    document.getElementById('nse-shortcodes-table').addEventListener('click', function(e) {
        if (e.target.classList.contains('nse-remove-shortcode')) {
            e.target.closest('tr').remove();
        }
    });
    </script>
    <?php
}

