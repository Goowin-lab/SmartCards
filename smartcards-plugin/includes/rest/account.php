<?php
if (!defined('ABSPATH')) exit;

/**
 * Endpoints nativos para "Mi cuenta" en la app móvil.
 *
 * POST /wp-json/smartcards/v1/update-profile
 * POST /wp-json/smartcards/v1/delete-account
 */
add_action('rest_api_init', function () {
  register_rest_route('smartcards/v1', '/update-profile', [
    'methods'  => 'POST',
    'callback' => 'sc_rest_account_update_profile',
    'permission_callback' => 'sc_rest_account_permission',
  ]);

  register_rest_route('smartcards/v1', '/delete-account', [
    'methods'  => 'POST',
    'callback' => 'sc_rest_account_delete_account',
    'permission_callback' => 'sc_rest_account_permission',
  ]);
});

function sc_rest_account_permission() {
  if (is_user_logged_in()) {
    return true;
  }

  return new WP_Error('rest_forbidden', 'No autorizado', ['status' => 401]);
}

function sc_rest_account_update_profile(WP_REST_Request $request) {
  $user_id = get_current_user_id();

  if (!$user_id) {
    return new WP_Error('not_authenticated', 'No autorizado', ['status' => 401]);
  }

  $first_name = sanitize_text_field((string) $request->get_param('first_name'));
  $last_name = sanitize_text_field((string) $request->get_param('last_name'));

  if ('' === trim($first_name)) {
    return new WP_Error('missing_first_name', 'Nombre obligatorio', ['status' => 400]);
  }

  if ('' === trim($last_name)) {
    return new WP_Error('missing_last_name', 'Apellido obligatorio', ['status' => 400]);
  }

  update_user_meta($user_id, 'first_name', $first_name);
  update_user_meta($user_id, 'last_name', $last_name);
  update_user_meta($user_id, 'smartcards_nombre', $first_name);
  update_user_meta($user_id, 'smartcards_apellido', $last_name);

  $updated_user = wp_update_user([
    'ID' => $user_id,
    'display_name' => trim($first_name . ' ' . $last_name),
  ]);

  if (is_wp_error($updated_user)) {
    return new WP_Error('update_failed', $updated_user->get_error_message(), ['status' => 500]);
  }

  $avatar_url = sc_rest_account_handle_avatar_upload($request, $user_id);

  if (is_wp_error($avatar_url)) {
    return $avatar_url;
  }

  return [
    'success' => true,
    'user' => sc_rest_account_build_user_response($user_id, $avatar_url),
  ];
}

function sc_rest_account_handle_avatar_upload(WP_REST_Request $request, $user_id) {
  $files = $request->get_file_params();

  if (empty($files['avatar']) || empty($files['avatar']['tmp_name'])) {
    $avatar_param = esc_url_raw((string) $request->get_param('avatar'));

    if ($avatar_param) {
      update_user_meta($user_id, 'smartcards_avatar_url', $avatar_param);
      return $avatar_param;
    }

    return sc_rest_account_get_avatar_url($user_id);
  }

  if ((int) $files['avatar']['size'] > 2 * 1024 * 1024) {
    return new WP_Error('avatar_too_large', 'El avatar no puede superar 2 MB.', ['status' => 400]);
  }

  require_once ABSPATH . 'wp-admin/includes/file.php';
  require_once ABSPATH . 'wp-admin/includes/image.php';
  require_once ABSPATH . 'wp-admin/includes/media.php';

  $attachment_id = media_handle_upload('avatar', 0, [
    'post_author' => $user_id,
  ]);

  if (is_wp_error($attachment_id)) {
    return new WP_Error('avatar_upload_failed', $attachment_id->get_error_message(), ['status' => 500]);
  }

  $avatar_url = wp_get_attachment_url($attachment_id);

  if (!$avatar_url) {
    return new WP_Error('avatar_url_failed', 'No se pudo obtener la URL del avatar.', ['status' => 500]);
  }

  update_user_meta($user_id, 'smartcards_avatar_id', (int) $attachment_id);
  update_user_meta($user_id, 'smartcards_foto_perfil_id', (int) $attachment_id);
  update_user_meta($user_id, 'smartcards_avatar_url', esc_url_raw($avatar_url));

  return (string) $avatar_url;
}

function sc_rest_account_delete_account(WP_REST_Request $request) {
  $user_id = get_current_user_id();

  if (!$user_id) {
    return new WP_Error('not_authenticated', 'No autorizado', ['status' => 401]);
  }

  require_once ABSPATH . 'wp-admin/includes/user.php';

  $deleted = wp_delete_user($user_id);

  if (!$deleted) {
    return new WP_Error('delete_failed', 'No se pudo eliminar la cuenta.', ['status' => 500]);
  }

  return [
    'success' => true,
  ];
}

function sc_rest_account_build_user_response($user_id, $avatar_url = '') {
  $user = get_userdata($user_id);
  $first_name = trim((string) get_user_meta($user_id, 'first_name', true));
  $last_name = trim((string) get_user_meta($user_id, 'last_name', true));
  $avatar = $avatar_url ?: sc_rest_account_get_avatar_url($user_id);

  return [
    'id' => (int) $user_id,
    'email' => $user ? (string) $user->user_email : '',
    'display_name' => $user ? (string) $user->display_name : trim($first_name . ' ' . $last_name),
    'name' => $user ? (string) $user->display_name : trim($first_name . ' ' . $last_name),
    'first_name' => $first_name,
    'last_name' => $last_name,
    'avatar' => $avatar,
    'avatar_url' => $avatar,
  ];
}

function sc_rest_account_get_avatar_url($user_id) {
  $avatar_id = (int) get_user_meta($user_id, 'smartcards_avatar_id', true);

  if ($avatar_id <= 0) {
    $avatar_id = (int) get_user_meta($user_id, 'smartcards_foto_perfil_id', true);
  }

  if ($avatar_id > 0) {
    $url = wp_get_attachment_url($avatar_id);

    if ($url) {
      return (string) $url;
    }
  }

  return (string) get_user_meta($user_id, 'smartcards_avatar_url', true);
}
