<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'sc_normalize_global_messages' ) ) {
    function sc_normalize_global_messages( $messages ) {
        if ( ! is_array( $messages ) ) {
            return [];
        }

        $normalized = [];

        foreach ( $messages as $message ) {
            if ( ! is_array( $message ) ) {
                continue;
            }

            $text = isset( $message['text'] ) ? sanitize_textarea_field( $message['text'] ) : '';

            if ( '' === $text ) {
                continue;
            }

            $normalized[] = [
                'text'   => $text,
                'active' => ! empty( $message['active'] ),
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

add_action( 'rest_api_init', 'sc_register_app_messages_route' );
function sc_register_app_messages_route() {
    register_rest_route(
        'smartcards/v1',
        '/messages',
        [
            'methods'             => 'GET',
            'callback'            => 'sc_get_active_app_messages',
            'permission_callback' => '__return_true',
        ]
    );
}

function sc_get_active_app_messages( WP_REST_Request $request ) {
    $messages = [];

    foreach ( sc_get_global_messages() as $index => $message ) {
        if ( empty( $message['active'] ) ) {
            continue;
        }

        $text       = (string) $message['text'];
        $messages[] = [
            'id'     => 'global-' . ( $index + 1 ) . '-' . substr( md5( $text ), 0, 10 ),
            'text'   => $text,
            'active' => true,
        ];
    }

    return rest_ensure_response( $messages );
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
    if (
        isset( $_POST['sc_save_app_messages'] )
        && check_admin_referer( 'sc_app_messages_nonce', 'sc_app_messages_nonce' )
    ) {
        $texts   = isset( $_POST['message_text'] ) && is_array( $_POST['message_text'] )
            ? wp_unslash( $_POST['message_text'] )
            : [];
        $active  = isset( $_POST['message_active'] ) && is_array( $_POST['message_active'] )
            ? wp_unslash( $_POST['message_active'] )
            : [];
        $deleted = isset( $_POST['message_delete'] ) && is_array( $_POST['message_delete'] )
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

            $messages[] = [
                'text'   => $text,
                'active' => isset( $active[ $index ] ),
            ];
        }

        update_option( 'sc_global_messages', $messages, false );

        echo '<div class="updated"><p>Mensajes guardados correctamente.</p></div>';
    }

    $messages = sc_get_global_messages();
    $rows     = $messages;
    $rows[]   = [
        'text'   => '',
        'active' => true,
    ];
    ?>
    <div class="wrap">
        <h1>Mensajes App</h1>
        <p>Crea mensajes globales para mostrarlos en el dashboard de la app. El endpoint solo entrega los mensajes activos.</p>

        <form method="post">
            <?php wp_nonce_field( 'sc_app_messages_nonce', 'sc_app_messages_nonce' ); ?>

            <table class="widefat fixed striped">
                <thead>
                    <tr>
                        <th>Mensaje</th>
                        <th style="width: 110px;">Activo</th>
                        <th style="width: 110px;">Eliminar</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $rows as $index => $message ) : ?>
                        <tr>
                            <td>
                                <textarea
                                    name="message_text[<?php echo esc_attr( $index ); ?>]"
                                    rows="2"
                                    style="width: 100%;"
                                    placeholder="🚀 Nueva función disponible"
                                ><?php echo esc_textarea( $message['text'] ); ?></textarea>
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
