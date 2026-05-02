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
        <option value="active" <?php selected($status, 'active'); ?>>Active</option>
        <option value="coming" <?php selected($status, 'coming'); ?>>Coming</option>
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
  }
}

add_action('rest_api_init', 'sc_register_improvements_rest_route');

if (!function_exists('sc_get_improvements')) {
  function sc_get_improvements() {
    $posts = get_posts([
      'post_type'      => 'sc_improvement',
      'post_status'    => 'publish',
      'posts_per_page' => -1,
      'orderby'        => 'title',
      'order'          => 'ASC',
    ]);

    $improvements = array_map(function ($post) {
      $status = sc_sanitize_improvement_status(get_post_meta($post->ID, 'status', true));

      return [
        'id'          => (int) $post->ID,
        'title'       => get_the_title($post),
        'status'      => $status,
        'days'        => absint(get_post_meta($post->ID, 'days', true)),
        'credits'     => absint(get_post_meta($post->ID, 'credits', true)),
        'description' => (string) get_post_meta($post->ID, 'description', true),
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
        return strcasecmp($a['title'], $b['title']);
      }

      return $status_a <=> $status_b;
    });

    return rest_ensure_response($improvements);
  }
}
