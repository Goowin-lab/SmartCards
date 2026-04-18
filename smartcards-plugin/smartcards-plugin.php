<?php
/**
 * Plugin Name: SmartCards
 * Plugin URI: https://goowin.co
 * Description: Formulario para generar archivos VCF, crea el perfil de contacto con la foto de la portada, foto del perfil, botón de guardar contacto, redes sociales, QR Dinámico y aprobación de perfil, optimización Créditos Smart Cards, notificaciones a los editores, Mis smart cards. Productos en el dashboard, mis smarts cards, ajustes, in-app purchases.
 * Version: 3.0.14
 * Author: Goowin
 * Author URI: https://goowin.co
 * Text Domain: smartcards
 * Domain Path: /languages
 */

// Asegurarse de que no se acceda directamente al archivo.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Crear el rol personalizado "customer" (Cliente)
function sc_add_customer_role() {
    add_role(
        'customer', // Nombre interno del rol
        'Cliente',  // Nombre visible en el admin
        array(
            'read' => true,       // Puede leer contenido
            'edit_posts' => false, // No puede editar posts
            'delete_posts' => false // No puede borrar posts
        )
    );
}
register_activation_hook( __FILE__, 'sc_add_customer_role' );

// Cambiar el rol por defecto al activar el plugin
function sc_set_default_role_to_customer() {
    update_option( 'default_role', 'customer' );
}
register_activation_hook( __FILE__, 'sc_set_default_role_to_customer' );

/**
 * Crear tabla para eventos de analíticas.
 */
function sc_create_events_table() {
    global $wpdb;

    $table_name      = $wpdb->prefix . 'smartcards_events';
    $charset_collate = $wpdb->get_charset_collate();

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $sql = "CREATE TABLE {$table_name} (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      profile_id BIGINT UNSIGNED NOT NULL,
      owner_user_id BIGINT UNSIGNED NOT NULL,
      event_type VARCHAR(32) NOT NULL,
      button_key VARCHAR(64) NULL,
      url TEXT NULL,
      ip_hash CHAR(64) NULL,
      ua_hash CHAR(64) NULL,
      created_at DATETIME NOT NULL,
      PRIMARY KEY  (id),
      KEY profile_event (profile_id, event_type),
      KEY owner_user (owner_user_id),
      KEY created_at (created_at)
    ) {$charset_collate};";

    dbDelta( $sql );
}
register_activation_hook( __FILE__, 'sc_create_events_table' );

// Cambiar el rol al registrar usuarios manualmente o programáticamente
function sc_register_user_as_customer( $user_id ) {
    $user = new WP_User( $user_id );
    $user->set_role( 'customer' );
}
add_action( 'user_register', 'sc_register_user_as_customer' );

function sc_login_redirect( $redirect_to, $request, $user ) {
    // Verifica si el usuario tiene el rol 'customer'
    if ( isset( $user->roles ) && in_array( 'customer', $user->roles ) ) {
        return home_url( '/dashboard/' ); // Cambia esta URL por la de tu Dashboard
    }
    return $redirect_to; // Si no cumple, redirige al destino predeterminado
}
add_filter( 'login_redirect', 'sc_login_redirect', 10, 3 );

// Definir constantes
define('SMARTCARDS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SMARTCARDS_PLUGIN_URL', plugin_dir_url(__FILE__));

global $sc_fonts;
$sc_fonts = [
    'Inter',
    'Roboto',
    'Poppins',
    'Open Sans',
    'Montserrat',
    'Lato',
    'Nunito',
    'Source Sans Pro',
    'Raleway',
    'Ubuntu',
    'Work Sans',
    'DM Sans',
    'Outfit',
    'Plus Jakarta Sans',
    'Rubik',
    'Mulish',
    'Quicksand',
    'Manrope',
    'Merriweather',
    'Playfair Display',
    'Libre Baskerville',
    'Lora',
    'Cormorant Garamond',
    'Pacifico',
    'Dancing Script',
    'Great Vibes',
    'Bebas Neue',
    'Oswald',
    'Bangers',
];

if ( ! function_exists( 'sc_get_available_fonts' ) ) {
    function sc_get_available_fonts() {
        global $sc_fonts;

        return is_array( $sc_fonts ) ? $sc_fonts : [ 'Montserrat' ];
    }
}

if ( ! function_exists( 'sc_clean_font_name' ) ) {
    function sc_clean_font_name( $font_raw ) {
        $font_raw = trim( (string) $font_raw );
        $font_key = strtolower( preg_replace( '/[^a-z0-9]+/', '', $font_raw ) );

        foreach ( sc_get_available_fonts() as $font ) {
            if ( stripos( $font_raw, $font ) !== false ) {
                return $font;
            }

            $normalized_font = strtolower( preg_replace( '/[^a-z0-9]+/', '', $font ) );
            if ( $normalized_font !== '' && $font_key !== '' && strpos( $font_key, $normalized_font ) !== false ) {
                return $font;
            }
        }

        return 'Montserrat';
    }
}

if ( ! function_exists( 'sc_sanitize_font_family_name' ) ) {
    function sc_sanitize_font_family_name( $font ) {
        return sc_clean_font_name( $font );
    }
}

if ( ! function_exists( 'sc_format_google_font' ) ) {
    function sc_format_google_font( $font ) {
        return str_replace( ' ', '+', trim( sc_clean_font_name( $font ) ) );
    }
}

if ( ! function_exists( 'sc_get_font_stack' ) ) {
    function sc_get_font_stack( $font ) {
        $font = sc_clean_font_name( $font );

        return '"' . addcslashes( $font, "\\\"" ) . '", "Montserrat", sans-serif';
    }
}

if ( ! function_exists( 'sc_hex_to_contrast_text' ) ) {
    function sc_hex_to_contrast_text( $hex ) {
        $hex = sanitize_hex_color( (string) $hex );
        if ( ! $hex ) {
            return '#ffffff';
        }

        $hex = ltrim( $hex, '#' );
        if ( strlen( $hex ) === 3 ) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        $r = hexdec( substr( $hex, 0, 2 ) );
        $g = hexdec( substr( $hex, 2, 2 ) );
        $b = hexdec( substr( $hex, 4, 2 ) );
        $luminance = ( 0.299 * $r ) + ( 0.587 * $g ) + ( 0.114 * $b );

        return $luminance > 186 ? '#000000' : '#ffffff';
    }
}

if ( ! function_exists( 'sc_get_google_font_weights' ) ) {
    function sc_get_google_font_weights( $font ) {
        $font = sc_clean_font_name( $font );

        $font_weights = [
            'Inter' => '300;400;600;700',
            'Roboto' => '300;400;600;700',
            'Poppins' => '300;400;600;700',
            'Open Sans' => '300;400;600;700',
            'Montserrat' => '300;400;600;700',
            'Lato' => '300;400;700',
            'Nunito' => '300;400;600;700',
            'Source Sans Pro' => '300;400;600;700',
            'Raleway' => '300;400;600;700',
            'Ubuntu' => '300;400;700',
            'Work Sans' => '300;400;600;700',
            'DM Sans' => '300;400;600;700',
            'Outfit' => '300;400;600;700',
            'Plus Jakarta Sans' => '300;400;600;700',
            'Rubik' => '300;400;600;700',
            'Mulish' => '300;400;600;700',
            'Quicksand' => '300;400;600;700',
            'Manrope' => '300;400;600;700',
            'Merriweather' => '300;400;700',
            'Playfair Display' => '400;600;700',
            'Libre Baskerville' => '400;700',
            'Lora' => '400;600;700',
            'Cormorant Garamond' => '300;400;600;700',
            'Pacifico' => '',
            'Dancing Script' => '400;600;700',
            'Great Vibes' => '',
            'Bebas Neue' => '',
            'Oswald' => '300;400;600;700',
            'Bangers' => '',
        ];

        return isset( $font_weights[ $font ] ) ? $font_weights[ $font ] : '300;400;600;700';
    }
}

if ( ! function_exists( 'sc_get_google_font_url' ) ) {
    function sc_get_google_font_url( $font ) {
        $font_name = sc_format_google_font( $font );
        $weights   = sc_get_google_font_weights( $font );

        if ( $weights === '' ) {
            return 'https://fonts.googleapis.com/css2?family=' . $font_name . '&display=swap';
        }

        return 'https://fonts.googleapis.com/css2?family=' . $font_name . ':wght@' . $weights . '&display=swap';
    }
}

if ( ! function_exists( 'sc_style_pick_value' ) ) {
    function sc_style_pick_value( $style, $keys ) {
        if ( ! is_array( $style ) ) {
            return '';
        }

        foreach ( (array) $keys as $key ) {
            if ( ! array_key_exists( $key, $style ) ) {
                continue;
            }

            $value = trim( (string) $style[ $key ] );
            if ( $value !== '' ) {
                return $value;
            }
        }

        return '';
    }
}

if ( ! function_exists( 'sc_style_has_any_key' ) ) {
    function sc_style_has_any_key( $style, $keys ) {
        if ( ! is_array( $style ) ) {
            return false;
        }

        foreach ( (array) $keys as $key ) {
            if ( array_key_exists( $key, $style ) ) {
                return true;
            }
        }

        return false;
    }
}

if ( ! function_exists( 'sc_get_profile_style_settings' ) ) {
    function sc_get_profile_style_settings( $post_id, $theme = '' ) {
        $post_id = (int) $post_id;
        $theme   = sanitize_key( (string) $theme );

        $legacy_color_raw = (string) get_post_meta( $post_id, 'sc_user_color', true );
        $primary_color_raw = (string) get_post_meta( $post_id, 'sc_color_primary', true );
        $role_color_raw    = (string) get_post_meta( $post_id, 'sc_color_role', true );
        $button_color_raw  = (string) get_post_meta( $post_id, 'sc_color_button', true );
        $social_color_raw  = (string) get_post_meta( $post_id, 'sc_color_redes', true );

        $primary_color = sanitize_hex_color( $primary_color_raw ? $primary_color_raw : $legacy_color_raw );
        if ( ! $primary_color ) {
            $primary_color = '#01a350';
        }

        $role_color = sanitize_hex_color( $role_color_raw );
        if ( ! $role_color ) {
            $role_color = $primary_color;
        }

        $button_color = sanitize_hex_color( $button_color_raw );
        if ( ! $button_color ) {
            $button_color = $primary_color;
        }

        $social_color = sanitize_hex_color( $social_color_raw );
        if ( ! $social_color ) {
            $social_color = $primary_color;
        }

        $legacy_font_raw = (string) get_post_meta( $post_id, 'sc_font_family', true );
        $name_font_raw   = (string) get_post_meta( $post_id, 'sc_font_name', true );
        $role_font_raw   = (string) get_post_meta( $post_id, 'sc_font_role', true );

        $base_font_source = $name_font_raw !== '' ? $name_font_raw : $legacy_font_raw;
        if ( $base_font_source === '' ) {
            $base_font_source = 'Montserrat';
        }

        $base_font = sc_clean_font_name( $base_font_source );
        $name_font = sc_clean_font_name( $name_font_raw !== '' ? $name_font_raw : $base_font_source );

        $role_font_source = $role_font_raw !== '' ? $role_font_raw : ( $legacy_font_raw !== '' ? $legacy_font_raw : $name_font );
        $role_font = sc_clean_font_name( $role_font_source );

        $font_urls = [];
        foreach ( array_unique( array_filter( [ $base_font, $name_font, $role_font ] ) ) as $font_name ) {
            $font_urls[] = sc_get_google_font_url( $font_name );
        }

        return [
            'theme'               => $theme,
            'primary_color'       => $primary_color,
            'role_color'          => $role_color,
            'button_color'        => $button_color,
            'social_color'        => $social_color,
            'primary_text_color'  => sc_hex_to_contrast_text( $primary_color ),
            'button_text_color'   => sc_hex_to_contrast_text( $button_color ),
            'base_font'           => $base_font,
            'name_font'           => $name_font,
            'role_font'           => $role_font,
            'base_font_css'       => sc_get_font_stack( $base_font ),
            'name_font_css'       => sc_get_font_stack( $name_font ),
            'role_font_css'       => sc_get_font_stack( $role_font ),
            'font_urls'           => $font_urls,
        ];
    }
}

if ( ! function_exists( 'sc_get_social_icon_fallback_svg' ) ) {
    function sc_get_social_icon_fallback_svg( $network_key ) {
        $raw_key = strtolower( trim( (string) $network_key ) );
        if ( function_exists( 'remove_accents' ) ) {
            $raw_key = remove_accents( $raw_key );
        }
        $raw_key = preg_replace( '/\.svg$/i', '', $raw_key );
        $key = str_replace( [ '-', ' ' ], '_', $raw_key );
        $key = preg_replace( '/_+/', '_', $key );
        $key = trim( $key, '_' );

        $icons = [
            'whatsapp'          => [
                'viewBox' => '0 0 500 500',
                'body'    => '<path fill="currentColor" d="M116.72,383.6l19.16-71.48c-10.85-19.57-16.55-41.5-16.55-63.76,0-72.77,59.2-131.97,131.97-131.97s131.98,59.2,131.98,131.97-59.2,131.98-131.98,131.98c-21.85,0-43.42-5.52-62.73-15.99l-71.84,19.25ZM192.14,337.57l4.52,2.7c16.61,9.9,35.5,15.13,54.64,15.13,59.01,0,107.03-48.01,107.03-107.03s-48.01-107.03-107.03-107.03-107.03,48.01-107.03,107.03c0,19.49,5.41,38.67,15.65,55.49l2.78,4.56-10.69,39.91,40.13-10.75Z"/><path fill="currentColor" d="M300.39,266.73c-5.43-3.25-12.5-6.88-18.9-4.26-4.91,2.01-8.04,9.69-11.22,13.62-1.63,2.01-3.58,2.33-6.09,1.32-18.43-7.34-32.55-19.64-42.72-36.6-1.72-2.64-1.41-4.71.66-7.15,3.07-3.62,6.93-7.72,7.76-12.6.83-4.87-1.46-10.57-3.47-14.91-2.58-5.55-5.45-13.46-11.01-16.59-5.11-2.89-11.84-1.27-16.39,2.44-7.85,6.4-11.64,16.42-11.53,26.35.03,2.82.38,5.64,1.04,8.36,1.59,6.55,4.61,12.67,8.02,18.49,2.57,4.39,5.36,8.64,8.37,12.73,9.86,13.39,22.12,25.03,36.32,33.72,7.09,4.34,14.74,8.16,22.65,10.77,8.87,2.93,16.78,5.98,26.37,4.16,10.03-1.9,19.93-8.11,23.91-17.78,1.18-2.86,1.77-6.05,1.11-9.07-1.36-6.25-9.83-9.97-14.88-12.99Z"/>',
            ],
            'instagram'         => [
                'viewBox' => '0 0 500 500',
                'body'    => '<path fill="currentColor" d="M350.62,127.83c-12.3,0-22.28,9.98-22.28,22.28s9.98,22.28,22.28,22.28,22.28-9.98,22.28-22.28-9.98-22.28-22.28-22.28Z"/><path fill="currentColor" d="M251.56,156.4c-51.61,0-93.6,41.99-93.6,93.6s41.99,93.6,93.6,93.6,93.6-41.99,93.6-93.6-41.99-93.6-93.6-93.6ZM251.56,309.95c-33.06,0-59.96-26.89-59.96-59.95s26.9-59.95,59.96-59.95,59.95,26.89,59.95,59.95-26.89,59.95-59.95,59.95Z"/><path fill="currentColor" d="M325.87,440.03h-151.74c-62.95,0-114.16-51.21-114.16-114.16v-151.75c0-62.95,51.21-114.16,114.16-114.16h151.74c62.95,0,114.17,51.21,114.17,114.16v151.75c0,62.95-51.22,114.16-114.17,114.16ZM174.13,95.73c-43.23,0-78.4,35.17-78.4,78.4v151.75c0,43.23,35.17,78.4,78.4,78.4h151.74c43.23,0,78.41-35.17,78.41-78.4v-151.75c0-43.23-35.17-78.4-78.41-78.4h-151.74Z"/>',
            ],
            'facebook'          => [
                'viewBox' => '0 0 500 500',
                'body'    => '<path fill="currentColor" d="M282.9,439.47v-165.79h55.28l10.52-68.58h-65.8v-44.5c0-18.76,9.19-37.05,38.66-37.05h29.92v-58.38s-27.15-4.63-53.11-4.63c-54.19,0-89.61,32.85-89.61,92.3v52.27h-60.24v68.58h60.24v165.79h74.14Z"/>',
            ],
            'linkedin'          => [
                'viewBox' => '0 0 500 500',
                'body'    => '<path fill="currentColor" d="M75.42,189.47h74.92v240.65h-74.92v-240.65ZM112.89,69.88c23.96,0,43.37,19.44,43.37,43.34s-19.41,43.4-43.37,43.4-43.43-19.46-43.43-43.4,19.41-43.34,43.43-43.34"/><path fill="currentColor" d="M197.27,189.47h71.75v32.92h.98c10-18.94,34.4-38.87,70.83-38.87,75.73,0,89.7,49.81,89.7,114.62v131.99h-74.75v-117c0-27.93-.54-63.82-38.88-63.82s-44.88,30.4-44.88,61.78v119.04h-74.76v-240.65Z"/>',
            ],
            'x'                 => [
                'viewBox' => '0 0 500 500',
                'body'    => '<path fill="currentColor" d="M277.51,227.38l107.63-125.11h-25.5l-93.45,108.63-74.64-108.63h-86.09l112.87,164.27-112.87,131.2h25.51l98.69-114.72,78.83,114.72h86.09l-117.06-170.36h0ZM242.57,267.98l-11.44-16.36-91-130.16h39.18l73.43,105.04,11.44,16.36,95.46,136.54h-39.18l-77.9-111.42h0Z"/>',
            ],
            'tiktok'            => [
                'viewBox' => '0 0 500 500',
                'body'    => '<path fill="currentColor" d="M305.32,190.07c21.62,15.44,48.09,24.53,76.69,24.53v-43.29c-15.96-3.4-30.09-11.74-40.72-23.33-18.19-11.34-31.29-30.06-35.13-51.96h-40.09v219.69c-.09,25.61-20.89,46.35-46.53,46.35-15.11,0-28.54-7.2-37.04-18.35-15.18-7.66-25.59-23.39-25.59-41.54,0-25.68,20.83-46.51,46.53-46.51,4.92,0,9.67.77,14.12,2.18v-43.77c-55.19,1.14-99.58,46.21-99.58,101.64,0,27.67,11.06,52.75,28.99,71.08,16.18,10.86,35.67,17.2,56.63,17.2,56.17,0,101.72-45.52,101.72-101.67v-112.25Z"/>',
            ],
            'youtube'           => [
                'viewBox' => '0 0 500 500',
                'body'    => '<path fill="currentColor" d="M419.96,205.62c0-41.19-33.39-74.59-74.59-74.59h-190.73c-41.2,0-74.59,33.4-74.59,74.59v88.76c0,41.2,33.39,74.59,74.59,74.59h190.73c41.2,0,74.59-33.39,74.59-74.59v-88.76ZM307.79,256.65l-85.53,42.32c-3.35,1.81-14.74-.61-14.74-4.43v-86.86c0-3.86,11.49-6.29,14.84-4.37l81.87,44.54c3.43,1.95,7.04,6.92,3.56,8.8Z"/>',
            ],
            'googlemaps'        => [
                'viewBox' => '0 0 500 500',
                'body'    => '<path fill="currentColor" d="M250,52c-82.7,0-149.8,67.1-149.8,149.8,0,103.7,149.8,246.2,149.8,246.2s149.8-142.5,149.8-246.2c0-82.7-67.1-149.8-149.8-149.8ZM250,258.9c-31.6,0-57.1-25.6-57.1-57.1s25.6-57.1,57.1-57.1,57.1,25.6,57.1,57.1-25.5,57.1-57.1,57.1Z"/>',
            ],
            'pay_wompi'         => [
                'viewBox' => '0 0 500 500',
                'body'    => '<path fill="currentColor" d="M71.76,68.17c6.85-2.33,17.15-4.96,29.85-5.07,13.54-.12,24.47,2.67,31.54,5.07,14.27,59.13,28.53,118.26,42.8,177.4l42.24-177.4c7.54-2.4,18.59-5,32.1-5.07,14.07-.07,25.53,2.62,33.23,5.07,16.33,59.13,32.66,118.26,49,177.4l34.92-177.4c7.65-2.68,20.08-5.92,35.48-5.07,10.43.58,19.06,2.86,25.34,5.07-21.5,83.04-43,166.08-64.5,249.12-.48,1.87-2.17,3.18-4.1,3.18h-61c-1.94,0-3.63-1.32-4.11-3.2l-41.99-166.31-47,166.43c-.52,1.83-2.18,3.09-4.08,3.09h-59.98c-1.92,0-3.61-1.3-4.1-3.16L71.76,68.17Z"/><path fill="currentColor" d="M121.88,380.16c36.58-7.76,83.23-14.34,137.41-14.08,51.43.25,95.9,6.59,131.22,14.08v56.88c-37.93-5.76-82.26-10-131.78-10.14-51.62-.15-97.7,4.19-136.85,10.14v-56.88Z"/>',
            ],
            'pay_epayco'        => [
                'viewBox' => '0 0 500 500',
                'body'    => '<path fill="currentColor" d="M400.26,137.57c-2.72-10.21-8.79-17.31-13.24-24.06-30.65-46.43-115.14-47.24-208.24-16.68l-66.26,327.23h48.19c14.36,0,26.65-10.3,29.15-24.44l12.53-66.44c5.06-3.37,5.51-4.78,11.2-4.14,62.15,14.1,117.15,3.55,153.13-37.5,41.62-39.55,51.61-104.46,33.52-153.98ZM305.78,128.36c4.21.82,11.26,2.42,18.61,8.74,13.13,11.27,15.53,29.14,15.75,32.53,0,0-24.51,47.21-44.85,70.54-30.29,34.74-43.95,49.13-90.22,74.02,12.72-60.43,38.16-181.3,38.16-181.3,17.47-4.79,62.54-4.54,62.54-4.54Z"/><path fill="currentColor" d="M87.06,359.16c31.91-6.18,130.9-29.62,206.21-120.01,46.51-55.83,64.38-113.66,71.81-146.6,9.64-5.54,19.28-11.08,28.93-16.62l18.93,25.64c-10.83,32.23-56.24,154.82-183.12,219.63-55.22,28.21-107.39,36.03-142.76,37.96Z"/><path fill="currentColor" d="M205.22,313.42l-2.93,20.39c-8.59,3.76-17.9,7.92-27.87,11.37-17.41,6.02-33.78,10.24-48.59,13.23,1.3-6.43,2.6-12.86,3.91-19.29l47.68-31.18,27.81,5.49Z"/>',
            ],
            'pay_paypal'        => [
                'viewBox' => '0 0 500 500',
                'body'    => '<path fill="currentColor" d="M215.05,163.37l-39.89,259.52c-1.39,9.01,5.04,17.14,13.56,17.14h43.56c6.19,0,11.5-4.82,12.53-11.36l10.79-71.1c1.41-10.06,5.71-23.9,22.01-26.31l19.79-.26c11.53-.18,68.39-8.02,92.12-71.17,6.93-18.45,14.94-49.29-2.32-81.2-16.38-30.28-46.97-33.48-52.65-33.46-36.52-.17-61.69-.52-98.21-.52-9.93,0-20.1,7.72-21.29,18.72Z"/><path fill="currentColor" d="M149.76,74.11l-47.1,302.67c-1.6,10.26,6.34,19.52,16.71,19.52h46.96c7.54,0,13.96-5.49,15.13-12.94l15.25-96.74c1.32-8.4,8.52-14.62,17.02-14.72l42.61-.5c14.04-.21,76.31-6.95,105.64-80.28,8.38-20.96,15.99-61.69-3.77-93.89-20.96-34.17-51.46-36.69-58.38-36.67-44.5-.2-89-.39-133.5-.59-8.26-.04-15.31,5.97-16.58,14.14Z"/>',
            ],
            'pay_bold'          => [
                'viewBox' => '0 0 500 500',
                'body'    => '<path fill="currentColor" d="M397.7,263.26c-1.55,75.76-70.4,134.5-143.67,136-78.22,1.6-144.92-54.09-154.03-135.83l297.7-.17Z"/><path fill="currentColor" d="M400,236.09l-299.03.7c4.89-75.8,70-134.37,144.18-136.04,75.91-1.71,144.1,49.4,154.86,135.34Z"/>',
            ],
            'pay_stripe'        => [
                'viewBox' => '0 0 500 500',
                'body'    => '<path fill="currentColor" d="M264.6,87l19.9,2c23.2,3.1,45.8,9.4,67,19.1l-12,75c-14.2-7.1-29.4-13.3-44.8-17.1-16.2-4.1-67.3-14.7-63.1,15.5,1.7,12.1,22.4,20.7,32.4,24.9,42,17.7,89.7,29.5,100,81.8l2.2,15.9v12.7c-1,3.9-.8,8.3-1.4,12.3-6.4,45-38.5,72-81.7,80.4l-26.2,3.4c-7.4-.3-14.9.4-22.2,0s-.7-.6-1.5-.7c-34.5-2-68.9-10.1-99.5-26.4l12.1-75.4c18.7,10.3,39.1,18.6,59.9,23.6s58.4,12.2,65.8-9.3c6.4-18.7-13.8-28.7-27.5-34.8-46.3-20.7-98.5-30.6-105.2-91.9-7.4-67.4,39.4-107.5,102.9-111.1h22.9Z"/>',
            ],
            'pay_mercadopago'   => [
                'viewBox' => '0 0 500 500',
                'body'    => '<path fill="currentColor" d="M250,150.6c-79.3,0-143.6,42.2-143.6,93.9s0,5,0,5.5c0,54.9,56.2,99.4,143.6,99.4s143.6-44.5,143.6-99.3v-5.5c0-51.7-64.3-93.9-143.6-93.9h0ZM387.1,234.1c-31.2,6.9-54.5,17-60.3,19.6-13.6-11.9-45.1-39.3-53.6-45.7-4.9-3.7-8.2-5.6-11.1-6.5-1.3-.4-3.1-.9-5.5-.9s-4.5.4-6.9,1.2c-5.5,1.8-11,6.1-16.3,10.3l-.3.2c-4.9,3.9-10.1,8-13.9,8.9-1.7.4-3.4.6-5.2.6-4.3,0-8.2-1.3-9.7-3.1-.2-.3,0-.8.5-1.5h0c0-.1,12-13,12-13,9.4-9.4,18.2-18.2,38.7-18.7h1c12.7,0,25.4,5.7,26.8,6.4,11.9,5.8,24.2,8.8,36.6,8.8s26.1-3.2,40-9.6c14.6,12.2,24.2,27,27.1,43.1h.1ZM250,156.2c42.1,0,79.8,12.1,105.1,31.1-12.2,5.3-23.9,8-35.2,8s-23-2.8-34.2-8.2c-.6-.3-14.6-6.9-29.2-6.9h-1.1c-17.1.4-26.8,6.5-33.3,11.8-6.3.2-11.8,1.7-16.6,3-4.3,1.2-8.1,2.2-11.7,2.2s-4.2-.1-4.4-.1c-4.2-.1-25.2-5.3-41.9-11.6,25.3-18,61.9-29.3,102.6-29.3h0ZM142.4,189.2c17.5,7.2,38.8,12.7,45.5,13.1,1.9.1,3.9.3,5.9.3,4.5,0,8.9-1.2,13.2-2.4,2.5-.7,5.4-1.5,8.3-2.1l-2.4,2.4-12.2,13.2c-1,1-3,3.6-1.7,6.7.5,1.3,1.6,2.5,3.2,3.6,2.9,1.9,8.1,3.3,12.9,3.3s3.6-.2,5.1-.5c5.1-1.1,10.5-5.4,16.1-9.9,4.5-3.6,10.9-8.1,15.9-9.5,1.4-.4,3.1-.6,4.4-.6s.8,0,1.1,0c3.2.4,6.4,1.5,12,5.7,10,7.5,54.2,46.2,54.6,46.6,0,0,2.9,2.5,2.6,6.5-.1,2.3-1.4,4.3-3.5,5.6-1.9,1.2-3.8,1.8-5.8,1.8-3,0-5-1.4-5.1-1.5-.2-.1-15.3-14-20.9-18.7-.9-.7-1.8-1.4-2.6-1.4s-.9.2-1.2.6c-.9,1.1.1,2.6,1.3,3.6l17.7,17.8s2.2,2.1,2.5,4.8c.1,2.9-1.3,5.4-4.2,7.3-2.1,1.4-4.2,2.1-6.3,2.1s-4.6-1.2-5-1.5l-2.5-2.5c-4.6-4.6-9.4-9.3-12.9-12.2-.9-.7-1.8-1.4-2.6-1.4s-.8.2-1.1.5c-.4.4-.7,1.2.3,2.6.4.6.9,1,.9,1l12.9,14.5c.1.1,2.7,3.2.3,6.2l-.5.6-1.2,1.2c-2.2,1.8-5.1,2-6.3,2s-1.2,0-1.8-.1c-1.3-.2-2.1-.6-2.5-1.1l-.2-.2c-.7-.7-7.2-7.4-12.6-11.9-.7-.6-1.6-1.3-2.5-1.3s-.9.2-1.2.5c-1.1,1.2.5,2.9,1.2,3.6l11,12.1c0,.1-.1.4-.4.7-.4.5-1.7,1.9-5.7,2.4h-1.5c-4.1,0-8.5-2-10.8-3.2,1-2.2,1.6-4.6,1.6-7,0-9.1-7.4-16.4-16.4-16.4h-.6c.3-4.1-.3-12-8.3-15.4-2.3-1-4.6-1.5-6.9-1.5s-3.4.3-5,.9c-1.7-3.2-4.4-5.6-8-6.8-2-.7-4-1-5.9-1-3.4,0-6.4,1-9.2,2.9-2.6-3.3-6.6-5.2-10.8-5.2s-7.2,1.5-9.8,4.1c-3.4-2.6-17-11.3-53.4-19.5-1.7-.4-5.7-1.5-8.2-2.2,3.4-16.3,13.8-31.3,29.2-43.5h0v-.3ZM210,283.9l-.4-.4h-.4c-.3,0-.7.1-1.1.4-1.9,1.3-3.6,1.9-5.4,1.9s-2-.2-3-.6c-8.4-3.3-7.8-11.2-7.4-13.6,0-.5,0-.9-.4-1.1l-.6-.5-.6.5c-1.6,1.6-3.8,2.4-6.1,2.4-4.8,0-8.8-3.9-8.8-8.8s3.9-8.8,8.8-8.8,8.1,3.3,8.6,7.6l.3,2.4,1.3-2c.1-.2,3.7-5.6,10.2-5.6s2.5.2,3.8.6c5.2,1.6,6.1,6.3,6.2,8.2,0,1.1.9,1.2,1.1,1.2.4,0,.8-.3,1-.5,1-1,3.1-2.7,6.4-2.7s3.1.4,4.8,1.1c8.2,3.5,4.5,14,4.5,14.1-.7,1.7-.7,2.5,0,2.9h.3c0,.1.2.1.2.1.4,0,.8-.2,1.6-.4,1.1-.4,2.8-1,4.4-1h0c6.2,0,11.3,5.1,11.3,11.3s-5.1,11.2-11.3,11.2-11-4.7-11.2-10.7c0-.5,0-1.9-1.2-1.9s-.9.3-1.4.7c-1.3,1.2-3,2.5-5.5,2.5s-2.4-.3-3.6-.8c-6.4-2.6-6.5-7-6.2-8.8,0-.5,0-1-.2-1.4h0v.5ZM250,332.8c-76.3,0-138.1-39.6-138.1-88.3s0-3.9.3-5.8c.6.1,6.7,1.6,7.9,1.9,37.2,8.3,49.5,16.9,51.6,18.5-.7,1.7-1.1,3.5-1.1,5.4,0,7.7,6.2,13.9,13.9,13.9s1.7,0,2.6-.2c1.2,5.7,4.9,9.9,10.5,12.1,1.6.6,3.3,1,5,1s2.1-.1,3.2-.4c1.1,2.6,3.4,6,8.6,8.1,1.8.7,3.7,1.1,5.5,1.1s2.9-.3,4.2-.8c2.5,6.1,8.5,10.2,15.2,10.2s8.7-1.8,11.8-5c2.6,1.5,8.2,4.1,13.9,4.2.7,0,1.4,0,2.1-.1,5.6-.7,8.2-2.9,9.4-4.6.2-.3.4-.6.6-1,1.3.4,2.8.7,4.5.7,3.1,0,6-1,9-3.2,2.9-2.1,5-5.1,5.3-7.7h0c1,.1,2,.2,3,.2,3.2,0,6.3-1,9.2-2.9,5.7-3.8,6.7-8.7,6.6-11.9,1,.2,2,.3,3,.3,3,0,5.9-.9,8.6-2.7,3.5-2.3,5.7-5.8,6-9.8.2-2.8-.5-5.5-1.9-7.9,9.6-4.1,31.5-12.1,57.3-17.9.1,1.5.2,2.9.2,4.4,0,48.8-61.8,88.3-138.1,88.3h.2Z"/>',
            ],
            'pay_wise'          => [
                'viewBox' => '0 0 500 500',
                'body'    => '<path fill="currentColor" fill-rule="evenodd" d="M195.6,205.1l-64.1,75.2h114.4l12.9-35.5h-49l30-34.8v-.9c0,0-19.4-33.7-19.4-33.7h87.6l-67.9,187.7h46.5l82-226.2h-211.9l38.9,68.2h0,0Z"/>',
            ],
        ];

        if ( ! isset( $icons[ $key ] ) ) {
            return '';
        }

        $icon = $icons[ $key ];
        $label = trim( str_replace( [ '_', '-' ], ' ', preg_replace( '/\.svg$/', '', (string) $network_key ) ) );

        if ( '' === $label ) {
            $label = 'red social';
        }

        return '<svg class="icon-social" viewBox="' . esc_attr( $icon['viewBox'] ) . '" role="img" aria-label="' . esc_attr( $label ) . '" focusable="false">' . $icon['body'] . '</svg>';
    }
}

if ( ! function_exists( 'sc_get_social_icon_markup' ) ) {
    function sc_get_social_icon_markup( $icon_filename, $label = '', $allow_inline_svg = false ) {
        if ( $allow_inline_svg ) {
            return sc_get_social_icon_fallback_svg( '' !== $label ? $label : $icon_filename );
        }

        $candidates = [
            [
                'path' => WP_CONTENT_DIR . '/uploads/2026/03/' . $icon_filename,
                'url'  => content_url( 'uploads/2026/03/' . $icon_filename ),
            ],
            [
                'path' => WP_CONTENT_DIR . '/uploads/2025/02/' . $icon_filename,
                'url'  => content_url( 'uploads/2025/02/' . $icon_filename ),
            ],
        ];

        foreach ( $candidates as $candidate ) {
            if ( ! file_exists( $candidate['path'] ) || ! is_readable( $candidate['path'] ) ) {
                continue;
            }

            return sprintf(
                '<img src="%1$s" alt="%2$s" loading="eager" fetchpriority="high" decoding="sync">',
                esc_url( $candidate['url'] ),
                esc_attr( $label )
            );
        }

        return sprintf(
            '<img src="%1$s" alt="%2$s" loading="eager" fetchpriority="high" decoding="sync">',
            esc_url( content_url( 'uploads/2025/02/' . $icon_filename ) ),
            esc_attr( $label )
        );
    }
}


// Requerir archivos de lógica
require_once SMARTCARDS_PLUGIN_DIR . 'includes/shortcodes.php';
require_once SMARTCARDS_PLUGIN_DIR . 'includes/procesar-formulario.php';
require_once SMARTCARDS_PLUGIN_DIR . 'includes/admin-panel.php';
require_once SMARTCARDS_PLUGIN_DIR . 'includes/smartcards-apple.php'; // ← SIWA endpoint

//endpoint OTP acceso con un código de verificación
require_once SMARTCARDS_PLUGIN_DIR . 'includes/otp-endpoints.php';

// Endpoint /smartcards/v1/me — devuelve datos del usuario autenticado via JWT
require_once SMARTCARDS_PLUGIN_DIR . 'includes/rest-me.php';

// Endpoint /smartcards/v1/create-card - crear Smart Card
require_once SMARTCARDS_PLUGIN_DIR . 'includes/rest/create-card.php';

// Cargar API de créditos SmartCards
require_once plugin_dir_path(__FILE__) . 'includes/api-credits.php';

// Carga el endpoint de Magic Link
require_once SMARTCARDS_PLUGIN_DIR . 'includes/smartcards-magic.php';

// Custom Post Type: Smart Card
// Define la estructura, visibilidad pública y rutas (/card/slug)
// Base para la creación de perfiles desde la App y futuras integraciones
require_once SMARTCARDS_PLUGIN_DIR . 'includes/smartcards-post-type.php';

// Endpoint publicar Smart Card
require_once SMARTCARDS_PLUGIN_DIR . 'includes/rest/publish-card.php';

// Endpoint listar Smart Cards del usuario
require_once SMARTCARDS_PLUGIN_DIR . 'includes/rest/my-cards.php';

// Endpoint REST: obtener Smart Card por ID (precargar wizard)
require_once SMARTCARDS_PLUGIN_DIR . 'includes/rest/get-card.php';

// Endpoint REST para crear perfiles públicos desde la App (React Native)
require_once SMARTCARDS_PLUGIN_DIR . 'includes/rest-profile-create.php';

// REST: subir imágenes (Paso 6)
require_once SMARTCARDS_PLUGIN_DIR . 'includes/rest/upload-image.php';

// REST: cancelar perfil (borrar borrador y recursos)
require_once SMARTCARDS_PLUGIN_DIR . 'includes/rest/delete-draft.php';

add_filter('the_content', function ($content) {
    if (!is_singular('smartcards')) {
        return $content;
    }

    if (is_admin()) {
        return $content;
    }

    $post_id = get_the_ID();
    if (!$post_id) {
        return $content;
    }

    $cached_html = (string) get_post_meta($post_id, 'sc_cached_html', true);
    if ($cached_html !== '') {
        return $cached_html;
    }

    return $content;
}, 999);






/* =========================================================
 * DESCARGA SEGURA DE VCF (WEB + APP)
 * ========================================================= */
if ( ! function_exists( 'sc_register_descargar_vcf_rewrite' ) ) {
function sc_register_descargar_vcf_rewrite() {
  add_rewrite_rule(
    '^descargar-vcf/([0-9]+)/?$',
    'index.php?descargar_vcf=1&user_id=$matches[1]',
    'top'
  );
}
}

add_action('init', 'sc_register_descargar_vcf_rewrite');

add_filter('query_vars', function ($vars) {
  $vars[] = 'descargar_vcf';
  $vars[] = 'user_id';
  return $vars;
});

register_activation_hook(__FILE__, function() {
  $rewrite_version = 'smartcards-rewrite-2026-04-13';

  if ( function_exists( 'sc_register_smartcards_post_type' ) ) {
    sc_register_smartcards_post_type();
  }

  if ( function_exists( 'sc_register_descargar_vcf_rewrite' ) ) {
    sc_register_descargar_vcf_rewrite();
  }

  flush_rewrite_rules();
  update_option( 'smartcards_rewrite_version', $rewrite_version );
});

register_deactivation_hook(__FILE__, function() {
  flush_rewrite_rules();
});

if ( ! function_exists( 'sc_maybe_flush_rewrite_rules_once' ) ) {
function sc_maybe_flush_rewrite_rules_once() {
  $rewrite_version = 'smartcards-rewrite-2026-04-13';

  if ( get_option( 'smartcards_rewrite_version' ) === $rewrite_version ) {
    return;
  }

  if ( function_exists( 'sc_register_smartcards_post_type' ) ) {
    sc_register_smartcards_post_type();
  }

  if ( function_exists( 'sc_register_descargar_vcf_rewrite' ) ) {
    sc_register_descargar_vcf_rewrite();
  }

  flush_rewrite_rules( false );
  update_option( 'smartcards_rewrite_version', $rewrite_version );
}
}

add_action( 'init', 'sc_maybe_flush_rewrite_rules_once', 20 );

add_action('template_redirect', function () {
  if (!get_query_var('descargar_vcf')) return;

  $user_id = (int) get_query_var('user_id');
  if (!$user_id) wp_die('Usuario inválido');

  $vcf_attachment_id = get_user_meta($user_id, 'smartcards_vcf_attachment_id', true);
  if (!$vcf_attachment_id) wp_die('VCF no encontrado');

  $file_path = get_attached_file($vcf_attachment_id);
  if (!file_exists($file_path)) wp_die('Archivo no existe');

header('Content-Type: text/vcard; charset=utf-8');
$nombre   = get_user_meta($user_id, 'smartcards_nombre', true);
$apellido = get_user_meta($user_id, 'smartcards_apellido', true);

// Fallback seguro
if (empty($nombre) && empty($apellido)) {
    $filename = 'contacto.vcf';
} else {
    $filename = sanitize_file_name($nombre . '-' . $apellido . '.vcf');
}

header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($file_path));

// 🔥 HEADERS ANTI-CACHÉ (clave para iOS)
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');

readfile($file_path);
exit;
});

// Cargar estilos y scripts
function smartcards_enqueue_assets() {
    wp_enqueue_style('smartcards-styles', SMARTCARDS_PLUGIN_URL . 'includes/assets/css/smartcards-styles.css', [], '1.0');

    // Scripts QR y frontend
    wp_enqueue_script(
        'qr-code-styling',
        'https://cdn.jsdelivr.net/npm/qr-code-styling@1.5.0/lib/qr-code-styling.js',
        [],
        '1.5.0',
        true
    );

    wp_enqueue_script(
        'smartcards-frontend',
        SMARTCARDS_PLUGIN_URL . 'includes/assets/js/smartcards-frontend.js',
        ['qr-code-styling'],
        '1.0',
        true
    );
    // ---- Datos del usuario para OneSignal (External ID + Tags) ----
  $uid = get_current_user_id();

  // Créditos
  $credits = (int) get_user_meta($uid, 'smartcards_credits', true);

  // País (WooCommerce o meta propio)
  $country = '';
  if ($uid) {
    $country = get_user_meta($uid, 'billing_country', true);          // Woo
    if (!$country) $country = get_user_meta($uid, 'country', true);   // meta propio (si lo usas)
  }
  $country = $country ? strtoupper($country) : '';

  // Idioma (dos letras)
  $lang = substr(get_locale(), 0, 2);

  // Estado del perfil (ajústalo a tu flujo)
  $profile_status = get_user_meta($uid, 'sc_profile_status', true);
  if (!$profile_status) { $profile_status = 'pending'; } // pending | approved | created

  // Pasa datos al JS del front
  wp_localize_script('smartcards-frontend', 'smartcardsUser', [
    'id'             => $uid,
    'credits'        => $credits,
    'country'        => $country,            // ej. CO, MX, US
    'lang'           => strtolower($lang),   // ej. es, en
    'profile_status' => $profile_status,
  ]);
}

add_action('wp_enqueue_scripts', 'smartcards_enqueue_assets');

add_action( 'wp_enqueue_scripts', function() {
    $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';

    if ( strpos( $request_uri, '/card/' ) === false ) {
        return;
    }

    wp_dequeue_script( 'wc-cart-fragments' );
    wp_dequeue_script( 'woocommerce' );
    wp_dequeue_script( 'yay-currency' );
    wp_dequeue_script( 'mailchimp' );
    wp_dequeue_script( 'wp-emoji-release' );
}, 100 );

// Encolar scripts JavaScript (QRCodeStyling y Frontend personalizado)
function smartcards_enqueue_scripts() {
    // QR Styling library (QRCodeStyling desde CDN)
    wp_enqueue_script(
        'qr-code-styling',
        'https://cdn.jsdelivr.net/npm/qr-code-styling@1.5.0/lib/qr-code-styling.js',
        [],
        '1.5.0',
        true
    );

    // Tu JavaScript personalizado
    wp_enqueue_script(
        'smartcards-frontend',
        SMARTCARDS_PLUGIN_URL . 'includes/assets/js/smartcards-frontend.js',
        ['qr-code-styling'],
        '1.0',
        true
    );
}


// ======================================================
// Ya NO asignamos crédito al registro de usuario
// ======================================================

// Desactivamos esta función para que NO sume créditos al registrarse:
/*
function asignar_creditos_nuevo_usuario( $user_id ) {
    update_user_meta( $user_id, 'smartcards_credits', 1 );
}
add_action( 'user_register', 'asignar_creditos_nuevo_usuario' );
*/

// ======================================================
// Función que sí asigna créditos cuando el pedido se completa
// ======================================================

/**
 * Validar automáticamente código de crédito y guardar empresa al registrar usuario con WPForms.
 */
function smartcards_registro_personalizado_wpforms($user_id, $fields, $form_data) {

    // IDs reales de tus campos en WPForms
    $empresa = sanitize_text_field($fields['7']['value']);
    $codigo_credito_ingresado = sanitize_text_field($fields['7']['value']);

    if (!empty($empresa)) {
        update_user_meta($user_id, 'smartcards_empresa', $empresa);
    }

    if ($codigo_credito_ingresado) {
        $codigo_credito_ingresado = strtoupper(trim($codigo_credito_ingresado));
        $datos_codigo_credito = get_option('smart_codigo_credito_' . $codigo_credito_ingresado);

        if ($datos_codigo_credito) {
            $usos_actuales = (int)$datos_codigo_credito['usos_actuales'];
            $usos_maximos  = (int)$datos_codigo_credito['usos_maximos'];
            $creditos      = (int)$datos_codigo_credito['creditos'];

            // Validación clave: comprobar si ya llegó al límite de usos máximos
            if ($usos_actuales < $usos_maximos) {
                // Asignar solo 1 crédito por registro
                update_user_meta($user_id, 'smartcards_credits', 1);

                // Incrementar usos del código automáticamente
                $datos_codigo_credito['usos_actuales']++;
                update_option('smart_codigo_credito_' . $codigo_credito_ingresado, $datos_codigo_credito);
            } else {
                // Si el código llegó al límite, asignar 0 créditos o manejar como prefieras
                update_user_meta($user_id, 'smartcards_credits', 0);
            }
        } else {
            // Código no válido, asignar 0 créditos
            update_user_meta($user_id, 'smartcards_credits', 0);
        }
    }
}

add_action('wpforms_user_registered', 'smartcards_registro_personalizado_wpforms', 10, 3);

function smartcards_ocultar_barra_wp() {
    if ( !current_user_can('administrator') && !is_admin() ) {
        show_admin_bar(false);
    }
}
add_action('after_setup_theme', 'smartcards_ocultar_barra_wp');

// === PASO 1: Página Administrativa === //

add_action('admin_menu', 'smartcards_registrar_pagina_admin');

function smartcards_registrar_pagina_admin() {
    add_menu_page(
        'Notificaciones Smart Cards',
        'Notificaciones Smart Cards',
        'manage_options',
        'notificaciones-smartcards',
        'smartcards_pagina_opciones',
        'dashicons-bell',
        20
    );
}

//Notificaciones Smart cards panel del administrador
function smartcards_pagina_opciones() {
    // Guardar cambios
    if ( isset($_POST['guardar_notificaciones']) && check_admin_referer('smartcards_notif_nonce','smartcards_notif_nonce') ) {
        $aprob  = isset($_POST['usuarios_aprobacion']) ? array_map('intval', $_POST['usuarios_aprobacion']) : [];
        $diario = isset($_POST['usuarios_diario'])    ? array_map('intval', $_POST['usuarios_diario'])    : [];

        update_option('smartcards_usuarios_notificar_aprobacion', $aprob);
        update_option('smartcards_usuarios_notificar_diario',    $diario);
        echo '<div class="updated"><p>✅ Configuración guardada correctamente.</p></div>';
    }

    // Obtener todos los editores/administradores
    $usuarios = get_users(['role__in'=>['editor','administrator']]);
    $selAprob = get_option('smartcards_usuarios_notificar_aprobacion', []);
    $selDiario = get_option('smartcards_usuarios_notificar_diario',    []);

    ?>
    <div class="wrap">
      <h1>🔔 Seleccionar Editores para Notificaciones</h1>
      <form method="post">
        <?php wp_nonce_field('smartcards_notif_nonce','smartcards_notif_nonce'); ?>
        <table class="wp-list-table widefat fixed striped">
  <thead>
    <tr>
      <th style="width:70px; text-align:center;">Aprobación</th>
      <th style="width:70px; text-align:center;">Diario</th>
      <th>Usuario</th>
      <th>Email</th>
      <th>Rol</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach($usuarios as $u): ?>
    <tr>
      <td>
        <input type="checkbox"
               name="usuarios_aprobacion[]"
               value="<?php echo $u->ID;?>"
               <?php checked( in_array($u->ID, $selAprob) );?> />
      </td>
      <td>
        <input type="checkbox"
               name="usuarios_diario[]"
               value="<?php echo $u->ID;?>"
               <?php checked( in_array($u->ID, $selDiario) );?> />
      </td>
      <td><?php echo esc_html($u->display_name);?></td>
      <td><?php echo esc_html($u->user_email);?></td>
      <td><?php echo esc_html( implode(', ',$u->roles) );?></td>
    </tr>
    <?php endforeach;?>
  </tbody>
</table>
        <p><input type="submit"
                  name="guardar_notificaciones"
                  class="button button-primary"
                  value="Guardar configuración" /></p>
      </form>
    </div>
    <?php
}


// === PASO 2: Notificación inmediata al aprobar perfil === //

function notificar_aprobacion_perfil($perfil_url, $nombre_cliente, $nombre_empresa) {
    $ids_usuarios = get_option('smartcards_usuarios_notificar_aprobacion', []);
    $usuarios = get_users(['include' => $ids_usuarios]);

    // Agregar por defecto al administrador principal
    $correos = ['info@smartcards.com.co'];

    foreach ($usuarios as $usuario) {
        $correos[] = $usuario->user_email;
    }

    $subject = "✅ Perfil aprobado por $nombre_cliente de $nombre_empresa";
    $message = "Hola,\n\n$nombre_cliente de la empresa \"$nombre_empresa\" aprobó su perfil público.\n🔗 Revísalo aquí: $perfil_url\n\nSaludos,\nEquipo Smart Cards.";

    wp_mail($correos, $subject, $message);

    // Registrar aprobación para resumen diario
    registrar_aprobacion_temporal($perfil_url, $nombre_cliente, $nombre_empresa);
}

function registrar_aprobacion_temporal($perfil_url, $nombre_cliente, $nombre_empresa) {
    $aprobaciones = get_option('smartcards_aprobaciones_temporales', []);

    $aprobaciones[] = [
        'perfil_url' => $perfil_url,
        'cliente' => $nombre_cliente,
        'empresa' => $nombre_empresa,
        'fecha' => current_time('mysql'),
    ];

    update_option('smartcards_aprobaciones_temporales', $aprobaciones);
}


// === PASO 3: Cron para resumen diario === //

register_activation_hook(__FILE__, 'smartcards_activar_cron');
function smartcards_activar_cron() {
    if (!wp_next_scheduled('smartcards_cron_notificacion_diaria')) {
        wp_schedule_event(strtotime('06:00:00'), 'daily', 'smartcards_cron_notificacion_diaria');
    }
}

register_deactivation_hook(__FILE__, 'smartcards_desactivar_cron');
function smartcards_desactivar_cron() {
    wp_clear_scheduled_hook('smartcards_cron_notificacion_diaria');
}

add_action('smartcards_cron_notificacion_diaria', 'enviar_notificacion_consolidada');

function enviar_notificacion_consolidada() {
    $ids_usuarios = get_option('smartcards_usuarios_notificar_diario', []);
    $usuarios      = get_users(['include' => $ids_usuarios]);

    $correos = ['info@smartcards.com.co'];
    foreach ($usuarios as $usuario) {
        $correos[] = $usuario->user_email;
    }

    $aprobaciones = get_option('smartcards_aprobaciones_temporales', []);
    if (empty($aprobaciones)) return;

// Filtrar aprobaciones del día anterior
$ayer = date('Y-m-d', strtotime('-1 day', current_time('timestamp')));
$aprobaciones_ayer = array_filter($aprobaciones, function($aprobacion) use ($ayer) {
    return strpos($aprobacion['fecha'], $ayer) === 0;
});

if (empty($aprobaciones_ayer)) return;

$subject = "📋 Resumen Diario de Perfiles Aprobados (". date('d/m/Y', strtotime($ayer)) .")";
$message = "Hola,\n\nEstos perfiles fueron aprobados el día ". date('d/m/Y', strtotime($ayer)) .":\n\n";

foreach ($aprobaciones_ayer as $aprobacion) {
    $message .= "✔️ Cliente: {$aprobacion['cliente']} ({$aprobacion['empresa']})\n🔗 Perfil: {$aprobacion['perfil_url']}\n📅 Hora: {$aprobacion['fecha']}\n\n";
}

$message .= "Saludos,\nEquipo Smart Cards.";

wp_mail($correos, $subject, $message);

// Limpia solo aprobaciones enviadas (mantén las aprobaciones del día actual)
$aprobaciones_restantes = array_filter($aprobaciones, function($aprobacion) use ($ayer) {
    return strpos($aprobacion['fecha'], $ayer) !== 0;
});
update_option('smartcards_aprobaciones_temporales', $aprobaciones_restantes);
}

// Guarda la URL del perfil generado en la Base de datos para mostrar "Mi Smart Cards" como tarjetas
/**
 * Guarda información del perfil público (URL, nombre completo y foto del perfil)
 * para visualizar múltiples Smart Cards en el dashboard.
 */
add_action('wp_ajax_guardar_url_perfil', 'guardar_url_perfil');
function guardar_url_perfil(){
  
  // Recibes datos vía AJAX
  $datos = json_decode(file_get_contents('php://input'), true);
  $nueva_url = '';
  if ( is_array( $datos ) && ! empty( $datos['perfil_url'] ) ) {
    $nueva_url = sc_get_live_profile_url( $datos['perfil_url'] );
  }

  if ( ! $nueva_url ) {
    wp_send_json_error( [ 'message' => 'URL del perfil no recibida.' ], 400 );
  }

  // Obtienes datos del usuario actual
  $user_id = get_current_user_id();
  $nombre = get_user_meta($user_id, 'smartcards_nombre', true);
  $apellido = get_user_meta($user_id, 'smartcards_apellido', true);
  $foto_perfil_id = get_user_meta($user_id, 'smartcards_foto_perfil_id', true);
  $foto_perfil_url = wp_get_attachment_url($foto_perfil_id);

  // Guardas en meta del usuario (en lugar de sesión)
  $perfil_nuevo = [
    'url' => $nueva_url,
    'nombre_completo' => trim($nombre . ' ' . $apellido),
    'foto_perfil' => $foto_perfil_url
  ];

  // Obtiene perfiles existentes o inicializa un arreglo nuevo
  $perfiles_urls = get_user_meta($user_id, 'smartcards_perfiles_urls', true);
  if (empty($perfiles_urls)) {
    $perfiles_urls = [];
  }

  // Evita duplicados normalizando contra la URL viva del post.
  $already_exists = false;
  foreach ( $perfiles_urls as $item ) {
    if ( ! is_array( $item ) || empty( $item['url'] ) ) {
      continue;
    }

    $existing_url = sc_get_live_profile_url( $item['url'] );
    if ( $existing_url && sc_url_matches( $existing_url, $nueva_url ) ) {
      $already_exists = true;
      break;
    }
  }

  if ( ! $already_exists ) {
    $perfiles_urls[] = $perfil_nuevo;
  }

  // Guarda claramente en la base de datos
  update_user_meta($user_id, 'smartcards_perfiles_urls', $perfiles_urls);

  wp_send_json_success();
}

add_action( 'wp_ajax_sc_track_event', 'sc_track_event_ajax' );
add_action( 'wp_ajax_nopriv_sc_track_event', 'sc_track_event_ajax' );

/**
 * Guarda eventos de analíticas desde AJAX.
 */
function sc_track_event_ajax() {
  global $wpdb;

  $raw  = file_get_contents( 'php://input' );
  $json = json_decode( $raw, true );
  if ( ! is_array( $json ) ) {
    $json = [];
  }

  if ( empty( $_REQUEST['nonce'] ) && ! empty( $json['nonce'] ) ) {
    $_REQUEST['nonce'] = sanitize_text_field( wp_unslash( $json['nonce'] ) );
  }

  if ( false === check_ajax_referer( 'sc_track_event', 'nonce', false ) ) {
    wp_send_json_error( [ 'message' => 'Nonce inválido.' ], 403 );
  }

  $profile_id = 0;
  if ( isset( $_POST['profile_id'] ) ) {
    $profile_id = absint( wp_unslash( $_POST['profile_id'] ) );
  } elseif ( isset( $json['profile_id'] ) ) {
    $profile_id = absint( $json['profile_id'] );
  }

  $event_type = '';
  if ( isset( $_POST['event_type'] ) ) {
    $event_type = sanitize_key( wp_unslash( $_POST['event_type'] ) );
  } elseif ( isset( $json['event_type'] ) ) {
    $event_type = sanitize_key( $json['event_type'] );
  }
  $event_type = substr( $event_type, 0, 32 );

  $button_key = null;
  if ( isset( $_POST['button_key'] ) ) {
    $button_key = sanitize_text_field( wp_unslash( $_POST['button_key'] ) );
  } elseif ( isset( $json['button_key'] ) ) {
    $button_key = sanitize_text_field( $json['button_key'] );
  }
  $button_key = $button_key ? substr( $button_key, 0, 64 ) : null;

  $url = null;
  if ( isset( $_POST['url'] ) ) {
    $url = esc_url_raw( wp_unslash( $_POST['url'] ) );
  } elseif ( isset( $json['url'] ) ) {
    $url = esc_url_raw( $json['url'] );
  }
  $url = $url ? $url : null;

  if ( ! $profile_id || ! $event_type ) {
    wp_send_json_error( [ 'message' => 'Parámetros inválidos.' ], 400 );
  }

  $profile_post = get_post( $profile_id );
  if ( ! $profile_post || 'page' !== $profile_post->post_type ) {
    wp_send_json_error( [ 'message' => 'profile_id inválido.' ], 400 );
  }

  $owner_user_id = (int) get_post_meta( $profile_id, 'sc_owner_user_id', true );
  if ( $owner_user_id <= 0 ) {
    wp_send_json_error( [ 'message' => 'Owner no encontrado.' ], 400 );
  }

  $ip_source = '';
  if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
    $forwarded = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
    $parts     = explode( ',', $forwarded );
    $ip_source = trim( $parts[0] );
  } elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
    $ip_source = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
  }

  $ua_source = '';
  if ( ! empty( $_SERVER['HTTP_USER_AGENT'] ) ) {
    $ua_source = sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) );
  }

  $ip_hash = $ip_source ? hash( 'sha256', $ip_source ) : null;
  $ua_hash = $ua_source ? hash( 'sha256', $ua_source ) : null;

  $table_name = $wpdb->prefix . 'smartcards_events';
  $inserted   = $wpdb->insert(
    $table_name,
    [
      'profile_id'    => $profile_id,
      'owner_user_id' => $owner_user_id,
      'event_type'    => $event_type,
      'button_key'    => $button_key,
      'url'           => $url,
      'ip_hash'       => $ip_hash,
      'ua_hash'       => $ua_hash,
      'created_at'    => current_time( 'mysql' ),
    ]
  );

  if ( false === $inserted ) {
    wp_send_json_error( [ 'message' => 'No se pudo guardar el evento.' ], 500 );
  }

  wp_send_json_success(
    [
      'saved'    => true,
      'event_id' => (int) $wpdb->insert_id,
    ]
  );
}

// ——— Canonicaliza una URL: sin query/fragment, con trailing slash y base del sitio ———
function sc_canonicalize_url( $url ) {
  if ( ! $url ) { return ''; }
  $parts = wp_parse_url( $url );
  if ( ! is_array( $parts ) ) { return ''; }

  $path = isset( $parts['path'] ) ? $parts['path'] : '/';
  $path = trailingslashit( '/' . ltrim( $path, '/' ) ); // asegura slash final

  // Une con el home (normaliza http/https y dominio)
  return rtrim( home_url(), '/' ) . $path;
}

function sc_get_profile_post_id_from_url( $url ) {
  $canonical_url = sc_canonicalize_url( $url );
  if ( '' === $canonical_url ) {
    return 0;
  }

  return (int) url_to_postid( $canonical_url );
}

function sc_get_profile_permalink( $post_id, $fallback = '' ) {
  $post_id = (int) $post_id;
  if ( $post_id <= 0 ) {
    return $fallback ? esc_url_raw( $fallback ) : '';
  }

  $permalink = get_permalink( $post_id );
  if ( $permalink ) {
    return (string) $permalink;
  }

  return $fallback ? esc_url_raw( $fallback ) : '';
}

function sc_get_live_profile_url( $url ) {
  $stored_url = esc_url_raw( (string) $url );
  if ( '' === $stored_url ) {
    return '';
  }

  $post_id = sc_get_profile_post_id_from_url( $stored_url );
  if ( $post_id > 0 ) {
    return sc_get_profile_permalink( $post_id, $stored_url );
  }

  return $stored_url;
}

// Igualdad por “path canónico”
function sc_url_matches( $a, $b ) {
  return sc_canonicalize_url( $a ) === sc_canonicalize_url( $b );
}


/**
 * Borra completamente una Smart Card:
 * - Tarjeta del dashboard
 * - Página del perfil público
 * - Foto del perfil de la biblioteca
 * - Foto de la portada de la biblioteca
 * - Archivo VCF generado
 */
add_action('wp_ajax_sc_reject_profile', 'sc_reject_profile_cb');
add_action('wp_ajax_borrar_smartcard_perfil', 'borrar_smartcard_perfil');
function sc_reject_profile_cb() {
  if ( ! is_user_logged_in() ) {
    wp_send_json_error(['msg' => 'Debes iniciar sesión.']);
  }

  $user_id   = get_current_user_id();
  $perfil_url = isset($_POST['perfil_url']) ? esc_url_raw($_POST['perfil_url']) : '';
  if ( ! $perfil_url ) {
    wp_send_json_error(['msg' => 'URL del perfil no recibida.']);
  }

  // Canoniza la URL recibida
$perfil_url_raw = isset($_POST['perfil_url']) ? esc_url_raw($_POST['perfil_url']) : '';
$perfil_url     = sc_canonicalize_url( $perfil_url_raw );
if ( ! $perfil_url ) {
  wp_send_json_error(['msg' => 'URL del perfil no recibida.']);
}

  // 0) Resolver post_id de la página pública creada
  $page_id = url_to_postid( $perfil_url );

  // 1) +1 crédito
  $meta_key = 'smartcards_credits';
  $credits  = (int) get_user_meta($user_id, $meta_key, true);
  $new_credits = $credits + 1;
  update_user_meta($user_id, $meta_key, $new_credits);

  // 2) Borrar foto de perfil (si existe)
  $foto_perfil_id = (int) get_user_meta($user_id, 'smartcards_foto_perfil_id', true);
  if ( $foto_perfil_id ) {
    wp_delete_attachment($foto_perfil_id, true);
    delete_user_meta($user_id, 'smartcards_foto_perfil_id');
  }

  // 3) Borrar foto de portada (si existe)
  $foto_portada_id = (int) get_user_meta($user_id, 'smartcards_foto_portada_id', true);
  if ( $foto_portada_id ) {
    wp_delete_attachment($foto_portada_id, true);
    delete_user_meta($user_id, 'smartcards_foto_portada_id');
  }

  // 4) Borrar VCF generado (si existe)
  $vcf_attachment_id = (int) get_user_meta($user_id, 'smartcards_vcf_attachment_id', true);
  if ( $vcf_attachment_id ) {
    wp_delete_attachment($vcf_attachment_id, true);
    delete_user_meta($user_id, 'smartcards_vcf_attachment_id');
  }

  // 5) Borrar página pública creada
  if ( $page_id ) {
    wp_delete_post($page_id, true);
  }

  // 6) Remover tarjeta del dashboard (lista de perfiles del usuario)
  $perfiles_urls = get_user_meta($user_id, 'smartcards_perfiles_urls', true);
if ( ! is_array($perfiles_urls) ) { $perfiles_urls = []; }

$updated = [];
foreach ( $perfiles_urls as $item ) {
  $candidate_url = '';
  if ( isset( $item['url'] ) ) {
    $candidate_url = sc_get_live_profile_url( $item['url'] );
  }

  // Conserva los que NO coinciden (comparación canónica)
  if ( '' === $candidate_url || ! sc_url_matches( $candidate_url, $perfil_url ) ) {
    $updated[] = $item;
  }
}
update_user_meta($user_id, 'smartcards_perfiles_urls', $updated);
  

  // 7) Respuesta
  wp_send_json_success([
    'msg'      => 'Se devolvió 1 crédito, se eliminó el perfil y los archivos asociados.',
    'credits'  => $new_credits,
    'redirect' => home_url('/dashboard/'),
  ]);
}

/**
 * Borrado manual desde el dashboard:
 * - Remueve la tarjeta del listado (smartcards_perfiles_urls)
 * - Borra la página del perfil público
 * - Borra foto de perfil, foto de portada y VCF si existen
 *
 * Requiere que existan los helpers:
 *   sc_canonicalize_url($url) y sc_url_matches($a, $b)
 */
function borrar_smartcard_perfil() {
  if ( ! is_user_logged_in() ) {
    wp_send_json_error(['message' => 'Debes iniciar sesión.']);
  }

  $user_id = get_current_user_id();

  // Permite JSON (fetch con body) o form-data
  $raw        = file_get_contents('php://input');
  $datos      = json_decode($raw, true);
  $perfil_url = isset($_POST['perfil_url']) ? $_POST['perfil_url'] : ( $datos['perfil_url'] ?? '' );
  $perfil_url = sc_canonicalize_url( esc_url_raw( $perfil_url ) );

  if ( ! $perfil_url ) {
    wp_send_json_error(['message' => 'URL del perfil no recibida.']);
  }

  // 1) Borrar página pública (por URL canónica)
  $page_id = url_to_postid( $perfil_url );
  if ( $page_id ) {
    wp_delete_post($page_id, true); // permanente
  }

  // 2) Borrar foto de perfil
  $foto_perfil_id = (int) get_user_meta($user_id, 'smartcards_foto_perfil_id', true);
  if ( $foto_perfil_id ) {
    wp_delete_attachment($foto_perfil_id, true);
    delete_user_meta($user_id, 'smartcards_foto_perfil_id');
  }

  // 3) Borrar foto de portada
  $foto_portada_id = (int) get_user_meta($user_id, 'smartcards_foto_portada_id', true);
  if ( $foto_portada_id ) {
    wp_delete_attachment($foto_portada_id, true);
    delete_user_meta($user_id, 'smartcards_foto_portada_id');
  }

  // 4) Borrar VCF
  $vcf_attachment_id = (int) get_user_meta($user_id, 'smartcards_vcf_attachment_id', true);
  if ( $vcf_attachment_id ) {
    wp_delete_attachment($vcf_attachment_id, true);
    delete_user_meta($user_id, 'smartcards_vcf_attachment_id');
  }

  // 5) Remover tarjeta del dashboard (comparación canónica)
  $perfiles_urls = get_user_meta($user_id, 'smartcards_perfiles_urls', true);
  $removed = false;
  if ( is_array($perfiles_urls) && ! empty($perfiles_urls) ) {
    $updated = [];
    foreach ( $perfiles_urls as $item ) {
      $candidate_url = '';
      if ( isset( $item['url'] ) ) {
        $candidate_url = sc_get_live_profile_url( $item['url'] );
      }

      if ( $candidate_url && sc_url_matches( $candidate_url, $perfil_url ) ) {
        $removed = true;
        continue; // no lo copiamos => eliminado
      }
      $updated[] = $item;
    }
    update_user_meta($user_id, 'smartcards_perfiles_urls', $updated);
  }

  if ( $removed || $page_id ) {
    wp_send_json_success(['message' => 'Smart Card eliminada.']);
  }

  wp_send_json_error(['message' => 'No se encontró el perfil solicitado.']);
}


// Endpoint REST Protegido para Crear Usuarios
add_action('rest_api_init', function () {
    register_rest_route('smartcards/v1', '/create-user', [
        'methods' => WP_REST_Server::CREATABLE,
        'callback' => 'smartcards_create_user',
        'permission_callback' => function (WP_REST_Request $request) {
            $token = $request->get_header('X-SMARTCARDS-TOKEN');
            return $token === 'n2#XfP9!kz$H6V@Bs%cTm*a7G^wYjD';
        },
    ]);
});

function smartcards_create_user(WP_REST_Request $request) {
    $params = $request->get_json_params();

    if (empty($params['username']) || empty($params['email']) || empty($params['password'])) {
        return new WP_REST_Response(['message' => 'Faltan datos obligatorios.'], 400);
    }

    $user_id = username_exists($params['username']);

    if (!$user_id) {
        $user_id = wp_create_user(
            sanitize_user($params['username']),
            sanitize_text_field($params['password']),
            sanitize_email($params['email'])
        );

        if (is_wp_error($user_id)) {
            return new WP_REST_Response(['message' => $user_id->get_error_message()], 500);
        }

        wp_update_user([
            'ID' => $user_id,
            'first_name' => sanitize_text_field($params['first_name']),
            'last_name' => sanitize_text_field($params['last_name']),
            'role' => 'customer',
        ]);
    }

    // Sumar claramente los créditos si ya existen
    $creditos_actuales = (int)get_user_meta($user_id, 'smartcards_credits', true);
    $creditos_nuevos = isset($params['smartcards_credits']) ? intval($params['smartcards_credits']) : 0;
    $total_creditos = $creditos_actuales + $creditos_nuevos;

    update_user_meta($user_id, 'smartcards_credits', $total_creditos);

    return new WP_REST_Response([
        'message' => 'Usuario creado correctamente.',
        'total_creditos' => $total_creditos
    ], 200);
}

// Shortcode Mejorado para Mostrar Productos Externos
add_shortcode('productos_externos', 'smartcards_mostrar_productos_externos');

function smartcards_mostrar_productos_externos() {
    $response = wp_remote_get('https://staging.smartcards.com.co/wp-json/smartcards/v1/products', ['timeout' => 30]);

    if (is_wp_error($response)) {
        return '<p>Error al cargar productos externos.</p>';
    }

    $data = json_decode(wp_remote_retrieve_body($response), true);
    if (empty($data['products'])) {
        return '<p>No hay productos disponibles en este momento.</p>';
    }

    $html = '<div class="smartcards-productos">';
    foreach ($data['products'] as $product) {
        $html .= sprintf(
            '<div class="smartcard-product">
                <img src="%1$s" alt="%2$s">
                <h3>%2$s</h3>
                <p>%3$s</p>
                <span class="price">$%4$s</span>
                <a href="%5$s" target="_blank" class="button">Ver Producto</a>
            </div>',
            esc_url($product['image']),
            esc_html($product['name']),
            esc_html($product['description']),
            number_format_i18n($product['price'], 2),
            esc_url($product['url'])
        );
    }
    $html .= '</div>';

    return $html;
}

// ——————————————————————————————————————————————
// 1) Desactivar créditos al registrarse en la App
// ——————————————————————————————————————————————
add_action( 'init', function() {
    remove_action( 'user_register', 'asignar_creditos_nuevo_usuario' );
} );

// ——————————————————————————————————————————————
// 2) Sumar créditos al completar un pedido en la App
// ——————————————————————————————————————————————
add_action( 'woocommerce_order_status_completed', 'sc_creditos_por_pedido', 10, 1 );
function sc_creditos_por_pedido( $order_id ) {
    $order   = wc_get_order( $order_id );
    $user_id = $order->get_user_id();
    if ( ! $user_id ) {
        return; // si es invitado, no hacemos nada
    }

    $total_creditos = 0;

    foreach ( $order->get_items() as $item ) {
        $product = $item->get_product();
        $sku     = $product->get_sku();
        $prod_id = $product->get_id();
        $qty     = $item->get_quantity();

        // — Créditos sueltos (producto ID 1935) —
        if ( $prod_id === 1935 ) {
            $total_creditos += $qty;
        }
        // — Paquetes antiguos (SKU "creditos-smartcards-X") —
        elseif ( preg_match( '/^creditos-smartcards-(\d+)$/', $sku, $m ) ) {
            $packs = intval( $m[1] );
            $total_creditos += $packs * $qty;
        }
    }

    if ( $total_creditos > 0 ) {
        $actual = (int) get_user_meta( $user_id, 'smartcards_credits', true );
        update_user_meta( $user_id, 'smartcards_credits', $actual + $total_creditos );
    }
}

// ——————————————————————————————————————————————
// Redirigir al carrito al añadir el crédito suelto (ID 1935)
// ——————————————————————————————————————————————
add_filter( 'woocommerce_add_to_cart_redirect', 'sc_redirect_credito_to_cart', 20 );
function sc_redirect_credito_to_cart( $url ) {
    if ( isset( $_REQUEST['add-to-cart'] ) && absint( $_REQUEST['add-to-cart'] ) === 1935 ) {
        return wc_get_cart_url();
    }
    return $url;
}

/**
 * Agrega créditos al usuario cuando compra productos específicos.
 * Hook a la acción cuando el pedido cambia a “completedo”.
 * Aquí verificamos los productos comprados y sumamos créditos por cada unidad.
 */
add_action( 'woocommerce_order_status_completed', 'sc_agregar_creditos_por_producto', 10, 1 );

function sc_agregar_creditos_por_producto( $order_id ) {
    // Obtener el objeto del pedido
    $order = wc_get_order( $order_id );
    if ( ! $order ) {
        return;
    }

    // ID del usuario que hizo el pedido
    $user_id = $order->get_user_id();
    // Si el pedido fue hecho como invitado (user_id == 0), no hacemos nada
    if ( $user_id === 0 ) {
        return;
    }

    // Lista de IDs de productos que generan 1 crédito cada unidad comprada
    $productos_con_credito = array( 1922, 1927, 1928, 1926, 1925, 1924, 1923, 1934, 1933, 1932, 1929, 1931, 1930 );

    // Obtener créditos actuales del usuario (meta: smartcards_credits)
    $current_credits = (int) get_user_meta( $user_id, 'smartcards_credits', true );

    // Variable para acumular nuevos créditos a asignar
    $nuevos_creditos = 0;

    // Recorrer cada ítem del pedido y ver si corresponde a un producto de la lista
    foreach ( $order->get_items() as $item ) {
        $product_id = $item->get_product_id();
        $cantidad   = $item->get_quantity();

        if ( in_array( $product_id, $productos_con_credito, true ) ) {
            // Sumar una unidad de crédito por cada unidad comprada
            $nuevos_creditos += $cantidad;
        }
    }

    // Si compró al menos un producto que da crédito, actualizamos su meta
    if ( $nuevos_creditos > 0 ) {
        $total = $current_credits + $nuevos_creditos;
        update_user_meta( $user_id, 'smartcards_credits', $total );
    }
}

//Añadir la opción eliminar cuenta.
add_action('admin_post_eliminar_cuenta_usuario', function(){
  if (!is_user_logged_in() || !wp_verify_nonce($_REQUEST['_wpnonce'],'eliminar_cuenta')) {
    wp_die(__('Acceso denegado','smartcards'));
  }
  wp_delete_user(get_current_user_id());
  wp_redirect(home_url('/'));
  exit;
});

// Cargar las traducciones para activar modo Inglés y Español
add_action( 'plugins_loaded', function(){
    load_plugin_textdomain(
        'smartcards',
        false,
        dirname( plugin_basename(__FILE__) ) . '/languages'
    );
});

// Forzar el locale con la cookie sc_user_lang
add_filter( 'locale', function( $locale ){
    // Si el usuario ya eligió idioma, lo usamos
    if ( ! empty( $_COOKIE['sc_user_lang'] ) ) {
        $lang = sanitize_text_field( $_COOKIE['sc_user_lang'] );
        // Sólo permitimos es_ES o en_US
        if ( in_array( $lang, [ 'es_ES', 'en_US' ], true ) ) {
            return $lang;
        }
    }
    // Si no hay cookie o es inválida, devolvemos el locale original
    return $locale;
});

// ——————————————————————————————
// Encolar el listener del toggle de idioma
// ——————————————————————————————
add_action( 'wp_enqueue_scripts', function(){
    // Asegúrate de que tu script principal ya esté encolado
    wp_enqueue_script(
        'smartcards-frontend',
        SMARTCARDS_PLUGIN_URL . 'includes/assets/js/smartcards-frontend.js',
        [],
        '1.0',
        true
    );

    // Inline script para el toggle que guarda la cookie indefinidamente
    $toggle_js = <<<'JS'
document.addEventListener('DOMContentLoaded', function(){
  var toggle = document.getElementById('sc_lang_toggle');
  if (!toggle) return;
  // Inicializar estado según cookie
  var cookies = document.cookie.split('; ').reduce(function(acc, pair){
    var parts = pair.split('=');
    acc[parts[0]] = parts[1];
    return acc;
  }, {});
  if (cookies['sc_user_lang'] === 'en_US') {
    toggle.checked = true;
  }
  // Listener para cambios
  toggle.addEventListener('change', function(){
    var lang    = this.checked ? 'en_US' : 'es_ES';
    var expires = 'expires=Fri, 31 Dec 9999 23:59:59 GMT';
    document.cookie = 'sc_user_lang=' + lang + '; path=/; ' + expires;
    location.reload();
  });
});
JS;
    wp_add_inline_script( 'smartcards-frontend', $toggle_js );
});

// ===========================
// Encolar script y pasar traducciones del archivo .js
// ===========================
function smartcards_cargar_scripts() {
  wp_enqueue_script(
    'smartcards-frontend',
    plugin_dir_url(__FILE__) . 'smartcards-frontend.js',
    [],
    '1.0.0',
    true
  );
  wp_localize_script('smartcards-frontend', 'smartcardsL10n', [
  // Loading / botón
  'creating_profile'        => __('Creando Perfil Público...', 'smartcards'),
  'waiting_time'            => sprintf(__('Tiempo de espera %s segundos', 'smartcards'), 15),
  'error_creating_profile'  => __('Error al activar perfil.', 'smartcards'),
  'generate_profile'        => __('Generar Perfil Público', 'smartcards'),
  'request_error'           => __('Error en la petición.', 'smartcards'),

  // Modal de revisión post-creación (NUEVO)
  'review_modal_message'    => __('🔔 Verifica que todos los detalles de tu perfil público estén correctos, incluyendo el botón “Guardar contacto”, los botones sociales, la foto de portada y la foto de perfil.', 'smartcards'),
  'review_ok'               => __('Aprobado ✅', 'smartcards'),
  'review_issue'            => __('Mi perfil tiene un error ❌', 'smartcards'),

  // Compartir
  'share_title'             => __('Compartir perfil', 'smartcards'),
  'whatsapp'                => __('WhatsApp 📲', 'smartcards'),
  'copy_link'               => __('Copiar enlace 🔗', 'smartcards'),
  'email'                   => __('Correo electrónico ✉️', 'smartcards'),
  'close'                   => __('Cerrar ✖', 'smartcards'),
  'copied_link'             => __('✅ Enlace copiado al portapapeles.', 'smartcards'),
  'email_subject'           => __('Mi Smart Cards', 'smartcards'),
  'email_body'              => __('Mira mi perfil público aquí:', 'smartcards'),

  // Eliminar tarjeta (si lo usas)
  'delete_card'             => __('Eliminar Tarjeta 🗑', 'smartcards'),
  'cancel'                  => __('Cancelar', 'smartcards'),
  'confirm_delete'          => __('⚠️ ¿Seguro que deseas eliminar esta tarjeta? Esta acción no se puede deshacer.', 'smartcards'),
  'deleted_success'         => __('✅ Tarjeta eliminada correctamente.', 'smartcards'),
  'deleted_error'           => __('❌ Error al eliminar tarjeta.', 'smartcards'),

  // Validaciones de imágenes (si lo usas)
  'img_too_big_profile'     => __('El archivo supera los 2 MB permitidos.', 'smartcards'),
  'img_too_big_cover'       => __('La foto de portada supera los 5 MB permitidos.', 'smartcards'),

  // SIEMPRE dentro de este mismo arreglo:
  'ajax_url'                => esc_url( admin_url('admin-ajax.php') ),
  'track_nonce'             => wp_create_nonce('sc_track_event'),
]);
}
add_action('wp_enqueue_scripts', 'smartcards_cargar_scripts');

/**
 * === SmartCards: Login nativo + Google OAuth (REST callback) ===
 * Pega este bloque en tu archivo principal del plugin.
 *
 * Requisitos:
 * - Archivo credenciales: wp-content/uploads/private/google-oauth.json
 *   con estructura estándar de Google ("web" -> client_id, client_secret, auth_uri/token_uri opcional).
 * - En Google Cloud, añade como Authorized redirect URI:
 *   https://app.smartcards.com.co/wp-json/smartcards/v1/google-callback
 *   (y el de app01 si lo usas: https://app01.smartcards.com.co/wp-json/smartcards/v1/google-callback)
 */

/* -----------------------------------------------------------
 * 1) Credenciales de Google (redirect_uri usando endpoint REST)
 * ----------------------------------------------------------- */
function sc_google_creds() {
  $path = WP_CONTENT_DIR . '/uploads/private/google-oauth.json';
  if (!file_exists($path)) return null;

  $json = json_decode(file_get_contents($path), true);
  if (!$json || !isset($json['web'])) return null;

  return [
    'client_id'     => $json['web']['client_id']     ?? '',
    'client_secret' => $json['web']['client_secret'] ?? '',
    'redirect_uri'  => rest_url('smartcards/v1/google-callback'),
    'auth_uri'      => $json['web']['auth_uri']      ?? 'https://accounts.google.com/o/oauth2/v2/auth',
    'token_uri'     => $json['web']['token_uri']     ?? 'https://oauth2.googleapis.com/token',
    'userinfo_uri'  => 'https://openidconnect.googleapis.com/v1/userinfo',
  ];
}

/* -----------------------------------------------------------
 * 2) Registro del endpoint REST para el callback de Google
 * ----------------------------------------------------------- */
add_action('rest_api_init', function () {
  register_rest_route('smartcards/v1', '/google-callback', [
    'methods'  => 'GET',
    'callback' => 'sc_google_callback',
    'permission_callback' => '__return_true',
  ]);
});

/* -----------------------------------------------------------
 * 3) Callback de Google: token -> userinfo -> login/crear usuario
 * ----------------------------------------------------------- */
function sc_google_callback(\WP_REST_Request $req) {
  // Error directo desde Google
  if ($err = $req->get_param('error')) {
    wp_safe_redirect( home_url('/?login_error=' . urlencode($err)) );
    exit;
  }

  // Validar state (CSRF)
  $state = sanitize_text_field($req->get_param('state'));
  $cookie_state = isset($_COOKIE['oauth_state']) ? sanitize_text_field($_COOKIE['oauth_state']) : '';
  if (!$state || !$cookie_state || !hash_equals($cookie_state, $state)) {
    return new \WP_REST_Response(['error'=>'Invalid state'], 400);
  }
  // Invalidar cookie state
  setcookie('oauth_state', '', [
    'expires'  => time()-3600,
    'path'     => '/',
    'domain'   => $_SERVER['HTTP_HOST'],
    'secure'   => is_ssl(),
    'httponly' => true,
    'samesite' => 'Lax'
  ]);

  // Code
  $code = sanitize_text_field($req->get_param('code'));
  if (!$code) return new \WP_REST_Response(['error'=>'Missing code'], 400);

  $c = sc_google_creds();
  if (!$c || empty($c['client_id']) || empty($c['client_secret'])) {
    return new \WP_REST_Response(['error'=>'OAuth creds not found'], 500);
  }

  // Intercambio code -> token
  $resp = wp_remote_post($c['token_uri'], [
    'body' => [
      'code'          => $code,
      'client_id'     => $c['client_id'],
      'client_secret' => $c['client_secret'],
      'redirect_uri'  => $c['redirect_uri'],
      'grant_type'    => 'authorization_code',
    ],
    'headers' => [ 'Content-Type' => 'application/x-www-form-urlencoded' ],
    'timeout' => 20
  ]);
  if (is_wp_error($resp)) return new \WP_REST_Response(['error'=>'Token request failed'], 500);

  $tok = json_decode(wp_remote_retrieve_body($resp), true);
  if (empty($tok['access_token'])) return new \WP_REST_Response(['error'=>'Invalid token response'], 500);

  // Userinfo (OIDC)
  $ui  = wp_remote_get($c['userinfo_uri'], [
    'headers' => [ 'Authorization' => 'Bearer ' . $tok['access_token'] ],
    'timeout'=> 20
  ]);
  if (is_wp_error($ui)) return new \WP_REST_Response(['error'=>'Userinfo request failed'], 500);

  $user = json_decode(wp_remote_retrieve_body($ui), true);
  if (empty($user['email'])) return new \WP_REST_Response(['error'=>'Email not returned'], 500);

  // Buscar o crear usuario por email
  $wp_user = get_user_by('email', $user['email']);
  if (!$wp_user) {
    $base   = sanitize_user( preg_replace('/@.*/','', $user['email']) ) ?: 'user';
    $login  = $base; $i = 1;
    while (username_exists($login)) { $login = $base . $i++; }

    $uid = wp_create_user($login, wp_generate_password(20), $user['email']);
    if (is_wp_error($uid)) {
      return new \WP_REST_Response(['error'=>'wp_create_user failed'], 500);
    }

    wp_update_user([
      'ID'           => $uid,
      'display_name' => $user['name'] ?? $user['email'],
      'first_name'   => $user['given_name'] ?? '',
      'last_name'    => $user['family_name'] ?? '',
      'role'         => 'subscriber',
    ]);

    if (!empty($user['sub']))     update_user_meta($uid, 'google_sub', $user['sub']);
    if (!empty($user['picture'])) update_user_meta($uid, 'google_picture', esc_url_raw($user['picture']));

    $wp_user = get_user_by('id', $uid);
  }

  // Iniciar sesión
  wp_set_auth_cookie($wp_user->ID, true, is_ssl());

  // Respetar redirect almacenado
  $fallback = home_url('/dashboard/');
  $redirect = $fallback;
  if (!empty($_COOKIE['after_login_redirect'])) {
    $tmp = esc_url_raw($_COOKIE['after_login_redirect']);
    $valid = wp_validate_redirect($tmp, $fallback);
    if ($valid) { $redirect = $valid; }
    setcookie('after_login_redirect', '', [
      'expires'  => time()-3600,
      'path'     => '/',
      'domain'   => $_SERVER['HTTP_HOST'],
      'secure'   => is_ssl(),
      'httponly' => true,
      'samesite' => 'Lax'
    ]);
  }

  wp_safe_redirect($redirect);
  exit;
}

/* -----------------------------------------------------------
 * 4) Guardián del dashboard: redirige a /app-login/ (no wp-login.php)
 * ----------------------------------------------------------- */
add_action('template_redirect', function () {
  if ( is_user_logged_in() ) return;

  $dashboard_slugs = ['dashboard', 'app-dashboard'];
  foreach ($dashboard_slugs as $slug) {
    if ( is_page($slug) ) {

      // Evitar bucles si ya estamos en la página de login personalizada
      if ( is_page('app-login') ) return;

      $dashboard_url = home_url("/{$slug}/");
      $login_page    = home_url('/app-login/');
      $login_url     = add_query_arg('redirect_to', rawurlencode($dashboard_url), $login_page);

      wp_safe_redirect($login_url);
      exit;
    }
  }
});

/* -----------------------------------------------------------
 * 5) Shortcode [smartcards_login]: login WP + botón Google
 * ----------------------------------------------------------- */
add_action('init', function () {
  add_shortcode('smartcards_login', 'sc_render_login_shortcode');
});

function sc_render_login_shortcode(){
  // Si ya hay sesión, evita mostrar formulario
  if ( is_user_logged_in() ) {
    $dash = home_url('/dashboard/');
    return '<p>Ya iniciaste sesión. <a href="'.esc_url($dash).'">Ir al dashboard</a></p>';
  }

  // Destino post-login (por defecto /dashboard/)
  $redirect = isset($_GET['redirect_to']) ? esc_url_raw($_GET['redirect_to']) : home_url('/dashboard/');

  // Guardar redirect en cookie
  setcookie('after_login_redirect', $redirect, [
    'expires'  => time()+3600,
    'path'     => '/',
    'domain'   => $_SERVER['HTTP_HOST'],
    'secure'   => is_ssl(),
    'httponly' => true,
    'samesite' => 'Lax'
  ]);

  // CSRF state
  $state = wp_generate_password(24, false, false);
  setcookie('oauth_state', $state, [
    'expires'  => time()+900,
    'path'     => '/',
    'domain'   => $_SERVER['HTTP_HOST'],
    'secure'   => is_ssl(),
    'httponly' => true,
    'samesite' => 'Lax'
  ]);

  // Credenciales para pintar botón Google
  $c = sc_google_creds();
  $google_auth_url = '';
  if ($c && !empty($c['client_id'])) {
    $google_auth_url = $c['auth_uri'] . '?' . http_build_query([
      'client_id'              => $c['client_id'],
      'redirect_uri'           => $c['redirect_uri'], // <- REST callback
      'response_type'          => 'code',
      'scope'                  => 'openid email profile',
      'include_granted_scopes' => 'true',
      'access_type'            => 'online',
      'state'                  => $state,
    ]);
  }

  // URL de registro personalizada con redirect de vuelta
  $register_url = add_query_arg(
    'redirect_to',
    rawurlencode($redirect),
    'https://app.smartcards.com.co/registro/'
  );

  // --- Salida HTML
  ob_start(); ?>
  <div class="sc-auth-wrap">
    <div class="sc-auth-card" role="form" aria-labelledby="sc-auth-title">
      <h2 id="sc-auth-title" class="sc-auth-title">Inicia sesión</h2>

      <div class="sc-auth-form">
        <?php
          // Formulario nativo WP (botón "Entrar" verde)
          wp_login_form([
            'redirect'       => $redirect,
            'remember'       => true,
            'label_username' => 'Correo o usuario',
            'label_password' => 'Contraseña',
            'label_remember' => 'Recuérdame',
            'label_log_in'   => 'Entrar',
            'id_submit'      => 'sc-login-submit',
          ]);
        ?>
      </div>

      <div class="sc-auth-forgot">
  <a href="<?php echo esc_url( wp_lostpassword_url( $redirect ) ); ?>">
    ¿Olvidaste tu contraseña?
  </a>
</div>

      <!-- Acción secundaria: Crear cuenta con correo -->
      <div class="sc-auth-actions">
        <a class="sc-secondary-btn" href="<?php echo esc_url($register_url); ?>">
          Crear cuenta con correo
        </a>
      </div>

      <?php if ($google_auth_url): ?>
        <!-- Separador -->
        <div class="sc-auth-divider" role="separator" aria-label="o"></div>

        <!-- Botón brand Google -->
        <a class="sc-google-btn" id="sc-google-btn"
           href="<?php echo esc_url($google_auth_url); ?>"
           rel="nofollow noopener">
          <span class="sc-google-icon" aria-hidden="true">
            <!-- Google "G" oficial (SVG) -->
            <svg width="18" height="18" viewBox="0 0 48 48" focusable="false" aria-hidden="true">
              <path fill="#EA4335" d="M24 9.5c3.54 0 6.72 1.22 9.22 3.6l6.9-6.9C36.9 2.38 30.9 0 24 0 14.62 0 6.4 5.38 2.54 13.22l8.02 6.22C12.5 12.38 17.8 9.5 24 9.5z"/>
              <path fill="#4285F4" d="M46.5 24c0-1.54-.14-3.02-.4-4.44H24v8.41h12.7c-.55 2.97-2.23 5.49-4.75 7.19l7.27 5.65C43.93 36.98 46.5 30.98 46.5 24z"/>
              <path fill="#FBBC05" d="M10.56 28.44A14.46 14.46 0 0 1 9.5 24c0-1.54.26-3.02.73-4.41l-8.02-6.22A24 24 0 0 0 0 24c0 3.86.92 7.5 2.54 10.78l8.02-6.34z"/>
              <path fill="#34A853" d="M24 48c6.48 0 11.92-2.14 15.9-5.83l-7.27-5.65c-2 1.35-4.57 2.14-8.63 2.14-6.2 0-11.5-3.88-13.44-9.16l-8.02 6.27C6.4 42.62 14.62 48 24 48z"/>
              <path fill="none" d="M0 0h48v48H0z"/>
            </svg>
          </span>
          <span class="sc-google-text">Continuar con Google</span>
        </a>
      <?php endif; ?>
      <div class="sc-auth-divider" role="separator" aria-label="o"></div>
      <!-- Botón brand Apple -->
      <button id="sc-apple-login"
        class="siwa-btn siwa-btn--pill"
        type="button" aria-label="Continuar con Apple">
  <span class="siwa-icon" aria-hidden="true">
    <!-- Logo Apple en SVG (blanco) -->
    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18"
         viewBox="0 0 14 17" fill="currentColor" role="img" focusable="false">
      <path d="M13.99 13.04c-.37.85-.81 1.57-1.35 2.17-.72.8-1.51 1.2-2.36 1.2-.45 0-1.01-.13-1.69-.38-.69-.26-1.33-.39-1.92-.39-.61 0-1.27.13-1.97.39-.7.25-1.25.38-1.64.38-.92 0-1.75-.38-2.48-1.14C.46 14.94 0 13.86 0 12.51c0-1.02.27-1.98.8-2.87.52-.89 1.22-1.34 2.09-1.34.41 0 .94.13 1.59.38.64.25 1.17.38 1.6.38.39 0 .94-.13 1.65-.38.71-.26 1.31-.39 1.79-.39.58 0 1.1.14 1.57.41.47.27.82.63 1.07 1.08-.85.53-1.27 1.28-1.27 2.26 0 .76.28 1.41.84 1.95.42.41.93.66 1.53.76zM9.96 0c0 .52-.19 1.02-.56 1.5-.45.58-1 .94-1.67.88a2 2 0 0 1 .02-.38c.13-.5.39-.97.77-1.4C8.97.2 9.51-.07 10.15 0c-.06.53-.19.95-.19 1z"/>
    </svg>
  </span>
  <span class="siwa-label">Continuar con Apple</span>
</button>
    </div>
  </div>
  <?php
  return ob_get_clean();
}

/* -----------------------------------------------------------
 * 6) (Opcional) Enqueue de estilos/JS del botón
 * ----------------------------------------------------------- */
add_action('wp_enqueue_scripts', function () {

  // Bases de ruta y URL dentro del plugin
  $base = plugin_dir_path(__FILE__) . 'includes/assets/';
  $url  = plugins_url('includes/assets/', __FILE__);

  // Rutas relativas de los assets
  $css_rel = 'css/smartcards-styles.css';
  $js_rel  = 'js/smartcards-frontend.js';

  $css_file = $base . $css_rel;
  $js_file  = $base . $js_rel;

  // CSS
  if ( file_exists($css_file) ) {
    wp_enqueue_style(
      'smartcards-styles',
      $url . $css_rel,
      [],
      filemtime($css_file) // ← versión dinámica (cache-busting)
    );
  }

  // JS
  if ( file_exists($js_file) ) {
    wp_enqueue_script(
      'smartcards-frontend',
      $url . $js_rel,
      [],
      filemtime($js_file), // ← versión dinámica (cache-busting)
      true
    );

    // Variable global que tu JS usa para AJAX
    //wp_localize_script('smartcards-frontend', 'smartcardsL10n', [
    //  'ajax_url' => admin_url('admin-ajax.php'),
    //]);
  }
}, 20);


/* === Fallback WooCommerce desde web/desktop === */
if ( ! function_exists('sc_wc_fallback_url_from_sku') ) {
  function sc_wc_fallback_url_from_sku( $sku ){
    // Mantén sincronizado con el $map del endpoint IAP
    $sku_to_product = [
      'creditos_smartcards_1'  => 1935, // 1 crédito
      'creditos_smartcards_5'  => 4946, // 5 créditos
      'creditos_smartcards_10' => 4947, // 10 créditos
    ];
    $pid = intval( $sku_to_product[ $sku ] ?? 0 );
    if ( ! $pid || ! function_exists('wc_get_checkout_url') ) return home_url('/');
    return wc_get_checkout_url() . '?add-to-cart=' . $pid;
  }
}

/* === Shortcodes con <a> y data-attrs para IAP/ fallback Woo === */
remove_shortcode('sc_iap_credito_uno');
add_shortcode('sc_iap_credito_uno', function(){
  $sku = 'creditos_smartcards_1';
  $url = esc_url( sc_wc_fallback_url_from_sku($sku) );
  ob_start(); ?>
  <a href="<?php echo $url; ?>"
     class="sc-button-featured js-iap-purchase"
     data-sku="<?php echo esc_attr($sku); ?>"
     data-fallback="<?php echo esc_attr($url); ?>">
    Comprar 1 crédito
  </a>
  <?php return ob_get_clean();
});

remove_shortcode('sc_iap_credito_cinco');
add_shortcode('sc_iap_credito_cinco', function(){
  $sku = 'creditos_smartcards_5';
  $url = esc_url( sc_wc_fallback_url_from_sku($sku) );
  ob_start(); ?>
  <a href="<?php echo $url; ?>"
     class="sc-button-featured js-iap-purchase"
     data-sku="<?php echo esc_attr($sku); ?>"
     data-fallback="<?php echo esc_attr($url); ?>">
    Comprar 5 créditos
  </a>
  <?php return ob_get_clean();
});

remove_shortcode('sc_iap_credito_diez');
add_shortcode('sc_iap_credito_diez', function(){
  $sku = 'creditos_smartcards_10';
  $url = esc_url( sc_wc_fallback_url_from_sku($sku) );
  ob_start(); ?>
  <a href="<?php echo $url; ?>"
     class="sc-button-featured js-iap-purchase"
     data-sku="<?php echo esc_attr($sku); ?>"
     data-fallback="<?php echo esc_attr($url); ?>">
    Comprar 10 créditos
  </a>
  <?php return ob_get_clean();
});

// SOLO usuarios logueados
add_action('wp_ajax_sc_sc_iap_complete', 'sc_sc_iap_complete');
// ❌ NO pongas el nopriv

function sc_sc_iap_complete(){
  if ( ! is_user_logged_in() ) {
    // Devuelve mensaje claro; el JS lo puede usar para redirigir
    wp_send_json_error(['code'=>'auth_required','message'=>'Debes iniciar sesión para completar la compra.']);
  }

  $raw = file_get_contents('php://input');
  $j   = json_decode($raw, true) ?: $_POST;

  $productId     = sanitize_text_field($j['productId'] ?? '');
  $transactionId = sanitize_text_field($j['transactionId'] ?? '');
  $platform      = strtolower(sanitize_text_field($j['platform'] ?? ''));
  $receipt       = $j['receipt'] ?? null;
  $purchaseToken = sanitize_text_field($j['purchaseToken'] ?? '');

  // Normaliza plataforma (solo aceptamos ios/android)
if ($platform !== 'ios' && $platform !== 'android') {
  wp_send_json_error(['message' => 'Plataforma web: usa la compra en WooCommerce.']);
}

  if (!$productId) wp_send_json_error(['message'=>'Producto inválido.']);
  if ($platform === 'ios' && empty($receipt))        wp_send_json_error(['message'=>'Recibo iOS faltante.']);
  if ($platform === 'android' && empty($purchaseToken)) wp_send_json_error(['message'=>'Token de compra Android faltante.']);

  $credits = 0;
  if ($productId === 'creditos_smartcards_1')  $credits = 1;
  if ($productId === 'creditos_smartcards_5')  $credits = 5;
  if ($productId === 'creditos_smartcards_10') $credits = 10;
  if ($credits <= 0) wp_send_json_error(['message'=>'SKU desconocido.']);

  $user_id = get_current_user_id();
  $current = (int) get_user_meta($user_id, 'smartcards_credits', true);
  update_user_meta($user_id, 'smartcards_credits', $current + $credits);

  add_user_meta($user_id, 'sc_iap_log', [
    'time'          => current_time('mysql'),
    'productId'     => $productId,
    'platform'      => $platform,
    'transactionId' => $transactionId,
    'purchaseToken' => $purchaseToken,
    'receipt_len'   => $receipt ? strlen(maybe_serialize($receipt)) : 0,
  ]);

  wp_send_json_success(['message'=>'Créditos acreditados']);
}

// Normaliza los args de wc_price para evitar "Undefined array key 'in_span'"
add_filter('wc_price_args', function($args){
  $defaults = array(
    'ex_tax_label'       => false,
    'currency'           => get_woocommerce_currency(),
    'decimal_separator'  => wc_get_price_decimal_separator(),
    'thousand_separator' => wc_get_price_thousand_separator(),
    'decimals'           => wc_get_price_decimals(),
    'price_format'       => get_woocommerce_price_format(),
    'currency_symbol'    => get_woocommerce_currency_symbol(),
    'in_span'            => true, // 👈 clave faltante en algunos filtros antiguos
  );
  // Combina sin pisar lo que ya venga:
  return wp_parse_args( $args, $defaults );
}, 99);

// Ruta base del plugin (más portable que __DIR__ a futuro)
if ( ! defined('SMARTCARDS_PLUGIN_DIR') ) {
  define('SMARTCARDS_PLUGIN_DIR', plugin_dir_path(__FILE__));
}
if ( ! defined('SMARTCARDS_PLUGIN_URL') ) {
  define('SMARTCARDS_PLUGIN_URL', plugin_dir_url(__FILE__));
}

/**
 * (Compatibilidad) Si en algún archivo antiguo se usa SC_PLUGIN_DIR,
 * lo “alias” apuntando a SMARTCARDS_PLUGIN_DIR para evitar fatales.
 */
if ( ! defined('SC_PLUGIN_DIR') ) {
  define('SC_PLUGIN_DIR', SMARTCARDS_PLUGIN_DIR);
}

/**
 * Carga segura de shortcodes.
 * - Verifica existencia del archivo
 * - Evita fatales en producción
 * - Muestra aviso en el admin si falta el archivo
 */
add_action('plugins_loaded', function () {
    $shortcodes_file = SC_PLUGIN_DIR . 'includes/shortcodes.php';

    if ( file_exists( $shortcodes_file ) ) {
        require_once $shortcodes_file; // ← tu línea original, pero con ruta segura
    } else {
        // Aviso solo para administradores
        if ( is_admin() ) {
            add_action('admin_notices', function () use ($shortcodes_file) {
                echo '<div class="notice notice-error"><p><strong>SmartCards Plugin:</strong> No se encontró <code>' . esc_html( $shortcodes_file ) . '</code>. Verifica la ruta.</p></div>';
            });
        }
        // Opcional: registra un log para debug
        if ( function_exists('error_log') ) {
            error_log('[SmartCards] Faltante: ' . $shortcodes_file);
        }
    }
}, 5); // prioridad baja: se carga temprano

//Encolar el CSS/JS del diagnostico
add_action('wp_enqueue_scripts', function () {
    // Carga solo si el contenido tiene el shortcode
    if (is_singular() && has_shortcode(get_post_field('post_content', get_the_ID()), 'sc_iap_diag')) {
        wp_enqueue_style(
            'sc-iap-diag',
            plugins_url('includes/assets/css/sc-iap-diag.css', __FILE__),
            [],
            '1.0'
        );
        wp_enqueue_script(
            'sc-iap-diag',
            plugins_url('includes/assets/js/sc-iap-diag.js', __FILE__),
            [],
            '1.0',
            true
        );
    }
});

// GET /wp-json/smartcards/v1/credits/{id} 
// Crea una puerta de entrada (endpoint REST) en tu WordPress para leer los créditos de un usuario.
add_action('rest_api_init', function () {
  register_rest_route('smartcards/v1', '/credits/(?P<id>\d+)', [
    'methods'  => WP_REST_Server::READABLE,
    'args'     => [
      'id' => [
        'description'       => __('User ID', 'smartcards'),
        'type'              => 'integer',
        'required'          => true,
        'validate_callback' => function($value) {
          return is_numeric($value) && (int)$value > 0;
        },
      ],
    ],
    'permission_callback' => function (WP_REST_Request $req) {
      $uid = (int) $req['id'];

      // ✅ Si usas JWT: asume que el usuario ya está autenticado.
      // Permite al propio usuario o a un admin/manager ver créditos.
      if (is_user_logged_in()) {
        $current_id = get_current_user_id();
        if ($current_id === $uid || current_user_can('manage_options') || current_user_can('list_users')) {
          return true;
        }
      }

      // También puedes permitir con un token propio (ej. en header)
      // $token = $req->get_header('x-smartcards-token'); // TODO: validar

      return new WP_Error('forbidden', __('No tienes permisos para ver estos créditos.', 'smartcards'), ['status' => 403]);
    },
    'callback' => function (WP_REST_Request $req) {
      $uid = (int) $req['id'];
      $user = get_user_by('ID', $uid);

      if (!$user) {
        return new WP_Error('invalid_user', __('Usuario no válido', 'smartcards'), ['status' => 404]);
      }

      $credits = (int) get_user_meta($uid, 'smartcards_credits', true);
      $used    = (int) get_user_meta($uid, 'smartcards_credits_used', true);
      $updated = get_user_meta($uid, 'smartcards_credits_updated_at', true);

      $payload = [
        'user_id'    => $uid,
        'credits'    => $credits,
        'used'       => $used,
        'updated_at' => $updated ?: null,
      ];

      return rest_ensure_response($payload);
    },
  ]);
});

//Crear endpoint en WordPress /smartcards/v1/register
add_action('rest_api_init', function () {
    register_rest_route('smartcards/v1', '/register', [
        'methods'  => 'POST',
        'callback' => 'sc_register_user',
        'permission_callback' => '__return_true'
    ]);
});

function sc_register_user($req) {
    $email = sanitize_email($req['email']);
    $pass  = sanitize_text_field($req['password']);

    if (!is_email($email)) {
        return new WP_Error('invalid_email', 'Email inválido', ['status' => 400]);
    }

    if (email_exists($email)) {
        return new WP_Error('email_exists', 'Este correo ya está registrado.', ['status' => 400]);
    }

    $user_id = wp_create_user($email, $pass, $email);

    if (is_wp_error($user_id)) {
        return new WP_Error('create_failed', $user_id->get_error_message(), ['status' => 400]);
    }

    // Autologin → crear token JWT automáticamente
    $token = sc_generate_jwt_token($email, $pass);

    return [
        'success' => true,
        'token'   => $token,
        'user_id' => $user_id
    ];
}

function sc_generate_jwt_token($email, $password) {
    $response = wp_remote_post(home_url('/wp-json/jwt-auth/v1/token'), [
        'body' => [
            'username' => $email,
            'password' => $password
        ]
    ]);

    $body = json_decode(wp_remote_retrieve_body($response), true);
    return $body['token'] ?? null;
}

// Crear endpoint para obtener las Smart Cards del usuario logueado
add_action('rest_api_init', function () {
    register_rest_route('smartcards/v1', '/my-cards', [
        'methods'  => 'GET',
        'callback' => 'sc_get_my_cards',
        'permission_callback' => function () {
            return is_user_logged_in();
        }
    ]);
});

/**
 * Endpoint: /wp-json/smartcards/v1/my-cards
 * Devuelve las Smart Cards del usuario logueado
 * Fuente REAL: user_meta smartcards_perfiles_urls
 */
function sc_get_my_cards(WP_REST_Request $request) {

    $user_id = get_current_user_id();

    if (!$user_id) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'No autorizado'
        ], 401);
    }

    // 📦 Obtener perfiles desde user_meta
    $perfiles = get_user_meta($user_id, 'smartcards_perfiles_urls', true);

    if (!is_array($perfiles)) {
        $perfiles = [];
    }

    $cards = [];

    foreach ($perfiles as $index => $perfil) {

        // Seguridad básica
        $url   = isset($perfil['url']) ? sc_get_live_profile_url($perfil['url']) : '';
        $name  = isset($perfil['nombre_completo']) ? sanitize_text_field($perfil['nombre_completo']) : '';
        $photo = isset($perfil['foto_perfil']) ? esc_url_raw($perfil['foto_perfil']) : '';

        if (!$url || !$name) {
            continue;
        }

        $cards[] = [
            'id'          => $index + 1,        // ID lógico (no post_id)
            'name'        => $name,
            'photo'       => $photo ?: '',
            'profile_url' => $url,
            'perfil_url'  => $url,
            'status'      => 'active',          // En tu flujo actual siempre es activo
        ];
    }

    return new WP_REST_Response($cards, 200);
}

/**
 * =========================================================
 * REGISTRO DEL ENDPOINT REST PARA ELIMINAR SMART CARDS
 * =========================================================
 *
 * Este endpoint se usa desde la app móvil (React Native)
 * para eliminar completamente una Smart Card del usuario.
 *
 * Ruta final:
 *   POST /wp-json/smartcards/v1/delete-card
 */
add_action('rest_api_init', function () {
  register_rest_route('smartcards/v1', '/delete-card', [
    'methods'  => 'POST',
    'callback' => 'sc_delete_smartcard_rest',
    'permission_callback' => function () {
      return is_user_logged_in();
    },
  ]);
});

function sc_delete_smartcard_rest(WP_REST_Request $request) {

  $user_id = get_current_user_id();
  if (!$user_id) {
    return new WP_REST_Response(['message' => 'No autorizado'], 401);
  }

  $perfil_url = esc_url_raw($request->get_param('perfil_url'));
  if (!$perfil_url) {
    return new WP_REST_Response(['message' => 'URL no recibida'], 400);
  }

  // Canonizar URL
  $perfil_url = sc_canonicalize_url($perfil_url);

  // 1️⃣ Eliminar página pública
  $page_id = url_to_postid($perfil_url);
  if ($page_id) {
    wp_delete_post($page_id, true);
  }

  // 2️⃣ Eliminar foto de perfil
  $foto_perfil_id = (int) get_user_meta($user_id, 'smartcards_foto_perfil_id', true);
  if ($foto_perfil_id) {
    wp_delete_attachment($foto_perfil_id, true);
    delete_user_meta($user_id, 'smartcards_foto_perfil_id');
  }

  // 3️⃣ Eliminar foto de portada
  $foto_portada_id = (int) get_user_meta($user_id, 'smartcards_foto_portada_id', true);
  if ($foto_portada_id) {
    wp_delete_attachment($foto_portada_id, true);
    delete_user_meta($user_id, 'smartcards_foto_portada_id');
  }

  // 4️⃣ Eliminar VCF
  $vcf_id = (int) get_user_meta($user_id, 'smartcards_vcf_attachment_id', true);
  if ($vcf_id) {
    wp_delete_attachment($vcf_id, true);
    delete_user_meta($user_id, 'smartcards_vcf_attachment_id');
  }

  // 5️⃣ Eliminar del listado del usuario
  $perfiles = get_user_meta($user_id, 'smartcards_perfiles_urls', true);
  if (is_array($perfiles)) {
    $updated = [];
    foreach ($perfiles as $item) {
      $candidate_url = '';
      if ( isset( $item['url'] ) ) {
        $candidate_url = sc_get_live_profile_url( $item['url'] );
      }

      if ( !$candidate_url || !sc_url_matches($candidate_url, $perfil_url)) {
        $updated[] = $item;
      }
    }
    update_user_meta($user_id, 'smartcards_perfiles_urls', $updated);
  }

  return new WP_REST_Response([
    'success' => true,
    'message' => 'Smart Card eliminada correctamente'
  ], 200);
}

add_action('template_redirect', function(){
    if (is_page()) {
        global $post;

        if ($post && strpos($post->post_content, 'perfil-publico') !== false) {

            if (!defined('DONOTCACHEPAGE')) {
                define('DONOTCACHEPAGE', true);
            }

            if (!defined('DONOTCACHEDB')) {
                define('DONOTCACHEDB', true);
            }

            if (!defined('DONOTCACHEOBJECT')) {
                define('DONOTCACHEOBJECT', true);
            }
        }
    }
});

add_filter('the_content', function($content){

    if (!is_page()) {
        return $content;
    }

    global $post;

    if (!$post) {
        return $content;
    }

    // Solo aplicar si es un perfil (tiene owner)
    $owner = get_post_meta($post->ID, 'sc_owner_user_id', true);

    if (!$owner) {
        return $content;
    }

    // Si ya tiene atributo, no duplicar
    if (strpos($content, 'data-sc-profile-id') !== false) {
        return $content;
    }

    // Inyectar atributo en el div principal
    $content = str_replace(
        'class="perfil-publico"',
        'class="perfil-publico" data-sc-profile-id="' . esc_attr($post->ID) . '"',
        $content
    );

    return $content;
}, 20);

add_filter('the_content', function($content){

    if (!is_page()) {
        return $content;
    }

    global $post;

    if (!$post) {
        return $content;
    }

    $owner = get_post_meta($post->ID, 'sc_owner_user_id', true);

    if (!$owner) {
        return $content;
    }

    // Solo si no tiene ya el atributo
    if (strpos($content, 'data-sc-event="button_click"') !== false) {
        return $content;
    }

    // Inyectar en botones sociales
    $content = preg_replace(
        '/class="btn-red-social ([^"]+)"/',
        'class="btn-red-social $1" data-sc-event="button_click" data-sc-button="$1"',
        $content
    );

    return $content;

}, 21);

add_filter('template_include', 'sc_load_profile_template');

function sc_load_profile_template($template){

    if (is_singular('smartcards')) {

        global $post;

        $source = get_post_meta($post->ID, 'sc_source', true);

        // Solo perfiles creados desde la app
        if ($source === 'app') {

            $plugin_template = plugin_dir_path(__FILE__) . 'templates/profile-template.php';

            if (file_exists($plugin_template)) {
                return $plugin_template;
            }

        }

    }

    return $template;
}
