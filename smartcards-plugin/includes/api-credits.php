<?php
add_action('rest_api_init', function () {

  // GET /smartcards/v1/credits/<id|me>
  register_rest_route('smartcards/v1', '/credits/(?P<id>[\d]+|me)', [
    'methods'  => 'GET',
    'callback' => function (WP_REST_Request $req) {
      $user = wp_get_current_user(); // poblado por el plugin JWT si envías Bearer
      if (!$user || 0 === $user->ID) {
        return new WP_REST_Response(['message' => 'Unauthorized'], 401);
      }

      $paramId = $req->get_param('id');
      $targetId = ($paramId === 'me') ? $user->ID : intval($paramId);

      // Si no soy admin/manager, solo puedo ver "me"
      if ($targetId !== $user->ID && !current_user_can('manage_options') && !current_user_can('manage_woocommerce')) {
        return new WP_REST_Response(['message' => 'Forbidden'], 403);
      }

      $credits = (int) get_user_meta($targetId, 'smartcards_credits', true);
      $used    = (int) get_user_meta($targetId, 'smartcards_credits_used', true);
      $updated = get_user_meta($targetId, 'smartcards_credits_updated', true);

      return [
        'user_id'    => $targetId,
        'credits'    => $credits,
        'used'       => $used,
        'updated_at' => $updated ?: null,
      ];
    },
    // durante pruebas puedes usar '__return_true' y luego endurecer
    'permission_callback' => '__return_true',
  ]);

  // POST /smartcards/v1/credits/<id>  body: { op: set|add|sub, value: number }
  register_rest_route('smartcards/v1', '/credits/(?P<id>\d+)', [
    'methods'  => 'POST',
    'callback' => function (WP_REST_Request $req) {
      $user = wp_get_current_user();
      if (!$user || 0 === $user->ID) {
        return new WP_REST_Response(['message' => 'Unauthorized'], 401);
      }

      // Solo roles autorizados
      if ( !current_user_can('manage_options') && !current_user_can('manage_woocommerce') ) {
        return new WP_REST_Response(['message' => 'Forbidden'], 403);
      }

      $targetId = intval($req->get_param('id'));
      $op    = sanitize_text_field($req->get_param('op'));
      $value = (int) $req->get_param('value');

      if (!in_array($op, ['set','add','sub'], true)) {
        return new WP_REST_Response(['message' => 'Invalid op'], 400);
      }
      if ($value < 0) {
        return new WP_REST_Response(['message' => 'Value must be >= 0'], 400);
      }

      $current = (int) get_user_meta($targetId, 'smartcards_credits', true);

      if ($op === 'set') $new = $value;
      if ($op === 'add') $new = $current + $value;
      if ($op === 'sub') $new = max(0, $current - $value);

      update_user_meta($targetId, 'smartcards_credits', $new);
      update_user_meta($targetId, 'smartcards_credits_updated', current_time('mysql'));

      $used = (int) get_user_meta($targetId, 'smartcards_credits_used', true);

      return [
        'user_id'    => $targetId,
        'credits'    => (int) $new,
        'used'       => (int) $used,
        'updated_at' => get_user_meta($targetId, 'smartcards_credits_updated', true),
      ];
    },
    // endurecido: requiere que el JWT se resuelva a un usuario con capacidad
    'permission_callback' => function () {
      return ( current_user_can('manage_options') || current_user_can('manage_woocommerce') );
    },
  ]);

});
