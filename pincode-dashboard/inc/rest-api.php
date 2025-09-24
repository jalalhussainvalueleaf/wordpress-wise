<?php
// inc/rest-api.php
if (!defined('ABSPATH')) exit;
add_action('rest_api_init', function () {
    register_rest_route('pincode/v1', '/records', [
        'methods'  => WP_REST_Server::READABLE,
        'callback' => 'pincode_get_records_api_callback',
        'permission_callback' => '__return_true',
        'args'     => [
            'state' => [
                'description' => 'Filter by state name.',
                'type'        => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'district' => [
                'description' => 'Filter by district name.',
                'type'        => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'pincode' => [
                'description' => 'Filter by pincode.',
                'type'        => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'limit' => [
                'description' => 'Maximum number of items to return.',
                'type'        => 'integer',
                'default'     => 100,
                'sanitize_callback' => 'absint',
            ],
            'offset' => [
                'description' => 'Offset number of items.',
                'type'        => 'integer',
                'default'     => 0,
                'sanitize_callback' => 'absint',
            ],
        ],
    ]);
});

/**
 * Callback for /pincode/v1/records
 */
function pincode_get_records_api_callback(WP_REST_Request $request) {
    global $wpdb;
    $table = $wpdb->prefix . 'pincode_data';

    $state    = $request->get_param('state');
    $district = $request->get_param('district');
    $pincode  = $request->get_param('pincode');
    $limit    = $request->get_param('limit');
    $offset   = $request->get_param('offset');

    $where_clauses = [];
    $where_params  = [];

    if (!empty($state)) {
        $where_clauses[] = 'statename = %s';
        $where_params[]  = $state;
    }

    if (!empty($district)) {
        $where_clauses[] = 'district = %s';
        $where_params[]  = $district;
    }

    if (!empty($pincode)) {
        $where_clauses[] = 'pincode = %s';
        $where_params[]  = $pincode;
    }
    $where_sql = $where_clauses ? 'WHERE ' . implode(' AND ', $where_clauses) : '';
    $total_items = $wpdb->get_var($wpdb->prepare("SELECT COUNT(id) FROM $table $where_sql", $where_params));
    $sql = "SELECT * FROM $table $where_sql ORDER BY id ASC LIMIT %d OFFSET %d";
    $query_params = array_merge($where_params, [$limit, $offset]);
    $results = $wpdb->get_results($wpdb->prepare($sql, ...$query_params), ARRAY_A);

    $response = new WP_REST_Response($results);
    $response->header('X-WP-Total', (int) $total_items);
    $response->header('X-WP-TotalPages', ceil($total_items / $limit));

    return $response;
}

