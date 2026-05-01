<?php
if (!defined('ABSPATH')) exit;

/**
 * GET /wp-json/smartcards/v1/card-analytics/{id}
 */
add_action('rest_api_init', function () {
  register_rest_route('smartcards/v1', '/card-analytics/(?P<id>\d+)', [
    'methods'  => 'GET',
    'callback' => 'sc_get_card_analytics_rest',
    'permission_callback' => function () {
      return is_user_logged_in();
    },
    'args' => [
      'id' => [
        'required' => true,
        'validate_callback' => function ($value) {
          return absint($value) > 0;
        },
      ],
    ],
  ]);
});

function sc_card_analytics_button_label($button_key) {
  $labels = [
    'website'         => 'Sitio web',
    'sitio_web'       => 'Sitio web',
    'whatsapp'        => 'WhatsApp',
    'instagram'       => 'Instagram',
    'facebook'        => 'Facebook',
    'x'               => 'X',
    'tiktok'          => 'TikTok',
    'linkedin'        => 'LinkedIn',
    'youtube'         => 'YouTube',
    'google_maps'     => 'Maps',
    'googleMaps'      => 'Maps',
    'pay_wompi'       => 'Wompi',
    'pay_epayco'      => 'ePayco',
    'pay_paypal'      => 'PayPal',
    'pay_bold'        => 'Bold',
    'pay_stripe'      => 'Stripe',
    'pay_mercadopago' => 'Mercado Pago',
    'pay_wise'        => 'Wise',
    'pay_payu'        => 'PayU',
  ];

  return isset($labels[$button_key]) ? $labels[$button_key] : $button_key;
}

function sc_get_card_analytics_rest(WP_REST_Request $request) {
  global $wpdb;

  $user_id = get_current_user_id();
  $card_id = (int) $request->get_param('id');

  if (!$user_id) {
    return new WP_Error('unauthorized', 'No autorizado', ['status' => 401]);
  }

  if (!$card_id) {
    return new WP_Error('invalid_id', 'ID inválido', ['status' => 400]);
  }

  $post = get_post($card_id);
  if (!$post || !in_array($post->post_type, ['smartcards', 'page'], true)) {
    return new WP_Error('not_found', 'Smart Card no encontrada', ['status' => 404]);
  }

  $owner_user_id = (int) get_post_meta($card_id, 'sc_owner_user_id', true);
  if ($owner_user_id <= 0 && $post->post_type === 'smartcards') {
    $owner_user_id = (int) $post->post_author;
  }

  if ($owner_user_id !== (int) $user_id && (int) $post->post_author !== (int) $user_id && !current_user_can('manage_options')) {
    return new WP_Error('forbidden', 'No tienes permisos para ver estas estadísticas', ['status' => 403]);
  }

  $table = $wpdb->prefix . 'smartcards_events';

  $count_event = function ($event_type) use ($wpdb, $table, $card_id) {
    return (int) $wpdb->get_var(
      $wpdb->prepare(
        "SELECT COUNT(*) FROM {$table} WHERE profile_id = %d AND event_type = %s",
        $card_id,
        $event_type
      )
    );
  };

  $buttons_raw = $wpdb->get_results(
    $wpdb->prepare(
      "SELECT button_key, COUNT(*) AS total
       FROM {$table}
       WHERE event_type = %s AND profile_id = %d
       GROUP BY button_key
       ORDER BY total DESC",
      'button_click',
      $card_id
    )
  );
  if (!is_array($buttons_raw)) {
    $buttons_raw = [];
  }

  $buttons = [];
  foreach ($buttons_raw as $row) {
    $key = sanitize_text_field((string) $row->button_key);
    $buttons[] = [
      'name'  => $key ? sc_card_analytics_button_label($key) : 'Sin nombre',
      'key'   => $key,
      'total' => (int) $row->total,
    ];
  }

  return [
    'visits'  => $count_event('profile_view'),
    'saves'   => $count_event('save_contact_click'),
    'clicks'  => $count_event('button_click'),
    'buttons' => $buttons,
  ];
}
