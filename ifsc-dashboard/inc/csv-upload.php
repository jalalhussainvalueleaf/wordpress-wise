<?php
// csv-upload.php (CSV-only version - XLSX removed)

if (!defined('ABSPATH')) exit;

/**
 * AJAX Action: upload_ifsc_file_check
 * Validates uploaded CSV and checks for duplicates.
 */
add_action('wp_ajax_upload_ifsc_file_check', function() {
    ifsc_verify_nonce();

    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        wp_send_json_error(['message' => 'File upload error. Code: ' . $_FILES['csv_file']['error']]);
    }

    $upload_dir = wp_upload_dir();
    $temp_dir = $upload_dir['basedir'] . '/ifsc-temp-uploads';
    wp_mkdir_p($temp_dir);
    $temp_file_path = $temp_dir . '/' . wp_unique_filename($temp_dir, $_FILES['csv_file']['name']);

    if (!move_uploaded_file($_FILES['csv_file']['tmp_name'], $temp_file_path)) {
        wp_send_json_error(['message' => 'Failed to save temporary file. Check permissions.']);
    }

    try {
        $data = ifsc_read_spreadsheet_data($temp_file_path);
    } catch (Exception $e) {
        unlink($temp_file_path);
        wp_send_json_error(['message' => 'Error reading file: ' . $e->getMessage()]);
    }

    if (empty($data['rows'])) {
        unlink($temp_file_path);
        wp_send_json_error(['message' => 'File is empty or columns could not be mapped. Ensure headers like "IFSC", "BANK", "BRANCH" etc., are present.']);
    }

    global $wpdb;
    $ifscs_in_file = array_filter(array_column($data['rows'], 'ifsc'));
    if (empty($ifscs_in_file)) {
        unlink($temp_file_path);
        wp_send_json_error(['message' => 'No rows with a valid IFSC code found in the file.']);
    }

    $placeholders = implode(', ', array_fill(0, count($ifscs_in_file), '%s'));
    $query = $wpdb->prepare("SELECT ifsc FROM {$wpdb->prefix}ifsc_codes WHERE ifsc IN ($placeholders)", $ifscs_in_file);
    $existing_ifscs = $wpdb->get_col($query);

    $file_token = 'ifsc_upload_' . get_current_user_id() . '_' . bin2hex(random_bytes(8));
    set_transient($file_token, ['path' => $temp_file_path, 'total' => count($data['rows'])], HOUR_IN_SECONDS);

    if (!empty($existing_ifscs)) {
        wp_send_json_error([
            'status' => 'duplicates_found',
            'message' => 'Duplicate IFSCs detected.',
            'duplicates' => $existing_ifscs
        ]);
    } else {
        wp_send_json_success([
            'status' => 'ready_to_process',
            'message' => 'File is valid. Starting import.',
            'file_token' => $file_token,
            'total_rows' => count($data['rows'])
        ]);
    }
});

/**
 * AJAX Action: process_ifsc_file_chunk
 * Processes data in chunks (CSV only)
 */
add_action('wp_ajax_process_ifsc_file_chunk', function() {
    ifsc_verify_nonce();
    global $wpdb;

    $file_token = sanitize_text_field($_POST['file_token']);
    $offset = isset($_POST['offset']) ? absint($_POST['offset']) : 0;
    $chunk_size = 100;
    $duplicate_action = in_array($_POST['duplicate_action'], ['update', 'skip']) ? $_POST['duplicate_action'] : 'skip';

    $upload_data = get_transient($file_token);
    if (false === $upload_data) {
        wp_send_json_error(['message' => 'Upload session expired or invalid. Please upload the file again.']);
    }

    try {
        $data = ifsc_read_spreadsheet_data($upload_data['path']);
        $all_rows = $data['rows'];
        $chunk = array_slice($all_rows, $offset, $chunk_size);
    } catch (Exception $e) {
        wp_send_json_error(['message' => 'Error reading file chunk: ' . $e->getMessage()]);
    }

    if (empty($chunk)) {
        unlink($upload_data['path']);
        delete_transient($file_token);
        wp_send_json_success(['status' => 'complete', 'message' => 'All rows processed.']);
        return;
    }

    $table = $wpdb->prefix . 'ifsc_codes';
    foreach ($chunk as $row) {
        if (empty($row['ifsc'])) continue;

        $existing_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE ifsc = %s", $row['ifsc']));
        if ($existing_id) {
            if ($duplicate_action === 'update') {
                $wpdb->update($table, $row, ['id' => $existing_id]);
            }
        } else {
            $wpdb->insert($table, $row);
        }
    }

    wp_send_json_success([
        'status' => 'processing',
        'processed_count' => $offset + count($chunk),
        'total_rows' => count($all_rows),
    ]);
});

/**
 * Helper function: Read CSV file and map to DB columns.
 */
function ifsc_read_spreadsheet_data($file_path) {
    $file_extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
    $raw_rows = [];

    if ($file_extension === 'csv') {
        if (($handle = fopen($file_path, 'r')) !== FALSE) {
            $header = fgetcsv($handle);
            while (($data = fgetcsv($handle)) !== FALSE) {
                if (count($data) === count($header)) {
                    $raw_rows[] = array_combine($header, $data);
                }
            }
            fclose($handle);
        }
    } else {
        throw new Exception('Only CSV files are supported.');
    }

    $column_map = [
        'bank_name' => ['BANK', 'Bank Name', 'Bank'],
        'branch' => ['BRANCH', 'Branch Name'],
        'ifsc' => ['IFSC', 'IFSC Code'],
        'micr' => ['MICR'],
        'address' => ['ADDRESS', 'Address'],
        'city' => ['CITY', 'CITY1', 'City'],
        'state' => ['STATE', 'State'],
        'district' => ['DISTRICT','District'],
        'contact_number' => ['CONTACT', 'Contact Number', 'Contact'],
    ];

    $mapped_rows = [];
    foreach ($raw_rows as $raw_row) {
        $mapped_row = [];
        foreach ($column_map as $db_col => $possible_headers) {
            foreach ($possible_headers as $header) {
                if (isset($raw_row[$header])) {
                    $mapped_row[$db_col] = sanitize_text_field($raw_row[$header]);
                    break;
                }
            }
        }
        if (!empty($mapped_row['ifsc'])) {
            $mapped_rows[] = $mapped_row;
        }
    }

    return ['rows' => $mapped_rows];
}
