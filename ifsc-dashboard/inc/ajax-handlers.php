    <?php
    

// inc/ajax-handlers.php

if (!defined('ABSPATH')) exit;

/**
 * Centralized function to verify the AJAX nonce for security.
 * Dies if verification fails.
 */
function ifsc_verify_nonce() {
    if (!check_ajax_referer('ifsc-dashboard-nonce', 'nonce', false)) {
        wp_send_json_error(['message' => 'Security check failed. Please refresh the page.'], 403);
    }
}

/**
 * Gets distinct values for a given filter field, optionally based on other dependent filters.
 * Example: Get all 'states' where 'bank_name' is 'SBI'.
 * AJAX Action: get_ifsc_filter_options
 */
add_action('wp_ajax_get_ifsc_filter_options', function() {
    ifsc_verify_nonce();
    global $wpdb;
    $table = $wpdb->prefix . 'ifsc_codes';

    $filter = sanitize_key($_POST['filter'] ?? 'banks');
    $dependencies = isset($_POST['dependencies']) && is_array($_POST['dependencies']) ? $_POST['dependencies'] : [];
    
    $column_map = ['banks' => 'bank_name', 'states' => 'state', 'districts' => 'district', 'branches' => 'branch'];
    if (!isset($column_map[$filter])) {
        wp_send_json_error(['message' => 'Invalid filter type.']);
    }
    
    $where_clauses = [];
    $valid_keys = ['bank_name', 'state', 'district', 'branch'];
    foreach ($dependencies as $key => $value) {
        if (!empty($value) && in_array($key, $valid_keys)) {
            $where_clauses[] = "`$key` = %s";
            $params[] = sanitize_text_field($value);
        }
    }

    
    $where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';
    $column = $column_map[$filter];
    $query = $wpdb->prepare("SELECT DISTINCT $column FROM $table $where_sql ORDER BY $column ASC", $params);
    
    wp_send_json_success($wpdb->get_col($query));
});

/**
 * Gets paginated and filtered data for the main table.
 * Defaults to the 50 most recent entries.
 * AJAX Action: get_filtered_data
 */
add_action('wp_ajax_get_filtered_data', function() {
    ifsc_verify_nonce();
    global $wpdb;
    $table = $wpdb->prefix . 'ifsc_codes';

    $limit = 50;
    $page = isset($_POST['page']) ? absint($_POST['page']) : 1;
    $offset = ($page - 1) * $limit;

    $where = [];
    $params = [];

    // IFSC Search Enhancement
    if (!empty($_POST['ifsc_code'])) {
        $ifsc_code = sanitize_text_field($_POST['ifsc_code']);

        // If exactly 11 characters, do exact match
        if (strlen($ifsc_code) === 11) {
            $where[] = "BINARY ifsc = %s";
            $params[] = $ifsc_code;
        } else {
            $where[] = "ifsc LIKE %s";
            $params[] = $wpdb->esc_like($ifsc_code) . '%';
        }
    } else {
        $filters = ['bank_name', 'state', 'district', 'branch'];
        foreach ($filters as $key) {
            if (!empty($_POST[$key])) {
                $where[] = "$key = %s";
                $params[] = sanitize_text_field($_POST[$key]);
            }
        }
    }

    $where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    $total_items = $wpdb->get_var($wpdb->prepare("SELECT COUNT(id) FROM $table $where_clause", $params));

    $data_query = "SELECT id, bank_name, branch, ifsc , micr, contact_number, address, city, state FROM $table $where_clause ORDER BY id DESC LIMIT %d OFFSET %d";
    array_push($params, $limit, $offset);
    $results = $wpdb->get_results($wpdb->prepare($data_query, ...$params), ARRAY_A);

    wp_send_json_success([
        'rows' => $results,
        'pagination' => [
            'total_items' => (int) $total_items,
            'total_pages' => ceil($total_items / $limit),
            'current_page' => $page,
        ]
    ]);
});


/**
 * Handles inline editing of a single row.
 * AJAX Action: edit_ifsc_row
 */
add_action('wp_ajax_edit_ifsc_row', function() {
    ifsc_verify_nonce();
    global $wpdb;
    $table = $wpdb->prefix . 'ifsc_codes';
    
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    if (!$id) wp_send_json_error(['message' => 'Invalid ID specified.'], 400);
    
    $allowed_fields = ['bank_name', 'branch', 'ifsc', 'address'];
    $data = [];
    foreach ($allowed_fields as $field) {
        if (isset($_POST[$field])) $data[$field] = sanitize_text_field($_POST[$field]);
    }

    if (empty($data)) wp_send_json_error(['message' => 'No data provided to update.'], 400);

    if ($wpdb->update($table, $data, ['id' => $id]) === false) {
        wp_send_json_error(['message' => 'Database update failed: ' . $wpdb->last_error]);
    }
    
    wp_send_json_success(['message' => 'Row updated successfully.']);
});

/**
 * Handles deleting a single row by its ID.
 * AJAX Action: delete_ifsc_row
 */
add_action('wp_ajax_delete_ifsc_row', function() {
    ifsc_verify_nonce();
    global $wpdb;
    $table = $wpdb->prefix . 'ifsc_codes';

    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    if (!$id) wp_send_json_error(['message' => 'Invalid ID specified.'], 400);

    if ($wpdb->delete($table, ['id' => $id]) === false) {
        wp_send_json_error(['message' => 'Database error occurred during deletion.']);
    }
    
    wp_send_json_success(['message' => 'Row deleted successfully.']);
});

/**
 * Fetches all data for CSV download.
 * AJAX Action: download_all_data
 */
add_action('wp_ajax_download_all_data', function() {
    ifsc_verify_nonce();
    global $wpdb;
    $table = $wpdb->prefix . 'ifsc_codes';
    
    $results = $wpdb->get_results("SELECT bank_name, branch, ifsc, micr, address, city, state, district, contact_number FROM $table ORDER BY id ASC", ARRAY_A);
    
    if (empty($results)) {
        wp_send_json_error(['message' => 'No data available to download.']);
    }

    wp_send_json_success($results);
});
