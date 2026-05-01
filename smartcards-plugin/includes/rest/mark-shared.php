<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'rest_api_init', function () {
    register_rest_route(
        'smartcards/v1',
        '/mark-shared',
        [
            'methods'             => 'POST',
            'callback'            => 'sc_mark_shared_rest',
            'permission_callback' => function () {
                return is_user_logged_in();
            },
        ]
    );
} );

function sc_mark_shared_rest( WP_REST_Request $request ) {
    $user_id = get_current_user_id();

    if ( ! $user_id ) {
        return new WP_Error( 'unauthorized', 'No autorizado', [ 'status' => 401 ] );
    }

    update_user_meta( $user_id, 'sc_has_shared', 1 );

    return rest_ensure_response(
        [
            'success'    => true,
            'has_shared' => true,
        ]
    );
}
