<?php
/**
 * Endpoint REST: Crear Smart Card (BORRADOR)
 * No descuenta créditos
 */

add_action('rest_api_init', function () {
  register_rest_route('smartcards/v1', '/create-card', [
    'methods'  => 'POST',
    'callback' => 'sc_create_smartcard_rest',
    'permission_callback' => function () {
      return get_current_user_id() > 0;
    },
  ]);
});

function sc_create_smartcard_rest(WP_REST_Request $request) {

  $user_id = get_current_user_id();

  if (!$user_id) {
    return new WP_Error('unauthorized', 'No autorizado', ['status' => 401]);
  }

  // Datos mínimos
  $name    = sanitize_text_field($request->get_param('name'));
  $job     = sanitize_text_field($request->get_param('job'));
  $company = sanitize_text_field($request->get_param('company'));

  if (!$name) {
    return new WP_Error('invalid', 'El nombre es obligatorio', ['status' => 400]);
  }

  // Crear Smart Card en borrador
  $post_id = wp_insert_post([
    'post_type' => 'smartcards',
    'post_title'  => $name,
    'post_status' => 'draft', // 👈 CLAVE
    'post_author' => $user_id,
  ], true);

  if (is_wp_error($post_id)) {
    return new WP_Error('create_failed', $post_id->get_error_message(), ['status' => 500]);
  }

  error_log('[SC DEBUG][create_card] post_id: ' . (int) $post_id);

  $created_post = get_post($post_id);
  if (!$created_post instanceof WP_Post) {
    error_log('[SC ERROR] post no existe');
  } else {
    error_log('[SC DEBUG][create_card] post_type: ' . (string) $created_post->post_type);
    error_log('[SC DEBUG][create_card] status: ' . (string) $created_post->post_status);
    error_log('[SC DEBUG][create_card] slug real: ' . (string) get_post_field('post_name', $post_id));
  }

// ==============================
// Guardar meta inicial (APP)
// ==============================

// Separar nombre y apellido desde "name"
$full_name = trim($name);
$parts = preg_split('/\s+/', $full_name, 2);

$firstName = $parts[0] ?? '';
$lastName  = $parts[1] ?? '';

// Guardar metas base alineadas con la App
update_post_meta($post_id, 'firstName', sanitize_text_field($firstName));
update_post_meta($post_id, 'lastName', sanitize_text_field($lastName));
update_post_meta($post_id, 'jobTitle', sanitize_text_field($job));
update_post_meta($post_id, 'company', sanitize_text_field($company));

// Marcar origen para diferenciar APP vs WEB
update_post_meta($post_id, 'sc_source', 'app');

  return [
    'success'     => true,
    'smartcard_id'=> $post_id,
    'status'      => 'draft',
    'message'     => 'Smart Card creada. Completa tu perfil para publicarla.'
  ];
}
