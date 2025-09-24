<?php
// ajax-handlers.php – Handles all AJAX actions for Pincode Dashboard

if (!defined('ABSPATH')) exit;

function pincode_verify_nonce() {
    if (!check_ajax_referer('pincode-dashboard-nonce', 'nonce', false)) {
        wp_send_json_error(['message' => 'Security check failed.'], 403);
    }
}

// Get filtered pincode data
add_action('wp_ajax_get_pincode_data', function () {
    pincode_verify_nonce();
    global $wpdb;
    $table = $wpdb->prefix . 'pincode_data';

    $limit = 20;
    $page = max(1, intval($_POST['page'] ?? 1));
    $offset = ($page - 1) * $limit;

    $where = [];
    $params = [];

    if (!empty($_POST['pincode'])) {
        $where[] = 'pincode = %s';
        $params[] = sanitize_text_field($_POST['pincode']);
    } else {
        foreach (['statename', 'district', 'officename'] as $col) {
            if (!empty($_POST[$col])) {
                $where[] = "$col = %s";
                $params[] = sanitize_text_field($_POST[$col]);
            }
        }
    }

    $where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
    $query_sql = "SELECT SQL_CALC_FOUND_ROWS id, circlename, regionname, divisionname, officename, pincode, officetype, delivery, district, statename, latitude, longitude FROM $table $where_sql ORDER BY id DESC LIMIT %d OFFSET %d";
    $query = $wpdb->prepare($query_sql, [...$params, $limit, $offset]);
    $results = $wpdb->get_results($query, ARRAY_A);

    $total = $wpdb->get_var("SELECT FOUND_ROWS()");
    $total_pages = ceil($total / $limit);

    wp_send_json_success([
        'rows' => $results,
        'pagination' => [
            'total_items'   => (int) $total,
            'total_pages'   => $total_pages,
            'current_page'  => $page
        ]
    ]);

});

// Edit row
add_action('wp_ajax_edit_pincode_row', function () {
    pincode_verify_nonce();
    global $wpdb;

    $id = intval($_POST['id'] ?? 0);
    if (!$id) wp_send_json_error(['message' => 'Invalid ID']);

    $fields = [
        'circlename', 'regionname', 'divisionname',
        'officename', 'pincode', 'officetype', 'delivery',
        'district', 'statename', 'latitude', 'longitude'
    ];

    $data = [];
    foreach ($fields as $field) {
        $data[$field] = sanitize_text_field($_POST[$field] ?? '');
    }

    $res = $wpdb->update($wpdb->prefix . 'pincode_data', $data, ['id' => $id]);

    if ($res === false) {
        wp_send_json_error(['message' => 'Update failed']);
    }

    wp_send_json_success(['message' => 'Row updated successfully']);
});

// Get Districts
add_action('wp_ajax_get_pincode_districts', function () {
    pincode_verify_nonce();
    global $wpdb;
    $state = sanitize_text_field($_POST['statename'] ?? '');
    if (!$state) wp_send_json_error();

    $results = $wpdb->get_col($wpdb->prepare("SELECT DISTINCT district FROM {$wpdb->prefix}pincode_data WHERE statename = %s ORDER BY district ASC", $state));
    wp_send_json_success($results);
});

// Get Offices
add_action('wp_ajax_get_pincode_offices', function () {
    pincode_verify_nonce();
    global $wpdb;
    $state = sanitize_text_field($_POST['statename'] ?? '');
    $district = sanitize_text_field($_POST['district'] ?? '');
    if (!$state || !$district) wp_send_json_error();

    $results = $wpdb->get_col($wpdb->prepare("SELECT DISTINCT officename FROM {$wpdb->prefix}pincode_data WHERE statename = %s AND district = %s ORDER BY officename ASC", $state, $district));
    wp_send_json_success($results);
});

// Delete row
add_action('wp_ajax_delete_pincode_row', function () {
    pincode_verify_nonce();
    global $wpdb;
    $id = intval($_POST['id'] ?? 0);
    if (!$id) wp_send_json_error(['message' => 'Invalid ID']);

    $table = $wpdb->prefix . 'pincode_data';
    $res = $wpdb->delete($table, ['id' => $id]);

    if ($res === false) wp_send_json_error(['message' => 'Failed to delete']);
    wp_send_json_success(['message' => 'Row is Deleted Successfully']);
});

// Delete all records
add_action('wp_ajax_delete_all_pincode_data', function () {
    pincode_verify_nonce();
    global $wpdb;
    $res = $wpdb->query("DELETE FROM {$wpdb->prefix}pincode_data");

    if ($res === false) wp_send_json_error(['message' => 'Failed to delete all records']);
    wp_send_json_success(['message' => 'All records deleted successfully']);
});

// Download all data as CSV
add_action('wp_ajax_download_pincode_data', function () {
    pincode_verify_nonce();
    global $wpdb;
    $table = $wpdb->prefix . 'pincode_data';
    $rows = $wpdb->get_results("SELECT circlename, regionname, divisionname, officename, pincode, officetype, delivery, district, statename, latitude, longitude FROM $table ORDER BY id DESC", ARRAY_A);
    wp_send_json_success($rows);
});

// Get States
add_action('wp_ajax_get_pincode_states', function () {
    pincode_verify_nonce();
    global $wpdb;
    $results = $wpdb->get_col("SELECT DISTINCT statename FROM {$wpdb->prefix}pincode_data WHERE statename != '' ORDER BY statename ASC");
    wp_send_json_success($results);
});

// CSV Upload Init (no duplicate check)
add_action('wp_ajax_upload_pincode_file_check', function () {
    pincode_verify_nonce();
    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        wp_send_json_error(['message' => 'File upload failed.']);
    }

    $upload_dir = wp_upload_dir();
    $temp_dir = $upload_dir['basedir'] . '/pincode-temp';
    wp_mkdir_p($temp_dir);
    $temp_path = $temp_dir . '/' . wp_unique_filename($temp_dir, $_FILES['csv_file']['name']);

    if (!wp_handle_upload($_FILES['csv_file']['tmp_name'], $temp_path)) {
        wp_send_json_error(['message' => 'Could not save file.']);
    }

    $rows = pincode_parse_csv($temp_path);
    if (empty($rows)) {
        wp_send_json_error(['message' => 'File appears empty or invalid.']);
    }

    $token = 'pincode_' . get_current_user_id() . '_' . wp_generate_password(8, false);
    set_transient($token, ['file' => $temp_path, 'total_rows' => count($rows), 'data' => $rows], HOUR_IN_SECONDS);

    wp_send_json_success(['file_token' => $token, 'total_rows' => count($rows)]);
});

// Chunk Upload
add_action('wp_ajax_process_pincode_file_chunk', function () {
    pincode_verify_nonce();
    $token = sanitize_text_field($_POST['file_token'] ?? '');
    $offset = intval($_POST['offset'] ?? 0);
    $chunk = 100;

    $data = get_transient($token);
    if (!$data || !file_exists($data['file'])) {
        wp_send_json_error(['message' => 'Upload session expired.']);
    }

    $rows = $data['data'];
    $current = array_slice($rows, $offset, $chunk);
    global $wpdb;
    $table = $wpdb->prefix . 'pincode_data';

    foreach ($current as $row) {
        if (empty($row['pincode'])) continue;
        $wpdb->insert($table, $row);
    }

    $processed = $offset + count($current);
    $total = $data['total_rows'];
    if ($processed >= $total) {
        delete_transient($token);
        unlink($data['file']);
        wp_send_json_success(['complete' => true, 'processed_count' => $processed, 'total_rows' => $total]);
    }

    wp_send_json_success(['processed_count' => $processed, 'total_rows' => $total, 'status' => 'processing']);
});

function pincode_parse_csv($path) {
    $out = [];
    if (($h = fopen($path, 'r')) !== false) {
        $head = fgetcsv($h);
        while (($row = fgetcsv($h)) !== false) {
            $item = array_combine($head, $row);
            $out[] = [
                'circlename' => sanitize_text_field($item['circlename'] ?? ''),
                'regionname' => sanitize_text_field($item['regionname'] ?? ''),
                'divisionname' => sanitize_text_field($item['divisionname'] ?? ''),
                'officename' => sanitize_text_field($item['officename'] ?? ''),
                'pincode' => sanitize_text_field($item['pincode'] ?? ''),
                'officetype' => sanitize_text_field($item['officetype'] ?? ''),
                'delivery' => sanitize_text_field($item['delivery'] ?? ''),
                'district' => sanitize_text_field($item['district'] ?? ''),
                'statename' => sanitize_text_field($item['statename'] ?? ''),
                'latitude' => sanitize_text_field($item['latitude'] ?? ''),
                'longitude' => sanitize_text_field($item['longitude'] ?? '')
            ];
        }
        fclose($h);
    }
    return $out;
}
