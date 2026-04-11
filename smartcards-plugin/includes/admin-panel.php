<?php
// Asegúrate de que este archivo no sea accedido directamente
defined('ABSPATH') || exit;

// Menú y submenú Créditos Smart Cards
function sc_register_admin_menu() {

    // 📋 Gestión de Créditos Smart Cards (principal)
    add_menu_page(
        'Gestión de Créditos Smart Cards', 
        '💳 Créditos Smart Cards',
        'manage_options', 
        'sc-credits-management', 
        'sc_credits_management_page',
        'dashicons-tickets-alt',
        25
    );

    // 🔑 Submenú Códigos de Crédito
    add_submenu_page(
        'sc-credits-management',
        'Códigos de Crédito',
        '🔑 Códigos de Crédito',
        'manage_options',
        'sc-codigos-credito',
        'sc_codigos_credito_page'
    );

    add_submenu_page(
        'sc-credits-management',
        'Analíticas Smart Cards',
        '📊 Analíticas',
        'manage_options',
        'sc-analytics',
        'sc_analytics_page'
    );
}
add_action('admin_menu', 'sc_register_admin_menu');

// Página del submenú "Códigos de Crédito"
function sc_codigos_credito_page() {
    if (!current_user_can('manage_options')) {
        wp_die('No tienes permisos para acceder aquí.');
    }

    // === Borrado de códigos seleccionados ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['borrar_codigos'])) {
    check_admin_referer('sc_borrar_codigos','sc_borrar_codigos_nonce');
    if (!empty($_POST['delete_codigos']) && is_array($_POST['delete_codigos'])) {
        foreach ($_POST['delete_codigos'] as $codigo) {
            $codigo = sanitize_text_field($codigo);
            delete_option('smart_codigo_credito_' . $codigo);
        }
        echo '<div class="updated notice"><p>✔️ Códigos seleccionados borrados.</p></div>';
    }
}

    // Crear nuevos códigos de crédito
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nuevo_codigo_credito'])) {

        // Protege contra CSRF
    check_admin_referer('sc_nuevo_codigo','sc_nuevo_codigo_nonce');
    
        $codigo = strtoupper(sanitize_text_field($_POST['codigo']));
        $creditos = intval($_POST['creditos']);
        $usos_maximos = intval($_POST['usos_maximos']);

        if ($codigo && $creditos && $usos_maximos) {
            $datos_codigo = [
                'creditos' => $creditos,
                'usos_maximos' => $usos_maximos,
                'usos_actuales' => 0
            ];
            update_option('smart_codigo_credito_' . $codigo, $datos_codigo);

            echo '<div class="updated notice"><p>✔️ Código creado con éxito.</p></div>';
        }
    }

    // HTML del formulario y listado
    ?>
    <div class="wrap">
        <h1>🔑 Códigos de Crédito</h1>

        <form method="get" action="">
    <input type="hidden" name="page" value="sc-codigos-credito" />
    <label for="filter_codigo">Filtrar por código:</label>
    <input type="text" name="filter_codigo" id="filter_codigo" value="<?php echo isset($_GET['filter_codigo']) ? esc_attr($_GET['filter_codigo']) : ''; ?>" />
    <button type="submit" class="button">🔍 Buscar</button>
</form>

        <!-- Formulario -->
        <form method="post">
    <?php wp_nonce_field('sc_nuevo_codigo','sc_nuevo_codigo_nonce'); ?>
    <table class="form-table">
        <tr>
            <th>Código:</th>
            <td><input type="text" name="codigo" required placeholder="Ej: ALFA20"/></td>
        </tr>
        <tr>
            <th>Créditos a otorgar:</th>
            <td><input type="number" name="creditos" required min="1"/></td>
        </tr>
        <tr>
            <th>Usos máximos:</th>
            <td><input type="number" name="usos_maximos" required min="1"/></td>
        </tr>
    </table>
    <input type="submit" name="nuevo_codigo_credito"
           class="button-primary" value="Crear Código"/>
</form>

        <hr/>

        <!-- Listado -->
        <h2>📋 Códigos Activos</h2>
        <?php
        // Definir la paginación
$codigos_por_pagina = 50;
$pagina_actual = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$offset = ($pagina_actual - 1) * $codigos_por_pagina;

$codigo_filtrado = isset($_GET['filter_codigo']) ? strtoupper(trim($_GET['filter_codigo'])) : '';

// Obtener todos los códigos de crédito
$todos_codigos = wp_load_alloptions();
$codigos_credito = [];

foreach ($todos_codigos as $nombre_opcion => $valor) {
    if (strpos($nombre_opcion, 'smart_codigo_credito_') === 0) {
        $codigo = str_replace('smart_codigo_credito_', '', $nombre_opcion);

        if ($codigo_filtrado === '' || $codigo_filtrado === $codigo) {
            $codigos_credito[$codigo] = maybe_unserialize($valor);
        }
    }
}

// Total de códigos
$total_codigos = count($codigos_credito);

// Aplicar paginación
$codigos_credito = array_slice($codigos_credito, $offset, $codigos_por_pagina, true);

        
if ( $codigos_credito ) :

    ?>
    <form method="post">
      <?php wp_nonce_field( 'sc_borrar_codigos', 'sc_borrar_codigos_nonce' ); ?>

      <table class="widefat striped">
        <thead>
          <tr>
            <th style="width:50px; text-align:center;">
              <input type="checkbox" id="sc_select_all_delete" title="Seleccionar todo"/>
            </th>
            <th>Código</th>
            <th>Créditos</th>
            <th>Usos Actuales</th>
            <th>Usos Máximos</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ( $codigos_credito as $codigo_nombre => $data ) : ?>
          <tr>
            <td style="text-align:center;">
              <input type="checkbox"
                     name="delete_codigos[]"
                     value="<?php echo esc_attr( $codigo_nombre ); ?>" />
            </td>
            <td><?php echo esc_html( $codigo_nombre ); ?></td>
            <td><?php echo esc_html( $data['creditos'] );      ?></td>
            <td><?php echo esc_html( $data['usos_actuales'] ); ?></td>
            <td><?php echo esc_html( $data['usos_maximos'] );  ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>

      <p>
        <input type="submit"
               name="borrar_codigos"
               class="button button-secondary"
               value="Borrar códigos seleccionados" />
               </p>
  </form>

  <?php if ( $total_codigos > $codigos_por_pagina ): ?>
    <?php
      $current_url = menu_page_url( 'sc-codigos-credito', false );
      echo paginate_links( array(
        'base'      => add_query_arg( 'paged', '%#%', $current_url ),
        'format'    => '',
        'current'   => $pagina_actual,
        'total'     => ceil( $total_codigos / $codigos_por_pagina ),
        'prev_text' => __( '« Anterior' ),
        'next_text' => __( 'Siguiente »' ),
      ) );
    ?>
  <?php endif; ?>

<?php else: ?>

  <p>No hay códigos aún.</p>

  <?php endif; ?>

<script>
document.getElementById('sc_select_all_delete').addEventListener('change', function(){
    document.querySelectorAll('input[name="delete_codigos[]"]')
        .forEach(ch => ch.checked = this.checked);
});
</script>

</div>
<?php
}



// Funcionalidad para mostrar y gestionar créditos
function sc_credits_management_page() {
    // Verificar permisos
    if (!current_user_can('manage_options')) {
        wp_die('No tienes permisos para acceder a esta página.');
    }
    ?>
  <div class="wrap">
    <h1>Gestión de Créditos Smart Cards</h1>

    <?php
    // 🔎 Buscador AJAX + vista previa de créditos
    if ( function_exists('sc_render_admin_credits_selector') ) {
      sc_render_admin_credits_selector();
    }
    ?>

    <!-- IMPORTANTE: en tus formularios existentes de Asignar/Reiniciar
         agrega/asegura este hidden para que el JS ponga aquí el user_id -->
    <form method="post" class="sc-form-asignar">
      <input type="hidden" name="user_id" value="">
      <!-- ...tu HTML actual de "Asignar Créditos" aquí... -->
    </form>

    <form method="post" class="sc-form-reiniciar">
      <input type="hidden" name="user_id" value="">
      <!-- ...tu HTML actual de "Reiniciar Créditos" aquí... -->
    </form>

    <!-- (Opcional) Tu tabla/resumen puede quedarse debajo tal como está -->
  </div>
  <?php

    // Procesar el formulario para actualizar créditos
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['reset_user_credits']) && isset($_POST['reset_user_id'])) {
            // Reiniciar créditos para un usuario específico
            $reset_user_id = intval($_POST['reset_user_id']);
            update_user_meta($reset_user_id, 'smartcards_credits', 0);
            update_user_meta($reset_user_id, 'smartcards_used_credits', 0);
            echo '<div class="updated"><p>Los créditos del usuario seleccionado han sido reiniciados.</p></div>';
        } elseif (isset($_POST['user_id'], $_POST['credits'])) {
            $user_id = intval($_POST['user_id']);
            $credits = intval($_POST['credits']);

            // Actualizar los créditos en el meta del usuario
            update_user_meta($user_id, 'smartcards_credits', $credits);

            echo '<div class="updated"><p>Créditos actualizados correctamente para el usuario seleccionado.</p></div>';
        }
    }

    // Obtener la página actual para la paginación
    $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $users_per_page = 50;

    // Filtrar por usuario si se seleccionó uno
    $filter_user_id = isset($_GET['filter_user_id']) ? intval($_GET['filter_user_id']) : null;

    // Obtener los usuarios con un límite de 50 por página
    $args = array(
        'number' => $users_per_page,
        'paged' => $paged,
    );

    if ($filter_user_id) {
        $args['include'] = array($filter_user_id);
    }

    $user_query = new WP_User_Query($args);
    $users = $user_query->get_results();
    $total_users = $user_query->get_total();
    $total_pages = ceil($total_users / $users_per_page);

    ?>
    <div class="wrap">
        <h1>Gestión de Créditos Smart Cards</h1>

        <!-- Formulario para asignar créditos -->
        <form method="post" action="">
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Seleccionar Usuario:</th>
                    <td>
                        <select name="user_id" required>
                            <option value="">-- Seleccionar Usuario --</option>
                            <?php foreach ($users as $user) : ?>
                                <option value="<?php echo esc_attr($user->ID); ?>">
                                    <?php echo esc_html($user->display_name . ' (' . $user->user_email . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Asignar Créditos:</th>
                    <td>
                        <input type="number" name="credits" value="0" min="0" required />
                    </td>
                </tr>
            </table>
            <p class="submit">
                <input type="submit" class="button-primary" value="Actualizar Créditos" />
            </p>
        </form>

        <!-- Formulario para reiniciar créditos para un usuario específico -->
        <form method="post" action="" style="margin-top: 20px;">
            <h3>Reiniciar Créditos de un Usuario</h3>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Seleccionar Usuario:</th>
                    <td>
                        <select name="reset_user_id" required>
                            <option value="">-- Seleccionar Usuario --</option>
                            <?php foreach ($users as $user) : ?>
                                <option value="<?php echo esc_attr($user->ID); ?>">
                                    <?php echo esc_html($user->display_name . ' (' . $user->user_email . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <input type="hidden" name="reset_user_credits" value="1" />
                <input type="submit" class="button-secondary" value="Reiniciar Créditos" />
            </p>
        </form>


        <table class="widefat fixed striped">
            <thead>
                <tr>
                    <th>Usuario</th>
                    <th>Correo</th>
                    <th>Créditos Asignados</th>
                    <th>Créditos Usados</th>
                    <th>Archivos VCF Generados</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($users)) : ?>
                    <?php foreach ($users as $user) : ?>
                        <?php
                        $user_id = $user->ID;
                        $total_credits = (int)get_user_meta($user_id, 'smartcards_credits', true);
                        $used_credits = (int)get_user_meta($user_id, 'smartcards_used_credits', true);

                        ?>
                        <tr>
                            <td><?php echo esc_html($user->display_name); ?></td>
                            <td><?php echo esc_html($user->user_email); ?></td>
                            <td><?php echo esc_html($total_credits); ?></td>
                            <td><?php echo esc_html($used_credits); ?></td>
                            <td>
                            <?php
    $vcf_attachment_id = get_user_meta($user_id, 'smartcards_vcf_attachment_id', true);

    if ($vcf_attachment_id) {
        $vcf_url = wp_get_attachment_url($vcf_attachment_id);
        $vcf_title = get_the_title($vcf_attachment_id);
        echo '<a href="' . esc_url($vcf_url) . '" target="_blank">' . esc_html($vcf_title) . '</a>';
    } else {
        echo 'No hay archivos.';
    }
    ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="5">No se encontraron usuarios.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Paginación -->
        <?php
        if ($total_pages > 1) {
            $current_url = menu_page_url('sc-credits-management', false);
            echo paginate_links(array(
                'base' => $current_url . '&paged=%#%',
                'format' => '',
                'current' => $paged,
                'total' => $total_pages,
            ));
        }
        ?>
    </div>
    <?php
}

// Nueva función: "Buscador AJAX instantáneo"
// ==== 1) Cargar assets solo en nuestra pantalla de "Créditos Smart Cards" ====
add_action('admin_enqueue_scripts', function($hook){
  // Cambia 'toplevel_page_sc-creditos' por el slug real de tu página de créditos
  if ($hook !== 'toplevel_page_sc-credits-management') return;

  // Select2 (usa el de WP si tu admin ya lo tiene, si no, CDN):
  wp_enqueue_style( 'select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css', [], '4.1.0' );
  wp_enqueue_script('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', ['jquery'], '4.1.0', true);

  // Nuestro JS de admin
  wp_enqueue_script(
    'sc-admin-credits',
    SMARTCARDS_PLUGIN_URL . 'includes/assets/js/sc-admin-credits.js',
    ['jquery','select2'],
    '1.0.0',
    true
  );
  wp_localize_script('sc-admin-credits', 'SCAdminCredits', [
    'ajax'   => admin_url('admin-ajax.php'),
    'nonce'  => wp_create_nonce('sc_admin_credits_nonce'),
    'i18n'   => [
      'ph'        => 'Buscar por nombre o correo…',
      'loading'   => 'Cargando…',
      'noresults' => 'No se encontraron usuarios',
      'credits'   => 'Créditos asignados',
      'used'      => 'Créditos usados',
      'vcf'       => 'VCF generados',
    ]
  ]);

  // CSS pequeño
  wp_enqueue_style(
    'sc-admin-credits',
    SMARTCARDS_PLUGIN_URL . 'includes/assets/css/sc-admin-credits.css',
    [],
    '1.0.0'
  );
});


// ==== 2) AJAX: Buscar usuarios (para Select2) ====
add_action('wp_ajax_sc_buscar_usuario', function(){
  check_ajax_referer('sc_admin_credits_nonce', 'nonce');

  $term = isset($_GET['term']) ? sanitize_text_field(wp_unslash($_GET['term'])) : '';
  if ($term === '') wp_send_json([]);

  global $wpdb;
  $like = '%' . $wpdb->esc_like($term) . '%';

  $rows = $wpdb->get_results($wpdb->prepare("
    SELECT ID, user_email, display_name
    FROM {$wpdb->users}
    WHERE display_name LIKE %s OR user_email LIKE %s
    ORDER BY user_registered DESC
    LIMIT 20
  ", $like, $like));

  $results = array_map(function($u){
    return [
      'id'   => (int) $u->ID,
      'text' => sprintf('%s (%s)', $u->display_name, $u->user_email)
    ];
  }, $rows ?: []);

  wp_send_json($results);
});


// ==== 3) AJAX: Resumen de créditos del usuario seleccionado ====
add_action('wp_ajax_sc_user_summary', function(){
  check_ajax_referer('sc_admin_credits_nonce', 'nonce');

  $uid = isset($_GET['user_id']) ? absint($_GET['user_id']) : 0;
  if (!$uid || !get_user_by('ID', $uid)) {
    wp_send_json_error(['message' => 'Usuario no válido']);
  }

  // Meta keys (ajusta si usas otros):
  $credits     = (int) get_user_meta($uid, 'smartcards_credits', true);
  $used        = (int) get_user_meta($uid, 'smartcards_credits_used', true);
  $vcf_count   = (int) get_user_meta($uid, 'smartcards_vcf_generated', true);
  $last_update = get_user_meta($uid, 'smartcards_credits_updated_at', true);

  // Puedes sumar otros datos útiles aquí (estado de perfil, etc.)
  $resp = [
    'credits'     => max(0, $credits),
    'used'        => max(0, $used),
    'vcf'         => max(0, $vcf_count),
    'last_update' => $last_update ? date_i18n( get_option('date_format').' '.get_option('time_format'), (int)$last_update ) : '—'
  ];

  wp_send_json_success($resp);
});

// ==== 3.1) AJAX: Buscar perfiles para analíticas (Select2) ====
add_action('wp_ajax_sc_admin_profile_search', 'sc_admin_profile_search');
function sc_admin_profile_search() {
  if ( ! current_user_can('manage_options') ) {
    wp_send_json_error(['message' => 'Permisos insuficientes'], 403);
  }

  $q = isset($_GET['q']) ? sanitize_text_field(wp_unslash($_GET['q'])) : '';
  $q = trim($q);
  if ($q === '') {
    wp_send_json(['results' => []]);
  }

  global $wpdb;
  $like = '%' . $wpdb->esc_like($q) . '%';

  $users_sql = "
    SELECT
      u.ID,
      u.user_email,
      u.display_name,
      (
        SELECT umn.meta_value
        FROM {$wpdb->usermeta} umn
        WHERE umn.user_id = u.ID AND umn.meta_key = 'smartcards_nombre'
        LIMIT 1
      ) AS smart_nombre,
      (
        SELECT uma.meta_value
        FROM {$wpdb->usermeta} uma
        WHERE uma.user_id = u.ID AND uma.meta_key = 'smartcards_apellido'
        LIMIT 1
      ) AS smart_apellido,
      (
        SELECT umc.meta_value
        FROM {$wpdb->usermeta} umc
        WHERE umc.user_id = u.ID AND umc.meta_key = 'smartcards_correo'
        LIMIT 1
      ) AS smart_correo
    FROM {$wpdb->users} u
    WHERE
      u.user_email LIKE %s
      OR u.display_name LIKE %s
      OR EXISTS (
        SELECT 1
        FROM {$wpdb->usermeta} um1
        WHERE um1.user_id = u.ID
          AND um1.meta_key = 'smartcards_nombre'
          AND um1.meta_value LIKE %s
      )
      OR EXISTS (
        SELECT 1
        FROM {$wpdb->usermeta} um2
        WHERE um2.user_id = u.ID
          AND um2.meta_key = 'smartcards_apellido'
          AND um2.meta_value LIKE %s
      )
      OR EXISTS (
        SELECT 1
        FROM {$wpdb->usermeta} um3
        WHERE um3.user_id = u.ID
          AND um3.meta_key = 'smartcards_correo'
          AND um3.meta_value LIKE %s
      )
    ORDER BY u.user_registered DESC
    LIMIT 20
  ";

  $user_rows = $wpdb->get_results(
    $wpdb->prepare($users_sql, $like, $like, $like, $like, $like)
  );

  if (empty($user_rows)) {
    wp_send_json(['results' => []]);
  }

  $users_by_id = [];
  $user_ids = [];
  foreach ($user_rows as $row) {
    $uid = (int) $row->ID;
    $users_by_id[$uid] = $row;
    $user_ids[] = $uid;
  }

  $user_ids = array_values(array_unique($user_ids));
  if (empty($user_ids)) {
    wp_send_json(['results' => []]);
  }

  $placeholders = implode(',', array_fill(0, count($user_ids), '%d'));
  $profiles_sql = "
    SELECT
      p.ID,
      p.post_title,
      CAST(pm.meta_value AS UNSIGNED) AS owner_user_id
    FROM {$wpdb->posts} p
    INNER JOIN {$wpdb->postmeta} pm
      ON pm.post_id = p.ID
      AND pm.meta_key = 'sc_owner_user_id'
    WHERE p.post_type = 'page'
      AND p.post_status = 'publish'
      AND CAST(pm.meta_value AS UNSIGNED) IN ($placeholders)
    ORDER BY p.post_date DESC
    LIMIT 50
  ";

  $profiles = $wpdb->get_results(
    $wpdb->prepare($profiles_sql, $user_ids)
  );

  $results = [];
  foreach ($profiles as $profile) {
    $owner_id = (int) $profile->owner_user_id;
    if (!isset($users_by_id[$owner_id])) {
      continue;
    }

    $owner = $users_by_id[$owner_id];
    $nombre = trim((string) $owner->smart_nombre);
    $apellido = trim((string) $owner->smart_apellido);
    $nombre_apellido = trim($nombre . ' ' . $apellido);
    $display_name = $nombre_apellido !== '' ? $nombre_apellido : (string) $owner->display_name;
    $email = trim((string) $owner->smart_correo);
    if ($email === '') {
      $email = (string) $owner->user_email;
    }

    $results[] = [
      'id' => (int) $profile->ID,
      'text' => sprintf(
        '%s (%s) — Perfil: %s (ID:%d)',
        $display_name,
        $email,
        (string) $profile->post_title,
        (int) $profile->ID
      ),
    ];
  }

  wp_send_json(['results' => $results]);
}

// ==== 3.2) Select2 para la pantalla de Analíticas ====
add_action('admin_enqueue_scripts', function(){

    if (!isset($_GET['page']) || $_GET['page'] !== 'sc-analytics') {
        return;
    }

    // Registrar select2 manualmente
    wp_enqueue_style(
        'smartcards-select2',
        'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css',
        [],
        '4.1.0'
    );

    wp_enqueue_script(
        'smartcards-select2',
        'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js',
        ['jquery'],
        '4.1.0',
        true
    );
});


// ==== 4) HTML: Reemplaza el selector grande por este bloque en tu pantalla de créditos ====
function sc_render_admin_credits_selector(){
  ?>
  <div class="sc-credits-toolbar">
    <label for="sc-user-select"><strong>Seleccionar Usuario:</strong></label>
    <select id="sc-user-select" style="width:480px" data-placeholder="Buscar por nombre o correo…"></select>
    <input type="hidden" id="sc-user-id" name="sc_user_id" value="">
  </div>

  <div id="sc-user-preview" class="sc-user-preview" aria-live="polite" hidden>
    <div class="sc-user-preview__row">
      <div><span><?php echo esc_html__('Créditos asignados','smartcards'); ?>:</span> <strong id="sc-prev-credits">—</strong></div>
      <div><span><?php echo esc_html__('Créditos usados','smartcards'); ?>:</span> <strong id="sc-prev-used">—</strong></div>
      <div><span><?php echo esc_html__('VCF generados','smartcards'); ?>:</span> <strong id="sc-prev-vcf">—</strong></div>
      <div><span><?php echo esc_html__('Última actualización','smartcards'); ?>:</span> <strong id="sc-prev-upd">—</strong></div>
      <div class="sc-user-actions" hidden>
    <label for="sc-edit-credits"><strong>Editar créditos:</strong></label>
    <input type="number" id="sc-edit-credits" min="0" step="1" value="0" />
    <button type="button" class="button button-primary" id="sc-btn-save-credits">Guardar</button>
    <button type="button" class="button" id="sc-btn-reset-used">Reiniciar usados</button>
    <span class="sc-user-actions__msg" aria-live="polite"></span>
  </div>
    </div>

  <script>
    // Si tus formularios de "Actualizar Créditos" o "Reiniciar Créditos"
    // necesitan el user_id, solo lee #sc-user-id en el submit del form existente.
  </script>
  <?php
}

// === Actualizar créditos (setear valor) ===
add_action('wp_ajax_sc_update_credits', function () {
  if ( ! current_user_can('manage_options') ) wp_send_json_error(['message'=>'Permisos insuficientes']);
  check_ajax_referer('sc_admin_credits_nonce', 'nonce');

  $uid     = isset($_POST['user_id']) ? absint($_POST['user_id']) : 0;
  $credits = isset($_POST['credits']) ? max(0, intval($_POST['credits'])) : null;
  if ( ! $uid || ! get_user_by('ID', $uid) ) wp_send_json_error(['message'=>'Usuario no válido']);
  if ( $credits === null ) wp_send_json_error(['message'=>'Valor de créditos inválido']);

  update_user_meta($uid, 'smartcards_credits', $credits);
  update_user_meta($uid, 'smartcards_credits_updated_at', time());

  $resp = [
    'credits' => (int) get_user_meta($uid, 'smartcards_credits', true),
    'used'    => (int) get_user_meta($uid, 'smartcards_credits_used', true),
    'vcf'     => (int) get_user_meta($uid, 'smartcards_vcf_generated', true),
    'last_update' => date_i18n(get_option('date_format').' '.get_option('time_format'), time()),
    'msg' => 'Créditos actualizados'
  ];
  wp_send_json_success($resp);
});

// === Reiniciar créditos usados (y opcionalmente créditos a 0) ===
add_action('wp_ajax_sc_reset_credits', function () {
  if ( ! current_user_can('manage_options') ) wp_send_json_error(['message'=>'Permisos insuficientes']);
  check_ajax_referer('sc_admin_credits_nonce', 'nonce');

  $uid = isset($_POST['user_id']) ? absint($_POST['user_id']) : 0;
  if ( ! $uid || ! get_user_by('ID', $uid) ) wp_send_json_error(['message'=>'Usuario no válido']);

  // Reset usados a 0; si quieres también poner créditos en 0, descomenta la línea siguiente:
  update_user_meta($uid, 'smartcards_credits_used', 0);
  // update_user_meta($uid, 'smartcards_credits', 0);
  update_user_meta($uid, 'smartcards_credits_updated_at', time());

  $resp = [
    'credits' => (int) get_user_meta($uid, 'smartcards_credits', true),
    'used'    => 0,
    'vcf'     => (int) get_user_meta($uid, 'smartcards_vcf_generated', true),
    'last_update' => date_i18n(get_option('date_format').' '.get_option('time_format'), time()),
    'msg' => 'Usados reiniciados'
  ];
  wp_send_json_success($resp);
});

function sc_analytics_page() {
  if ( ! current_user_can('manage_options') ) {
    wp_die('No tienes permisos para acceder a esta página.');
  }

  global $wpdb;
  $table = $wpdb->prefix . 'smartcards_events';

  $profile_id = isset($_GET['profile_id']) ? absint($_GET['profile_id']) : 0;
  $date_from  = isset($_GET['date_from']) ? sanitize_text_field(wp_unslash($_GET['date_from'])) : '';
  $date_to    = isset($_GET['date_to']) ? sanitize_text_field(wp_unslash($_GET['date_to'])) : '';

  $valid_from = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from) ? $date_from : '';
  $valid_to   = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to) ? $date_to : '';
  $select_available = wp_script_is('selectWoo','enqueued') || wp_script_is('selectWoo','registered') || wp_script_is('select2','enqueued') || wp_script_is('select2','registered');

  $selected_profile_text = '';
  if ( $profile_id > 0 ) {
    $post_title = get_the_title($profile_id);
    if ( $post_title ) {
      $owner_user_id = (int) get_post_meta($profile_id, 'sc_owner_user_id', true);
      $owner_label = '';
      if ( $owner_user_id > 0 ) {
        $owner = get_userdata($owner_user_id);
        if ( $owner ) {
          $owner_label = $owner->display_name . ' (' . $owner->user_email . ')';
        }
      }
      if ( $owner_label === '' ) {
        $owner_label = __('Usuario desconocido', 'smartcards');
      }
      $selected_profile_text = sprintf(
        '%s — Perfil: %s (ID:%d)',
        $owner_label,
        $post_title,
        $profile_id
      );
    }
  }

  $where = [];
  $args  = [];

  if ( $profile_id > 0 ) {
    $where[] = 'profile_id = %d';
    $args[]  = $profile_id;
  }
  if ( $valid_from ) {
    $where[] = 'created_at >= %s';
    $args[]  = $valid_from . ' 00:00:00';
  }
  if ( $valid_to ) {
    $where[] = 'created_at <= %s';
    $args[]  = $valid_to . ' 23:59:59';
  }

  $where_sql = $where ? ' AND ' . implode(' AND ', $where) : '';

  ?>
  <div class="wrap">
    <h1>📊 Analíticas Smart Cards</h1>

    <form method="get" style="margin: 12px 0 18px;">
      <input type="hidden" name="page" value="sc-analytics" />
      <?php if ( $select_available ) : ?>
        <label for="sc_profile_search" style="margin-right:8px;">Perfil:</label>
        <select id="sc_profile_search" style="min-width:420px">
          <?php if ( $profile_id > 0 && $selected_profile_text !== '' ) : ?>
            <option value="<?php echo esc_attr( (string) $profile_id ); ?>" selected>
              <?php echo esc_html( $selected_profile_text ); ?>
            </option>
          <?php endif; ?>
        </select>
        <input type="hidden" name="profile_id" id="sc_profile_id" value="<?php echo esc_attr( $profile_id ? (string) $profile_id : '' ); ?>" />
      <?php else : ?>
        <input type="text" name="profile_search_text" placeholder="Buscar por nombre, apellido o correo…" value="<?php echo isset($_GET['profile_search_text']) ? esc_attr( sanitize_text_field( wp_unslash( $_GET['profile_search_text'] ) ) ) : ''; ?>" />
        <button type="submit" class="button">Buscar</button>
        <input type="number" name="profile_id" id="sc_profile_id" placeholder="ID de página del perfil" value="<?php echo esc_attr( $profile_id ? (string) $profile_id : '' ); ?>" />
      <?php endif; ?>
      <input type="date" name="date_from" value="<?php echo esc_attr( $valid_from ? $valid_from : $date_from ); ?>" />
      <input type="date" name="date_to" value="<?php echo esc_attr( $valid_to ? $valid_to : $date_to ); ?>" />
      <button type="submit" class="button button-primary">Filtrar</button>
    </form>

    <?php if ( $profile_id > 0 ) : ?>
      <?php
      $count_event = function( $event_type ) use ( $wpdb, $table, $where_sql, $args ) {
        $sql = "SELECT COUNT(*) FROM {$table} WHERE event_type = %s{$where_sql}";
        $q_args = array_merge( [ $event_type ], $args );
        return (int) $wpdb->get_var( $wpdb->prepare( $sql, $q_args ) );
      };

      $total_views        = $count_event('profile_view');
      $total_save_contact = $count_event('save_contact_click');
      $total_button_click = $count_event('button_click');

      $buttons_sql = "SELECT button_key, COUNT(*) AS total
                      FROM {$table}
                      WHERE event_type = %s{$where_sql}
                      GROUP BY button_key
                      ORDER BY total DESC";
      $buttons_args = array_merge( [ 'button_click' ], $args );
      $button_rows = $wpdb->get_results( $wpdb->prepare( $buttons_sql, $buttons_args ) );

      $social_keys = [ 'sitio_web', 'whatsapp', 'instagram', 'x', 'youtube', 'facebook', 'tiktok', 'linkedin', 'google_maps' ];
      $payment_keys = [ 'paypal' ];
      $labels = [
        'sitio_web' => 'Sitio web',
        'google_maps' => 'Maps',
        'linkedin' => 'LinkedIn',
        'tiktok' => 'TikTok',
        'x' => 'X',
      ];
      $social_rows = [];
      $payment_rows = [];
      foreach ( $button_rows as $row ) {
        $key = sanitize_key( (string) $row->button_key );
        if ( in_array( $key, $social_keys, true ) ) {
          $social_rows[] = $row;
        }
        if ( in_array( $key, $payment_keys, true ) ) {
          $payment_rows[] = $row;
        }
      }
      ?>

      <div style="display:flex; gap:12px; margin: 8px 0 16px; flex-wrap:wrap;">
        <div style="background:#fff;border:1px solid #dcdcde;padding:12px 16px;min-width:180px;">
          <strong>Visitas</strong><br><span style="font-size:24px;"><?php echo esc_html( $total_views ); ?></span>
        </div>
        <div style="background:#fff;border:1px solid #dcdcde;padding:12px 16px;min-width:180px;">
          <strong>Guardar contacto</strong><br><span style="font-size:24px;"><?php echo esc_html( $total_save_contact ); ?></span>
        </div>
        <div style="background:#fff;border:1px solid #dcdcde;padding:12px 16px;min-width:180px;">
          <strong>Clicks botones</strong><br><span style="font-size:24px;"><?php echo esc_html( $total_button_click ); ?></span>
        </div>
      </div>

      <h2>Redes y enlaces</h2>
      <table class="widefat striped">
        <thead>
          <tr>
            <th>Botón</th>
            <th>Total</th>
          </tr>
        </thead>
        <tbody>
          <?php if ( ! empty( $social_rows ) ) : ?>
            <?php foreach ( $social_rows as $row ) : ?>
              <?php
              $key = sanitize_key( (string) $row->button_key );
              $label = isset( $labels[ $key ] ) ? $labels[ $key ] : $row->button_key;
              ?>
              <tr>
                <td><?php echo esc_html( $label ? $label : '—' ); ?></td>
                <td><?php echo esc_html( (int) $row->total ); ?></td>
              </tr>
            <?php endforeach; ?>
          <?php else : ?>
            <tr><td colspan="2">No hay datos para este filtro.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>

      <h2 style="margin-top:18px;">Botones de pago</h2>
      <table class="widefat striped">
        <thead>
          <tr>
            <th>Botón</th>
            <th>Total</th>
          </tr>
        </thead>
        <tbody>
          <?php if ( ! empty( $payment_rows ) ) : ?>
            <?php foreach ( $payment_rows as $row ) : ?>
              <?php
              $key = sanitize_key( (string) $row->button_key );
              $label = isset( $labels[ $key ] ) ? $labels[ $key ] : $row->button_key;
              ?>
              <tr>
                <td><?php echo esc_html( $label ? $label : '—' ); ?></td>
                <td><?php echo esc_html( (int) $row->total ); ?></td>
              </tr>
            <?php endforeach; ?>
          <?php else : ?>
            <tr><td colspan="2">No hay datos para este filtro.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>

      <h2>Botones por clicks</h2>
      <table class="widefat striped">
        <thead>
          <tr>
            <th>Botón</th>
            <th>Total</th>
          </tr>
        </thead>
        <tbody>
          <?php if ( ! empty( $button_rows ) ) : ?>
            <?php foreach ( $button_rows as $row ) : ?>
              <tr>
                <td><?php echo esc_html( $row->button_key ? $row->button_key : '—' ); ?></td>
                <td><?php echo esc_html( (int) $row->total ); ?></td>
              </tr>
            <?php endforeach; ?>
          <?php else : ?>
            <tr><td colspan="2">No hay datos para este filtro.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>

    <?php else : ?>
      <?php
      $summary_where = [];
      $summary_args  = [];
      if ( $valid_from ) {
        $summary_where[] = 'created_at >= %s';
        $summary_args[]  = $valid_from . ' 00:00:00';
      }
      if ( $valid_to ) {
        $summary_where[] = 'created_at <= %s';
        $summary_args[]  = $valid_to . ' 23:59:59';
      }
      $summary_where_sql = $summary_where ? ' AND ' . implode(' AND ', $summary_where) : '';

      $top_profiles_sql = "SELECT profile_id, COUNT(*) AS total
                           FROM {$table}
                           WHERE event_type = %s{$summary_where_sql}
                           GROUP BY profile_id
                           ORDER BY total DESC
                           LIMIT 100";
      $top_profiles_args = array_merge( [ 'profile_view' ], $summary_args );
      $top_profiles = $wpdb->get_results( $wpdb->prepare( $top_profiles_sql, $top_profiles_args ) );

      $top_buttons_sql = "SELECT button_key, COUNT(*) AS total
                          FROM {$table}
                          WHERE event_type = %s{$summary_where_sql}
                          GROUP BY button_key
                          ORDER BY total DESC
                          LIMIT 100";
      $top_buttons_args = array_merge( [ 'button_click' ], $summary_args );
      $top_buttons = $wpdb->get_results( $wpdb->prepare( $top_buttons_sql, $top_buttons_args ) );
      ?>

      <h2>Top 100 perfiles por visitas</h2>
      <table class="widefat striped">
        <thead>
          <tr>
            <th>Perfil</th>
            <th>Dueño</th>
            <th>Total</th>
          </tr>
        </thead>
        <tbody>
          <?php if ( ! empty( $top_profiles ) ) : ?>
            <?php foreach ( $top_profiles as $row ) : ?>
              <?php
              $pid = (int) $row->profile_id;
              $post = get_post( $pid );
              ?>
              <tr>
                <td>
                  <?php if ( $post ) : ?>
                    <a href="<?php echo esc_url( admin_url('post.php?post=' . $pid . '&action=edit') ); ?>">
                      <?php echo esc_html( get_the_title( $pid ) ); ?>
                    </a>
                    <small>(ID: <?php echo esc_html( $pid ); ?>)</small>
                  <?php else : ?>
                    ID: <?php echo esc_html( $pid ); ?>
                  <?php endif; ?>
                </td>
                <td>
                  <?php
                  $owner_user_id = (int) get_post_meta( $pid, 'sc_owner_user_id', true );
                  if ( $owner_user_id > 0 ) {
                    $owner = get_userdata( $owner_user_id );
                    if ( $owner ) {
                      echo esc_html( $owner->display_name . ' (' . $owner->user_email . ')' );
                    } else {
                      echo esc_html( 'ID: ' . $owner_user_id );
                    }
                  } else {
                    echo '—';
                  }
                  ?>
                </td>
                <td><?php echo esc_html( (int) $row->total ); ?></td>
              </tr>
            <?php endforeach; ?>
          <?php else : ?>
            <tr><td colspan="3">No hay datos para este filtro.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>

      <h2 style="margin-top:18px;">Top 100 botones globales</h2>
      <table class="widefat striped">
        <thead>
          <tr>
            <th>Botón</th>
            <th>Total</th>
          </tr>
        </thead>
        <tbody>
          <?php if ( ! empty( $top_buttons ) ) : ?>
            <?php foreach ( $top_buttons as $row ) : ?>
              <tr>
                <td><?php echo esc_html( $row->button_key ? $row->button_key : '—' ); ?></td>
                <td><?php echo esc_html( (int) $row->total ); ?></td>
              </tr>
            <?php endforeach; ?>
          <?php else : ?>
            <tr><td colspan="2">No hay datos para este filtro.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    <?php endif; ?>

    <?php if ( $select_available ) : ?>
      <script>
        jQuery(function($){
          var $select = $('#sc_profile_search');
          var $hidden = $('#sc_profile_id');
          if (!$select.length) return;
          if (typeof $.fn.select2 !== 'function') {
            return;
          }

          $select.select2({
            placeholder: 'Buscar por nombre, apellido o correo…',
            allowClear: true,
            minimumInputLength: 1,
            ajax: {
              url: <?php echo wp_json_encode( admin_url('admin-ajax.php') ); ?>,
              dataType: 'json',
              delay: 250,
              data: function(params){
                return {
                  action: 'sc_admin_profile_search',
                  q: params.term || ''
                };
              },
              processResults: function(data){
                return { results: (data && data.results) ? data.results : [] };
              },
              cache: true
            }
          });

          $select.on('select2:select', function(e){
            var selected = (e && e.params && e.params.data) ? e.params.data : null;
            $hidden.val(selected && selected.id ? selected.id : '');
          });

          $select.on('select2:clear', function(){
            $hidden.val('');
          });
        });
      </script>
    <?php endif; ?>
  </div>
  <?php
}
