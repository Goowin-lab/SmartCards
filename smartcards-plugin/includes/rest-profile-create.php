<?php
if ( ! defined('ABSPATH') ) exit;

add_action('rest_api_init', function () {
  register_rest_route('smartcards/v1', '/profile/create', [
    'methods'  => 'POST',
    'callback' => 'sc_rest_create_profile',
    'permission_callback' => function () {
      return is_user_logged_in(); // JWT ya debe setear el usuario
    }
  ]);
});

function sc_rest_create_profile(WP_REST_Request $req){
  $user_id = get_current_user_id();
  if (!$user_id) {
    return new WP_REST_Response(['message' => 'No autenticado'], 401);
  }

  // ✅ Créditos
  $credits = (int) get_user_meta($user_id, 'smartcards_credits', true);
  if ($credits <= 0) {
    return new WP_REST_Response(['message' => 'No tienes créditos disponibles'], 403);
  }

  // ✅ Recibe campos (multipart/form-data)
  $p = $req->get_params(); // textos
  $files = $req->get_file_params(); // imagen, portada, imagen_vcf

  // ⛔ Requeridas
  if (empty($files['imagen']['name'])) {
    return new WP_REST_Response(['message' => 'Debes subir la foto de perfil'], 400);
  }
  if (empty($files['portada']['name'])) {
    return new WP_REST_Response(['message' => 'Debes subir la foto de portada'], 400);
  }

  // ✅ Aquí: reutiliza tu lógica actual
  // (Recomendación: extraer de procesar_formulario() a una función compartida)
  // Por ahora, lo más rápido: copia la parte de:
  // 1) guardar metas
  // 2) generar vcf
  // 3) subir adjuntos (perfil/portada)
  // 4) crear contenido + wp_insert_post()
  //
  // Y al final:

  // ✔ Restar crédito SOLO si todo sale bien
  // update_user_meta($user_id, 'smartcards_credits', $credits - 1);

  return new WP_REST_Response([
    'ok' => true,
    'perfil_url' => $url_perfil,
    'vcf_url' => $vcf_url
  ], 200);
}
