<?php
if (!defined('ABSPATH')) exit;

/**
 * POST /wp-json/smartcards/v1/upload-image
 * multipart/form-data:
 * - smartcard_id (int)
 * - kind: avatar | cover | vcf
 * - file (image)
 */
add_action('rest_api_init', function () {
  register_rest_route('smartcards/v1', '/upload-image', [
    'methods'  => 'POST',
    'callback' => 'sc_upload_image_rest',
    'permission_callback' => function () {
      return is_user_logged_in();
    },
  ]);
});

function sc_upload_image_rest(WP_REST_Request $request) {
  $user_id     = get_current_user_id();
  $smartcard_id = (int) $request->get_param('smartcard_id');
  $kind        = sanitize_text_field((string) $request->get_param('kind'));

  if (!$smartcard_id || !$kind) {
    return new WP_Error('invalid', 'Faltan parámetros', ['status' => 400]);
  }

  $post = get_post($smartcard_id);
  if (!$post || $post->post_type !== 'smartcards') {
    return new WP_Error('not_found', 'Smart Card no encontrada', ['status' => 404]);
  }

  if ((int) $post->post_author !== (int) $user_id) {
    return new WP_Error('forbidden', 'No tienes acceso a esta Smart Card', ['status' => 403]);
  }

  // Solo permitimos subir imágenes en draft (recomendado)
  if ($post->post_status !== 'draft') {
    return new WP_Error('invalid_state', 'Solo puedes subir imágenes mientras el perfil está en borrador', ['status' => 400]);
  }

  $files = $request->get_file_params();
  if (empty($files['file']) || empty($files['file']['tmp_name'])) {
    return new WP_Error('no_file', 'No llegó el archivo', ['status' => 400]);
  }

  $allowed = ['avatar', 'cover', 'vcf'];
  if (!in_array($kind, $allowed, true)) {
    return new WP_Error('invalid_kind', 'Tipo de imagen inválido', ['status' => 400]);
  }

  require_once ABSPATH . 'wp-admin/includes/file.php';
  require_once ABSPATH . 'wp-admin/includes/image.php';
  require_once ABSPATH . 'wp-admin/includes/media.php';

  // Sube a la biblioteca y crea attachment
  $attachment_id = media_handle_upload('file', $smartcard_id);

  if (is_wp_error($attachment_id)) {
    return new WP_Error('upload_failed', $attachment_id->get_error_message(), ['status' => 500]);
  }

  // Guardar meta según tipo
  if ($kind === 'avatar') {
    update_post_meta($smartcard_id, 'sc_avatar_id', (int) $attachment_id);
    set_post_thumbnail($smartcard_id, $attachment_id); // Foto de perfil como featured image
  } elseif ($kind === 'cover') {
    update_post_meta($smartcard_id, 'sc_cover_id', (int) $attachment_id);
  } elseif ($kind === 'vcf') {
    update_post_meta($smartcard_id, 'sc_vcf_photo_id', (int) $attachment_id);
  }

  $url = wp_get_attachment_url($attachment_id);

  return [
    'success' => true,
    'attachment_id' => (int) $attachment_id,
    'url' => (string) $url,
    'kind' => $kind,
  ];
}
