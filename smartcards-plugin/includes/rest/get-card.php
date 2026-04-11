<?php
/**
 * Endpoint: Obtener Smart Card por ID (precargar wizard)
 */

add_action('rest_api_init', function () {
  register_rest_route(
    'smartcards/v1',
    '/card/(?P<id>\d+)',
    [
      'methods'  => 'GET',
      'callback' => 'sc_get_smartcard',
      'permission_callback' => function () {
        return is_user_logged_in();
      },
    ]
  );
});

/**
 * Callback: devuelve los datos de la Smart Card
 */
function sc_get_smartcard(WP_REST_Request $request) {
  $user_id = get_current_user_id();
  $card_id = (int) $request->get_param('id');

  // 1️⃣ Validar ID
  if (!$card_id) {
    return new WP_Error('invalid_id', 'ID inválido', ['status' => 400]);
  }

  // 2️⃣ Obtener post
  $post = get_post($card_id);

  if (!$post || $post->post_type !== 'smartcards') {
    return new WP_Error('not_found', 'Smart Card no encontrada', ['status' => 404]);
  }

  // 3️⃣ Verificar dueño
  if ((int) $post->post_author !== $user_id) {
    return new WP_Error('forbidden', 'No tienes acceso a esta Smart Card', ['status' => 403]);
  }

  // 4️⃣ Construir respuesta (vacíos por defecto)
  $data = [
    'firstName' => get_post_meta($card_id, 'firstName', true) ?: '',
    'lastName'  => get_post_meta($card_id, 'lastName', true) ?: '',
    'jobTitle'  => get_post_meta($card_id, 'jobTitle', true) ?: '',
    'company'   => get_post_meta($card_id, 'company', true) ?: '',

    'email'     => get_post_meta($card_id, 'email', true) ?: '',
    'phone'     => get_post_meta($card_id, 'phone', true) ?: '',
    'phone2'    => get_post_meta($card_id, 'phone2', true) ?: '',
    'country'   => get_post_meta($card_id, 'country', true) ?: '',
    'city'      => get_post_meta($card_id, 'city', true) ?: '',
    'address'   => get_post_meta($card_id, 'address', true) ?: '',

    'whatsapp'  => get_post_meta($card_id, 'whatsapp', true) ?: '',
    'instagram' => get_post_meta($card_id, 'instagram', true) ?: '',
    'facebook'  => get_post_meta($card_id, 'facebook', true) ?: '',
    'x'         => get_post_meta($card_id, 'x', true) ?: '',
    'tiktok'    => get_post_meta($card_id, 'tiktok', true) ?: '',
    'linkedin'  => get_post_meta($card_id, 'linkedin', true) ?: '',
    'youtube'   => get_post_meta($card_id, 'youtube', true) ?: '',
    'googleMaps'=> get_post_meta($card_id, 'googleMaps', true) ?: '',

    'pay_bold'         => get_post_meta($card_id, 'pay_bold', true) ?: '',
    'pay_wompi'        => get_post_meta($card_id, 'pay_wompi', true) ?: '',
    'pay_epayco'       => get_post_meta($card_id, 'pay_epayco', true) ?: '',
    'pay_paypal'       => get_post_meta($card_id, 'pay_paypal', true) ?: '',
    'pay_stripe'       => get_post_meta($card_id, 'pay_stripe', true) ?: '',
    'pay_mercadopago'  => get_post_meta($card_id, 'pay_mercadopago', true) ?: '',
    'pay_wise'         => get_post_meta($card_id, 'pay_wise', true) ?: '',
    'style'            => [
      'fontName' => sc_clean_font_name((string) get_post_meta($card_id, 'sc_font_family', true)),
    ],
  ];

  return [
    'success' => true,
    'data'    => $data,
  ];
}
