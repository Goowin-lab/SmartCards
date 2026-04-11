<?php
if (!defined('ABSPATH')) exit;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class SC_REST_Me {

  public static function init() {
    add_action('rest_api_init', [__CLASS__, 'routes']);
  }

  public static function routes() {

    // GET /smartcards/v1/me
    register_rest_route('smartcards/v1', '/me', [
      'methods'  => 'GET',
      'callback' => [__CLASS__, 'get_me'],
      'permission_callback' => '__return_true', // JWT valida después
    ]);
  }

  public static function get_me( WP_REST_Request $req ) {

    // Leer usuario autenticado vía JWT (plugin oficial)
    $user = wp_get_current_user();

    if ( !$user || $user->ID === 0 ) {
        return new WP_REST_Response([
            'message' => 'Unauthorized'
        ], 401);
    }

    // Créditos del usuario
    $credits = (int) get_user_meta( $user->ID, 'smartcards_credits', true );

    return [
        'id'           => $user->ID,
        'email'        => $user->user_email,
        'display_name' => $user->display_name,
        'name'         => $user->display_name,
        'credits'      => $credits,
    ];
  }
}

SC_REST_Me::init();

