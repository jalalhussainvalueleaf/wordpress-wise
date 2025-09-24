<?php
// inc/rest-api.php

add_action('rest_api_init', function () {
    register_rest_route('ifsc/v1', '/codes', [
        'methods'  => WP_REST_Server::READABLE, // Constant for 'GET'
        'callback' => 'ifsc_get_codes_api_callback',
        'permission_callback' => '__return_true', // Publicly accessible endpoint
        'args'     => [ // Define, validate, and sanitize arguments
            'bank' => [
                'description' => 'Filter results by bank name.',
                'type'        => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'state' => [
                'description' => 'Filter results by state name.',
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
                'description' => 'Offset the result set by a specific number of items.',
                'type'        => 'integer',
                'default'     => 0,
                'sanitize_callback' => 'absint',
            ],
        ],
    ]);
});

/**
 * Callback function for the /ifsc/v1/codes REST API endpoint.
 * @param WP_REST_Request $request The request object.
 * @return WP_REST_Response The response object.
 */
function ifsc_get_codes_api_callback(WP_REST_Request $request) {
    global $wpdb;
    $table = $wpdb->prefix . 'ifsc_codes';

    // Parameters are already sanitized via the 'args' definition
    $bank_name = $request->get_param('bank');
    $state     = $request->get_param('state');
    $limit     = $request->get_param('limit');
    $offset    = $request->get_param('offset');

    $where_clauses = [];
    $where_params = [];

    if (!empty($bank_name)) {
        $where_clauses[] = 'bank_name = %s';
        $where_params[] = $bank_name;
    }
    if (!empty($state)) {
        $where_clauses[] = 'state = %s';
        $where_params[] = $state;
    }

    $where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';
    
    // Get total count for pagination headers
    $total_items = $wpdb->get_var($wpdb->prepare("SELECT COUNT(id) FROM $table $where_sql", $where_params));
    
    // Get paginated results
    $sql = "SELECT * FROM $table $where_sql ORDER BY id ASC LIMIT %d OFFSET %d";
    $prepare_params = array_merge($where_params, [$limit, $offset]);
    $results = $wpdb->get_results($wpdb->prepare($sql, ...$prepare_params), ARRAY_A);

    $response = new WP_REST_Response($results);
    // Add standard pagination headers to the response
    $response->header('X-WP-Total', (int) $total_items);
    $response->header('X-WP-TotalPages', ceil($total_items / $limit));

    return $response;
}