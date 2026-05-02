<?php
/**
 * Post Type: Smart Cards
 */

if (!function_exists('sc_register_smartcards_post_type')) {
  function sc_register_smartcards_post_type() {
    $labels = [
      'name'                  => 'Smart Cards',
      'singular_name'         => 'Smart Card',
      'menu_name'             => 'Smart Cards',
      'name_admin_bar'        => 'Smart Card',
      'add_new'               => 'Añadir nueva',
      'add_new_item'          => 'Añadir Smart Card',
      'new_item'              => 'Nueva Smart Card',
      'edit_item'             => 'Editar Smart Card',
      'view_item'             => 'Ver Smart Card',
      'all_items'             => 'Todas las Smart Cards',
      'search_items'          => 'Buscar Smart Cards',
      'not_found'             => 'No se encontraron Smart Cards',
      'not_found_in_trash'    => 'No hay Smart Cards en la papelera',
      'featured_image'        => 'Imagen destacada',
      'set_featured_image'    => 'Asignar imagen destacada',
      'remove_featured_image' => 'Quitar imagen destacada',
      'use_featured_image'    => 'Usar como imagen destacada',
    ];

    register_post_type('smartcards', [
      'labels'             => $labels,
      'public'             => true,
      'publicly_queryable' => true,
      'show_ui'            => true,
      'show_in_menu'       => true,
      'show_in_rest'       => true,
      'has_archive'        => false,
      'rewrite'            => [
        'slug'       => 'card',
        'with_front' => false,
      ],
      'supports'           => ['title', 'editor', 'thumbnail'],
      'menu_icon'          => 'dashicons-id',
    ]);
  }
}

add_action('init', 'sc_register_smartcards_post_type');

/**
 * Post Type: Mejoras Pro
 */
if (!function_exists('sc_register_improvements_post_type')) {
  function sc_register_improvements_post_type() {
    $labels = [
      'name'               => 'Mejoras Pro',
      'singular_name'      => 'Mejora Pro',
      'menu_name'          => 'Mejoras Pro',
      'name_admin_bar'     => 'Mejora Pro',
      'add_new'            => 'Añadir nueva',
      'add_new_item'       => 'Añadir Mejora Pro',
      'new_item'           => 'Nueva Mejora Pro',
      'edit_item'          => 'Editar Mejora Pro',
      'view_item'          => 'Ver Mejora Pro',
      'all_items'          => 'Todas las Mejoras Pro',
      'search_items'       => 'Buscar Mejoras Pro',
      'not_found'          => 'No se encontraron Mejoras Pro',
      'not_found_in_trash' => 'No hay Mejoras Pro en la papelera',
    ];

    register_post_type('sc_improvement', [
      'labels'        => $labels,
      'public'        => false,
      'show_ui'       => true,
      'show_in_menu'  => true,
      'show_in_rest'  => true,
      'has_archive'   => false,
      'supports'      => ['title'],
      'menu_icon'     => 'dashicons-star-filled',
    ]);
  }
}

add_action('init', 'sc_register_improvements_post_type');

if (!function_exists('sc_sanitize_improvement_status')) {
  function sc_sanitize_improvement_status($status) {
    $status = sanitize_key((string) $status);

    return in_array($status, ['active', 'coming'], true) ? $status : 'coming';
  }
}

if (!function_exists('sc_normalize_improvement_user_votes')) {
  function sc_normalize_improvement_user_votes($votes) {
    if (is_string($votes)) {
      $decoded_votes = json_decode($votes, true);
      $votes = is_array($decoded_votes) ? $decoded_votes : explode(',', $votes);
    }

    if (!is_array($votes)) {
      return [];
    }

    $user_ids = [];
    foreach ($votes as $user_id) {
      $user_id = absint($user_id);

      if ($user_id > 0) {
        $user_ids[] = $user_id;
      }
    }

    return array_values(array_unique($user_ids));
  }
}

if (!function_exists('sc_register_improvement_meta')) {
  function sc_register_improvement_meta() {
    register_post_meta('sc_improvement', 'status', [
      'single'            => true,
      'type'              => 'string',
      'default'           => 'coming',
      'sanitize_callback' => 'sc_sanitize_improvement_status',
      'show_in_rest'      => [
        'schema' => [
          'type' => 'string',
          'enum' => ['active', 'coming'],
        ],
      ],
    ]);

    register_post_meta('sc_improvement', 'days', [
      'single'            => true,
      'type'              => 'integer',
      'default'           => 0,
      'sanitize_callback' => 'absint',
      'show_in_rest'      => true,
    ]);

    register_post_meta('sc_improvement', 'credits', [
      'single'            => true,
      'type'              => 'integer',
      'default'           => 0,
      'sanitize_callback' => 'absint',
      'show_in_rest'      => true,
    ]);

    register_post_meta('sc_improvement', 'description', [
      'single'            => true,
      'type'              => 'string',
      'default'           => '',
      'sanitize_callback' => 'sanitize_textarea_field',
      'show_in_rest'      => true,
    ]);

    register_post_meta('sc_improvement', 'votes', [
      'single'            => true,
      'type'              => 'integer',
      'default'           => 0,
      'sanitize_callback' => 'absint',
      'show_in_rest'      => true,
    ]);

    register_post_meta('sc_improvement', 'user_votes', [
      'single'            => true,
      'type'              => 'array',
      'default'           => [],
      'sanitize_callback' => 'sc_normalize_improvement_user_votes',
      'show_in_rest'      => [
        'schema' => [
          'type'  => 'array',
          'items' => [
            'type' => 'integer',
          ],
        ],
      ],
    ]);
  }
}

add_action('init', 'sc_register_improvement_meta');

if (!function_exists('sc_add_improvement_metabox')) {
  function sc_add_improvement_metabox() {
    add_meta_box(
      'sc_improvement_details',
      'Datos de la mejora',
      'sc_render_improvement_metabox',
      'sc_improvement',
      'normal',
      'high'
    );
  }
}

add_action('add_meta_boxes', 'sc_add_improvement_metabox');

if (!function_exists('sc_render_improvement_metabox')) {
  function sc_render_improvement_metabox($post) {
    $status = sc_sanitize_improvement_status(get_post_meta($post->ID, 'status', true));
    $days = absint(get_post_meta($post->ID, 'days', true));
    $credits = absint(get_post_meta($post->ID, 'credits', true));
    $description = (string) get_post_meta($post->ID, 'description', true);

    wp_nonce_field('sc_save_improvement_meta', 'sc_improvement_meta_nonce');
    ?>
    <p>
      <label for="sc_improvement_status"><strong>Estado</strong></label><br>
      <select id="sc_improvement_status" name="sc_improvement_status">
        <option value="active" <?php selected($status, 'active'); ?>>Activo</option>
        <option value="coming" <?php selected($status, 'coming'); ?>>Próximamente</option>
      </select>
    </p>

    <p>
      <label for="sc_improvement_days"><strong>Días</strong></label><br>
      <input id="sc_improvement_days" name="sc_improvement_days" type="number" min="0" step="1" value="<?php echo esc_attr($days); ?>" class="small-text">
    </p>

    <p>
      <label for="sc_improvement_credits"><strong>Créditos</strong></label><br>
      <input id="sc_improvement_credits" name="sc_improvement_credits" type="number" min="0" step="1" value="<?php echo esc_attr($credits); ?>" class="small-text">
    </p>

    <p>
      <label for="sc_improvement_description"><strong>Descripción</strong></label><br>
      <textarea id="sc_improvement_description" name="sc_improvement_description" rows="4" class="large-text"><?php echo esc_textarea($description); ?></textarea>
    </p>
    <?php
  }
}

if (!function_exists('sc_save_improvement_meta')) {
  function sc_save_improvement_meta($post_id) {
    if (!isset($_POST['sc_improvement_meta_nonce'])) {
      return;
    }

    $nonce = sanitize_text_field(wp_unslash($_POST['sc_improvement_meta_nonce']));
    if (!wp_verify_nonce($nonce, 'sc_save_improvement_meta')) {
      return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
      return;
    }

    if (!current_user_can('edit_post', $post_id)) {
      return;
    }

    $status = isset($_POST['sc_improvement_status'])
      ? sc_sanitize_improvement_status(wp_unslash($_POST['sc_improvement_status']))
      : 'coming';
    $days = isset($_POST['sc_improvement_days'])
      ? absint(wp_unslash($_POST['sc_improvement_days']))
      : 0;
    $credits = isset($_POST['sc_improvement_credits'])
      ? absint(wp_unslash($_POST['sc_improvement_credits']))
      : 0;
    $description = isset($_POST['sc_improvement_description'])
      ? sanitize_textarea_field(wp_unslash($_POST['sc_improvement_description']))
      : '';

    update_post_meta($post_id, 'status', $status);
    update_post_meta($post_id, 'days', $days);
    update_post_meta($post_id, 'credits', $credits);
    update_post_meta($post_id, 'description', $description);
  }
}

add_action('save_post_sc_improvement', 'sc_save_improvement_meta');

if (!function_exists('sc_register_improvements_rest_route')) {
  function sc_register_improvements_rest_route() {
    register_rest_route('smartcards/v1', '/improvements', [
      'methods'             => WP_REST_Server::READABLE,
      'callback'            => 'sc_get_improvements',
      'permission_callback' => '__return_true',
    ]);

    register_rest_route('smartcards/v1', '/suggest-improvement', [
      'methods'             => WP_REST_Server::CREATABLE,
      'callback'            => 'sc_suggest_improvement',
      'permission_callback' => '__return_true',
    ]);

    register_rest_route('smartcards/v1', '/vote-improvement', [
      'methods'             => WP_REST_Server::CREATABLE,
      'callback'            => 'sc_vote_improvement',
      'permission_callback' => '__return_true',
    ]);

    register_rest_route('smartcards/v1', '/activate-improvement', [
      'methods'             => WP_REST_Server::CREATABLE,
      'callback'            => 'sc_activate_improvement',
      'permission_callback' => '__return_true',
    ]);
  }
}

add_action('rest_api_init', 'sc_register_improvements_rest_route');

if (!function_exists('sc_get_improvements')) {
  function sc_get_improvements($request = null) {
    $viewer_id = 0;
    if ($request instanceof WP_REST_Request) {
      $viewer_id = absint($request->get_param('user_id'));
    }

    $posts = get_posts([
      'post_type'      => 'sc_improvement',
      'post_status'    => 'publish',
      'posts_per_page' => -1,
      'orderby'        => 'title',
      'order'          => 'ASC',
    ]);

    $improvements = array_map(function ($post) use ($viewer_id) {
      $status = sc_sanitize_improvement_status(get_post_meta($post->ID, 'status', true));
      $votes = absint(get_post_meta($post->ID, 'votes', true));
      $user_votes = sc_normalize_improvement_user_votes(get_post_meta($post->ID, 'user_votes', true));

      return [
        'id'          => (int) $post->ID,
        'title'       => get_the_title($post),
        'status'      => $status,
        'days'        => absint(get_post_meta($post->ID, 'days', true)),
        'credits'     => absint(get_post_meta($post->ID, 'credits', true)),
        'description' => (string) get_post_meta($post->ID, 'description', true),
        'votes'       => $votes,
        'voted'       => $viewer_id > 0 && in_array($viewer_id, $user_votes, true),
      ];
    }, $posts);

    usort($improvements, function ($a, $b) {
      $order = [
        'active' => 0,
        'coming' => 1,
      ];

      $status_a = $order[$a['status']] ?? 99;
      $status_b = $order[$b['status']] ?? 99;

      if ($status_a === $status_b) {
        if ($a['votes'] !== $b['votes']) {
          return $b['votes'] <=> $a['votes'];
        }

        return strcasecmp($a['title'], $b['title']);
      }

      return $status_a <=> $status_b;
    });

    return rest_ensure_response($improvements);
  }
}

if (!function_exists('sc_suggest_improvement')) {
  function sc_suggest_improvement(WP_REST_Request $request) {
    $idea = sanitize_text_field((string) $request->get_param('idea'));

    if ($idea === '') {
      return new WP_Error(
        'smartcards_empty_idea',
        'La idea no puede estar vacía.',
        ['status' => 400]
      );
    }

    $post_id = wp_insert_post([
      'post_type'   => 'sc_improvement',
      'post_status' => 'draft',
      'post_title'  => $idea,
    ], true);

    if (is_wp_error($post_id)) {
      return $post_id;
    }

    update_post_meta($post_id, 'status', 'coming');
    update_post_meta($post_id, 'days', 0);
    update_post_meta($post_id, 'credits', 0);
    update_post_meta($post_id, 'description', '');
    update_post_meta($post_id, 'votes', 0);
    update_post_meta($post_id, 'user_votes', []);

    return rest_ensure_response([
      'success' => true,
      'id'      => (int) $post_id,
      'message' => 'Gracias, estamos evaluando tu idea.',
    ]);
  }
}

if (!function_exists('sc_vote_improvement')) {
  function sc_vote_improvement(WP_REST_Request $request) {
    $post_id = absint($request->get_param('id'));
    $user_id = absint($request->get_param('user_id'));
    $current_user_id = get_current_user_id();

    if ($current_user_id > 0 && $user_id > 0 && $current_user_id !== $user_id) {
      return new WP_Error(
        'smartcards_vote_user_mismatch',
        'No puedes votar con otro usuario.',
        ['status' => 403]
      );
    }

    if ($user_id <= 0) {
      $user_id = absint($current_user_id);
    }

    if ($post_id <= 0 || $user_id <= 0) {
      return new WP_Error(
        'smartcards_invalid_vote',
        'Faltan datos para registrar el voto.',
        ['status' => 400]
      );
    }

    $post = get_post($post_id);
    if (!$post || $post->post_type !== 'sc_improvement' || $post->post_status !== 'publish') {
      return new WP_Error(
        'smartcards_improvement_not_found',
        'La mejora no está disponible para votar.',
        ['status' => 404]
      );
    }

    $user_votes = sc_normalize_improvement_user_votes(get_post_meta($post_id, 'user_votes', true));
    $votes = absint(get_post_meta($post_id, 'votes', true));

    if (in_array($user_id, $user_votes, true)) {
      return new WP_Error(
        'smartcards_already_voted',
        'Ya votaste por esta idea.',
        [
          'status' => 409,
          'votes'  => $votes,
        ]
      );
    }

    $user_votes[] = $user_id;
    $votes = max($votes + 1, count($user_votes));

    update_post_meta($post_id, 'user_votes', $user_votes);
    update_post_meta($post_id, 'votes', $votes);

    return rest_ensure_response([
      'success' => true,
      'id'      => $post_id,
      'votes'   => $votes,
      'voted'   => true,
    ]);
  }
}

if (!function_exists('sc_activate_improvement')) {
  function sc_activate_improvement(WP_REST_Request $request) {
    $user_id = get_current_user_id();

    if (!$user_id) {
      return new WP_Error(
        'smartcards_unauthorized',
        'Debes iniciar sesión para activar esta mejora.',
        ['status' => 401]
      );
    }

    $improvement = sanitize_key((string) $request->get_param('improvement'));

    if ($improvement !== 'qr_logo') {
      return new WP_Error(
        'smartcards_invalid_improvement',
        'Mejora no disponible.',
        ['status' => 400]
      );
    }

    $qr_logo_enabled = (bool) get_user_meta($user_id, 'qr_logo_enabled', true);
    $credits = (int) get_user_meta($user_id, 'smartcards_credits', true);

    if ($qr_logo_enabled) {
      return rest_ensure_response([
        'success'         => true,
        'qr_logo_enabled' => true,
        'credits'         => $credits,
      ]);
    }

    if ($credits < 1) {
      return new WP_Error(
        'smartcards_no_credits',
        'No tienes créditos suficientes para activar esta mejora.',
        ['status' => 400]
      );
    }

    $new_credits = max(0, $credits - 1);

    update_user_meta($user_id, 'smartcards_credits', $new_credits);
    update_user_meta($user_id, 'smartcards_credits_updated', current_time('mysql'));
    update_user_meta($user_id, 'smartcards_credits_updated_at', time());
    update_user_meta($user_id, 'qr_logo_enabled', true);

    return rest_ensure_response([
      'success'         => true,
      'qr_logo_enabled' => true,
      'credits'         => $new_credits,
    ]);
  }
}

if (!function_exists('sc_add_improvement_votes_column')) {
  function sc_add_improvement_votes_column($columns) {
    $next_columns = [];

    foreach ($columns as $key => $label) {
      if ($key === 'date') {
        $next_columns['votes'] = 'Votos';
      }

      $next_columns[$key] = $label;
    }

    if (!isset($next_columns['votes'])) {
      $next_columns['votes'] = 'Votos';
    }

    return $next_columns;
  }
}

add_filter('manage_sc_improvement_posts_columns', 'sc_add_improvement_votes_column');

if (!function_exists('sc_render_improvement_votes_column')) {
  function sc_render_improvement_votes_column($column, $post_id) {
    if ($column === 'votes') {
      echo esc_html(absint(get_post_meta($post_id, 'votes', true)));
    }
  }
}

add_action('manage_sc_improvement_posts_custom_column', 'sc_render_improvement_votes_column', 10, 2);

if (!function_exists('sc_make_improvement_votes_column_sortable')) {
  function sc_make_improvement_votes_column_sortable($columns) {
    $columns['votes'] = 'votes';

    return $columns;
  }
}

add_filter('manage_edit-sc_improvement_sortable_columns', 'sc_make_improvement_votes_column_sortable');

if (!function_exists('sc_sort_improvement_admin_by_votes')) {
  function sc_sort_improvement_admin_by_votes($query) {
    if (!is_admin() || !$query->is_main_query()) {
      return;
    }

    if ($query->get('post_type') !== 'sc_improvement' || $query->get('orderby') !== 'votes') {
      return;
    }

    $query->set('meta_key', 'votes');
    $query->set('orderby', 'meta_value_num');
  }
}

add_action('pre_get_posts', 'sc_sort_improvement_admin_by_votes');
