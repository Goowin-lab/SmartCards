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
    $has_shared = (bool) get_user_meta( $user->ID, 'sc_has_shared', true );
    $latest_profile_id = self::get_latest_profile_id( $user->ID );
    $has_legacy_profile = self::user_has_legacy_profile( $user->ID );
    $has_profile = $latest_profile_id > 0 || $has_legacy_profile;
    $profile_complete = $latest_profile_id > 0
        ? self::profile_is_complete( $latest_profile_id )
        : self::legacy_profile_is_complete( $user->ID );

    return [
        'id'           => $user->ID,
        'email'        => $user->user_email,
        'display_name' => $user->display_name,
        'name'         => $user->display_name,
        'credits'      => $credits,
        'has_profile'  => $has_profile,
        'profile_complete' => $profile_complete,
        'has_shared' => $has_shared,
        'latest_profile_id' => $latest_profile_id,
    ];
  }

  private static function get_latest_profile_id( $user_id ) {
    $query = new WP_Query([
        'post_type'      => 'smartcards',
        'post_status'    => ['draft', 'publish'],
        'author'         => (int) $user_id,
        'posts_per_page' => 1,
        'orderby'        => 'date',
        'order'          => 'DESC',
        'fields'         => 'ids',
        'no_found_rows'  => true,
    ]);

    return ! empty( $query->posts ) ? (int) $query->posts[0] : 0;
  }

  private static function user_has_legacy_profile( $user_id ) {
    $profiles = get_user_meta( $user_id, 'smartcards_perfiles_urls', true );
    return is_array( $profiles ) && ! empty( $profiles );
  }

  private static function profile_is_complete( $profile_id ) {
    $first_name = trim( (string) get_post_meta( $profile_id, 'firstName', true ) );
    $last_name  = trim( (string) get_post_meta( $profile_id, 'lastName', true ) );
    $title      = trim( (string) get_the_title( $profile_id ) );
    $avatar_id  = (int) get_post_thumbnail_id( $profile_id );

    if ( $avatar_id <= 0 ) {
        $avatar_id = (int) get_post_meta( $profile_id, 'sc_avatar_id', true );
    }

    $has_name    = ( '' !== $first_name && '' !== $last_name ) || '' !== $title;
    $has_image   = $avatar_id > 0;
    $has_contact = self::profile_has_any_meta( $profile_id, [ 'email', 'phone', 'phone2', 'whatsapp' ] );

    return $has_name && $has_image && $has_contact;
  }

  private static function legacy_profile_is_complete( $user_id ) {
    $nombre   = trim( (string) get_user_meta( $user_id, 'smartcards_nombre', true ) );
    $apellido = trim( (string) get_user_meta( $user_id, 'smartcards_apellido', true ) );
    $foto_id  = (int) get_user_meta( $user_id, 'smartcards_foto_perfil_id', true );

    $has_contact = self::user_has_any_meta(
        $user_id,
        [ 'smartcards_correo_electronico', 'smartcards_telefono', 'smartcards_telefono2', 'smartcards_whatsapp' ]
    );

    return '' !== $nombre && '' !== $apellido && $foto_id > 0 && $has_contact;
  }

  private static function profile_has_any_meta( $profile_id, $keys ) {
    foreach ( $keys as $key ) {
        if ( '' !== trim( (string) get_post_meta( $profile_id, $key, true ) ) ) {
            return true;
        }
    }

    return false;
  }

  private static function user_has_any_meta( $user_id, $keys ) {
    foreach ( $keys as $key ) {
        if ( '' !== trim( (string) get_user_meta( $user_id, $key, true ) ) ) {
            return true;
        }
    }

    return false;
  }
}

SC_REST_Me::init();
