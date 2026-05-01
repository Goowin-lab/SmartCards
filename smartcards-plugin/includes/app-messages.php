<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'SC_MESSAGE_LOGS_DB_VERSION', '1.0.0' );

if ( ! function_exists( 'sc_get_message_logs_table_name' ) ) {
    function sc_get_message_logs_table_name() {
        global $wpdb;
        return $wpdb->prefix . 'sc_message_logs';
    }
}

if ( ! function_exists( 'sc_create_message_logs_table' ) ) {
    function sc_create_message_logs_table() {
        global $wpdb;

        $table_name      = sc_get_message_logs_table_name();
        $charset_collate = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $sql = "CREATE TABLE {$table_name} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            message_id VARCHAR(191) NOT NULL,
            seen TINYINT(1) NOT NULL DEFAULT 0,
            clicked TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY user_message (user_id, message_id),
            KEY message_id (message_id),
            KEY seen (seen),
            KEY clicked (clicked),
            KEY created_at (created_at)
        ) {$charset_collate};";

        dbDelta( $sql );
        update_option( 'sc_message_logs_db_version', SC_MESSAGE_LOGS_DB_VERSION, false );
    }
}

if ( defined( 'SMARTCARDS_PLUGIN_DIR' ) ) {
    register_activation_hook( SMARTCARDS_PLUGIN_DIR . 'smartcards-plugin.php', 'sc_create_message_logs_table' );
}

add_action( 'init', 'sc_maybe_create_message_logs_table' );
function sc_maybe_create_message_logs_table() {
    if ( get_option( 'sc_message_logs_db_version' ) !== SC_MESSAGE_LOGS_DB_VERSION ) {
        sc_create_message_logs_table();
    }
}

function sc_sanitize_message_id( $message_id ) {
    $message_id = sanitize_text_field( (string) $message_id );
    $message_id = preg_replace( '/[^A-Za-z0-9_:\\-.]/', '', $message_id );
    return substr( (string) $message_id, 0, 191 );
}

function sc_log_message_seen( $user_id, $message_id ) {
    global $wpdb;

    $user_id    = absint( $user_id );
    $message_id = sc_sanitize_message_id( $message_id );

    if ( ! $user_id || '' === $message_id ) {
        return false;
    }

    $table_name = sc_get_message_logs_table_name();
    $exists     = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT id FROM {$table_name} WHERE user_id = %d AND message_id = %s",
            $user_id,
            $message_id
        )
    );

    if ( ! $exists ) {
        return (bool) $wpdb->insert(
            $table_name,
            [
                'user_id'    => $user_id,
                'message_id' => $message_id,
                'seen'       => 1,
                'clicked'    => 0,
                'created_at' => current_time( 'mysql' ),
            ],
            [ '%d', '%s', '%d', '%d', '%s' ]
        );
    }

    $wpdb->update(
        $table_name,
        [ 'seen' => 1 ],
        [ 'id' => absint( $exists ) ],
        [ '%d' ],
        [ '%d' ]
    );

    return true;
}

function sc_log_message_clicked( $user_id, $message_id ) {
    global $wpdb;

    $user_id    = absint( $user_id );
    $message_id = sc_sanitize_message_id( $message_id );

    if ( ! $user_id || '' === $message_id ) {
        return false;
    }

    sc_log_message_seen( $user_id, $message_id );

    return (bool) $wpdb->update(
        sc_get_message_logs_table_name(),
        [
            'seen'    => 1,
            'clicked' => 1,
        ],
        [
            'user_id'    => $user_id,
            'message_id' => $message_id,
        ],
        [ '%d', '%d' ],
        [ '%d', '%s' ]
    );
}

if ( ! function_exists( 'sc_normalize_global_messages' ) ) {
    function sc_normalize_global_messages( $messages ) {
        if ( ! is_array( $messages ) ) {
            return [];
        }

        $normalized      = [];
        $allowed_types   = [ 'onboarding', 'info' ];
        $allowed_actions = [ '', 'go_to_cards', 'go_to_create', 'go_to_profile' ];

        foreach ( $messages as $index => $message ) {
            if ( ! is_array( $message ) ) {
                continue;
            }

            $text = isset( $message['text'] ) ? sanitize_textarea_field( $message['text'] ) : '';

            if ( '' === $text ) {
                continue;
            }

            $type = isset( $message['type'] ) ? sanitize_key( $message['type'] ) : 'info';
            if ( ! in_array( $type, $allowed_types, true ) ) {
                $type = 'info';
            }

            $action = isset( $message['action'] ) ? sanitize_key( $message['action'] ) : '';
            if ( ! in_array( $action, $allowed_actions, true ) ) {
                $action = '';
            }

            $expires_at = '';
            if ( ! empty( $message['fecha_expiracion'] ) ) {
                $expires_at = sanitize_text_field( $message['fecha_expiracion'] );
            } elseif ( ! empty( $message['expires_at'] ) ) {
                $expires_at = sanitize_text_field( $message['expires_at'] );
            }

            $message_id = ! empty( $message['id'] )
                ? sc_sanitize_message_id( $message['id'] )
                : sc_sanitize_message_id( 'global-' . ( $index + 1 ) . '-' . substr( md5( $text ), 0, 10 ) );

            $normalized[] = [
                'id'               => $message_id,
                'text'             => $text,
                'type'             => $type,
                'action'           => $action,
                'active'           => ! empty( $message['active'] ),
                'fecha_expiracion' => $expires_at,
            ];
        }

        return $normalized;
    }
}

if ( ! function_exists( 'sc_get_global_messages' ) ) {
    function sc_get_global_messages() {
        return sc_normalize_global_messages( get_option( 'sc_global_messages', [] ) );
    }
}

function sc_message_is_expired( $message ) {
    if ( empty( $message['fecha_expiracion'] ) ) {
        return false;
    }

    $expires_at = strtotime( $message['fecha_expiracion'] . ' 23:59:59' );

    if ( ! $expires_at ) {
        return false;
    }

    return $expires_at < current_time( 'timestamp' );
}

function sc_get_message_log_counts( $message_id ) {
    global $wpdb;

    $message_id = sc_sanitize_message_id( $message_id );

    if ( '' === $message_id ) {
        return [
            'seen'    => 0,
            'clicked' => 0,
        ];
    }

    $table_name = sc_get_message_logs_table_name();
    $row        = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT
                COUNT(CASE WHEN seen = 1 THEN 1 END) AS seen_count,
                COUNT(CASE WHEN clicked = 1 THEN 1 END) AS clicked_count
             FROM {$table_name}
             WHERE message_id = %s",
            $message_id
        ),
        ARRAY_A
    );

    return [
        'seen'    => isset( $row['seen_count'] ) ? (int) $row['seen_count'] : 0,
        'clicked' => isset( $row['clicked_count'] ) ? (int) $row['clicked_count'] : 0,
    ];
}

add_action( 'rest_api_init', 'sc_register_app_messages_routes' );
function sc_register_app_messages_routes() {
    register_rest_route(
        'smartcards/v1',
        '/messages',
        [
            'methods'             => 'GET',
            'callback'            => 'sc_get_active_app_messages',
            'permission_callback' => '__return_true',
        ]
    );

    register_rest_route(
        'smartcards/v1',
        '/message-click',
        [
            'methods'             => 'POST',
            'callback'            => 'sc_message_click_rest',
            'permission_callback' => function () {
                return is_user_logged_in();
            },
        ]
    );

    register_rest_route(
        'smartcards/v1',
        '/message-seen',
        [
            'methods'             => 'POST',
            'callback'            => 'sc_message_seen_rest',
            'permission_callback' => function () {
                return is_user_logged_in();
            },
        ]
    );
}

function sc_get_active_app_messages( WP_REST_Request $request ) {
    $messages = [];
    $user_id  = get_current_user_id();

    foreach ( sc_get_global_messages() as $message ) {
        if ( empty( $message['active'] ) || sc_message_is_expired( $message ) ) {
            continue;
        }

        if ( $user_id ) {
            sc_log_message_seen( $user_id, $message['id'] );
        }

        $messages[] = [
            'id'               => $message['id'],
            'text'             => $message['text'],
            'type'             => $message['type'],
            'action'           => $message['action'],
            'active'           => true,
            'fecha_expiracion' => $message['fecha_expiracion'],
        ];
    }

    return rest_ensure_response( $messages );
}

function sc_message_click_rest( WP_REST_Request $request ) {
    $user_id    = get_current_user_id();
    $message_id = sc_sanitize_message_id( $request->get_param( 'message_id' ) );

    if ( ! $user_id ) {
        return new WP_Error( 'unauthorized', 'No autorizado', [ 'status' => 401 ] );
    }

    if ( '' === $message_id ) {
        return new WP_Error( 'invalid_message', 'message_id es obligatorio', [ 'status' => 400 ] );
    }

    sc_log_message_clicked( $user_id, $message_id );

    return rest_ensure_response(
        [
            'success'    => true,
            'message_id' => $message_id,
        ]
    );
}

function sc_message_seen_rest( WP_REST_Request $request ) {
    $user_id    = get_current_user_id();
    $message_id = sc_sanitize_message_id( $request->get_param( 'message_id' ) );

    if ( ! $user_id ) {
        return new WP_Error( 'unauthorized', 'No autorizado', [ 'status' => 401 ] );
    }

    if ( '' === $message_id ) {
        return new WP_Error( 'invalid_message', 'message_id es obligatorio', [ 'status' => 400 ] );
    }

    sc_log_message_seen( $user_id, $message_id );

    return rest_ensure_response(
        [
            'success'    => true,
            'message_id' => $message_id,
        ]
    );
}

add_action( 'admin_menu', 'sc_register_app_messages_admin_menu' );
function sc_register_app_messages_admin_menu() {
    add_menu_page(
        'Mensajes App',
        'Mensajes App',
        'manage_options',
        'smartcards-app-messages',
        'sc_render_app_messages_admin_page',
        'dashicons-format-chat',
        21
    );
}

function sc_render_app_messages_admin_page() {
    $allowed_types   = [
        'info'       => 'Info',
        'onboarding' => 'Onboarding',
    ];
    $allowed_actions = [
        ''              => 'Sin accion',
        'go_to_cards'   => 'Mis Smart Cards',
        'go_to_create'  => 'Crear Smart Card',
        'go_to_profile' => 'Perfil',
    ];

    if (
        isset( $_POST['sc_save_app_messages'] )
        && check_admin_referer( 'sc_app_messages_nonce', 'sc_app_messages_nonce' )
    ) {
        $ids        = isset( $_POST['message_id'] ) && is_array( $_POST['message_id'] )
            ? wp_unslash( $_POST['message_id'] )
            : [];
        $texts      = isset( $_POST['message_text'] ) && is_array( $_POST['message_text'] )
            ? wp_unslash( $_POST['message_text'] )
            : [];
        $types      = isset( $_POST['message_type'] ) && is_array( $_POST['message_type'] )
            ? wp_unslash( $_POST['message_type'] )
            : [];
        $actions    = isset( $_POST['message_action'] ) && is_array( $_POST['message_action'] )
            ? wp_unslash( $_POST['message_action'] )
            : [];
        $expires_at = isset( $_POST['message_expires_at'] ) && is_array( $_POST['message_expires_at'] )
            ? wp_unslash( $_POST['message_expires_at'] )
            : [];
        $active     = isset( $_POST['message_active'] ) && is_array( $_POST['message_active'] )
            ? wp_unslash( $_POST['message_active'] )
            : [];
        $deleted    = isset( $_POST['message_delete'] ) && is_array( $_POST['message_delete'] )
            ? wp_unslash( $_POST['message_delete'] )
            : [];

        $messages = [];

        foreach ( $texts as $index => $text ) {
            if ( isset( $deleted[ $index ] ) ) {
                continue;
            }

            $text = sanitize_textarea_field( $text );

            if ( '' === $text ) {
                continue;
            }

            $message_id = isset( $ids[ $index ] ) ? sc_sanitize_message_id( $ids[ $index ] ) : '';
            if ( '' === $message_id ) {
                $message_id = sc_sanitize_message_id( 'global-' . substr( wp_generate_uuid4(), 0, 8 ) );
            }

            $type = isset( $types[ $index ] ) ? sanitize_key( $types[ $index ] ) : 'info';
            if ( ! isset( $allowed_types[ $type ] ) ) {
                $type = 'info';
            }

            $action = isset( $actions[ $index ] ) ? sanitize_key( $actions[ $index ] ) : '';
            if ( ! isset( $allowed_actions[ $action ] ) ) {
                $action = '';
            }

            $expiration = isset( $expires_at[ $index ] ) ? sanitize_text_field( $expires_at[ $index ] ) : '';

            $messages[] = [
                'id'               => $message_id,
                'text'             => $text,
                'type'             => $type,
                'action'           => $action,
                'active'           => isset( $active[ $index ] ),
                'fecha_expiracion' => $expiration,
            ];
        }

        update_option( 'sc_global_messages', $messages, false );

        echo '<div class="updated"><p>Mensajes guardados correctamente.</p></div>';
    }

    $messages = sc_get_global_messages();
    $rows     = $messages;
    $rows[]   = [
        'id'               => '',
        'text'             => '',
        'type'             => 'info',
        'action'           => '',
        'active'           => true,
        'fecha_expiracion' => '',
    ];
    ?>
    <div class="wrap">
        <h1>Mensajes App</h1>
        <p>Crea mensajes globales para mostrarlos en el dashboard de la app. El endpoint solo entrega mensajes activos y no vencidos.</p>

        <form method="post">
            <?php wp_nonce_field( 'sc_app_messages_nonce', 'sc_app_messages_nonce' ); ?>

            <table class="widefat fixed striped">
                <thead>
                    <tr>
                        <th>Mensaje</th>
                        <th style="width: 135px;">Tipo</th>
                        <th style="width: 165px;">Accion</th>
                        <th style="width: 150px;">Fecha expiracion</th>
                        <th style="width: 90px;">Activo</th>
                        <th style="width: 130px;">Analiticas</th>
                        <th style="width: 95px;">Eliminar</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $rows as $index => $message ) : ?>
                        <?php
                        $counts = ! empty( $message['id'] )
                            ? sc_get_message_log_counts( $message['id'] )
                            : [ 'seen' => 0, 'clicked' => 0 ];
                        ?>
                        <tr>
                            <td>
                                <input
                                    type="hidden"
                                    name="message_id[<?php echo esc_attr( $index ); ?>]"
                                    value="<?php echo esc_attr( $message['id'] ); ?>"
                                />
                                <textarea
                                    name="message_text[<?php echo esc_attr( $index ); ?>]"
                                    rows="2"
                                    style="width: 100%;"
                                    placeholder="Nueva funcion disponible"
                                ><?php echo esc_textarea( $message['text'] ); ?></textarea>
                                <?php if ( ! empty( $message['id'] ) ) : ?>
                                    <p style="margin: 4px 0 0; color: #666;">
                                        ID: <code><?php echo esc_html( $message['id'] ); ?></code>
                                    </p>
                                <?php endif; ?>
                            </td>
                            <td>
                                <select name="message_type[<?php echo esc_attr( $index ); ?>]">
                                    <?php foreach ( $allowed_types as $type_key => $type_label ) : ?>
                                        <option value="<?php echo esc_attr( $type_key ); ?>" <?php selected( $message['type'], $type_key ); ?>>
                                            <?php echo esc_html( $type_label ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <select name="message_action[<?php echo esc_attr( $index ); ?>]">
                                    <?php foreach ( $allowed_actions as $action_key => $action_label ) : ?>
                                        <option value="<?php echo esc_attr( $action_key ); ?>" <?php selected( $message['action'], $action_key ); ?>>
                                            <?php echo esc_html( $action_label ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <input
                                    type="date"
                                    name="message_expires_at[<?php echo esc_attr( $index ); ?>]"
                                    value="<?php echo esc_attr( $message['fecha_expiracion'] ); ?>"
                                />
                            </td>
                            <td>
                                <label>
                                    <input
                                        type="checkbox"
                                        name="message_active[<?php echo esc_attr( $index ); ?>]"
                                        value="1"
                                        <?php checked( ! empty( $message['active'] ) ); ?>
                                    />
                                    Activo
                                </label>
                            </td>
                            <td>
                                <strong><?php echo esc_html( $counts['seen'] ); ?></strong> vistos<br />
                                <strong><?php echo esc_html( $counts['clicked'] ); ?></strong> clicks
                            </td>
                            <td>
                                <?php if ( '' !== $message['text'] ) : ?>
                                    <label>
                                        <input
                                            type="checkbox"
                                            name="message_delete[<?php echo esc_attr( $index ); ?>]"
                                            value="1"
                                        />
                                        Eliminar
                                    </label>
                                <?php else : ?>
                                    <span style="color: #666;">Nuevo</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <p>
                <input
                    type="submit"
                    name="sc_save_app_messages"
                    class="button button-primary"
                    value="Guardar mensajes"
                />
            </p>
        </form>
    </div>
    <?php
}
