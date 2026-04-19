<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Evita el acceso directo
}

function procesar_formulario() {
    check_admin_referer( 'smartcards_form_nonce', 'smartcards_form_nonce_field' );

    if ( ! is_user_logged_in() ) {
        wp_die(
            esc_html__( 'No tienes permisos para realizar esta acción.', 'smartcards' )
        );
    }

    $user_id = get_current_user_id();

    error_log('POST DEBUG: ' . print_r($_POST, true));

    // Verificar créditos disponibles
    $credits = (int) get_user_meta( $user_id, 'smartcards_credits', true );
    if ( $credits <= 0 ) {
        wp_send_json_error( [
            'message' => '🚫 No tienes créditos disponibles. Compra un crédito para crear tu perfil.',
        ] );
    }

    // Restar un crédito
    update_user_meta($user_id, 'smartcards_credits', $credits - 1);

    // 3. Definir la lista de campos que deseamos sobrescribir con vacío si no se envían
    $lista_campos = [
        // Datos personales
        'nombre',
        'apellido',
        'empresa',
        'cargo',
        'correo_electronico',
        'telefono',
        'telefono2',
        'pais',
        'direccion',
        'ciudad',
        'departamento',
        'codigo_postal',
        'sitio_web',
        'notas',
        // Redes sociales
        'whatsapp',
        'instagram',
        'facebook',
        'x',
        'tiktok',
        'linkedin',
        'youtube',
        'google_maps',
        'wompi',
        'epayco',
        'paypal'
    ];

    // Sanitizar datos personales
    $datos_personales = [
        'nombre'        => sanitize_text_field($_POST['nombre'] ?? ''),
        'apellido'      => sanitize_text_field($_POST['apellido'] ?? ''),
        'cargo'         => sanitize_text_field($_POST['cargo'] ?? ''),
        'empresa'       => sanitize_text_field($_POST['empresa'] ?? ''),
        'correo'        => sanitize_email($_POST['correo_electronico'] ?? ''),
        'telefono'      => sanitize_text_field($_POST['telefono'] ?? ''),
        'telefono2'     => sanitize_text_field($_POST['telefono2'] ?? ''),
        'direccion'     => sanitize_text_field($_POST['direccion'] ?? ''),
        'ciudad'        => sanitize_text_field($_POST['ciudad'] ?? ''),
        'departamento'  => sanitize_text_field($_POST['departamento'] ?? ''),
        'codigo_postal' => sanitize_text_field($_POST['codigo_postal'] ?? ''),
        'pais'          => sanitize_text_field($_POST['pais'] ?? ''),
        'sitio_web'     => esc_url_raw($_POST['sitio_web'] ?? ''),
        'notas'         => sanitize_textarea_field($_POST['notas'] ?? '')
    ];

    foreach ($datos_personales as $clave => $valor) {
        update_user_meta($user_id, "smartcards_{$clave}", $valor);
    }

    // Sanitizar redes sociales
    $redes_sociales = [
        'whatsapp'     => sanitize_text_field($_POST['whatsapp'] ?? ''),
        'instagram'    => sanitize_text_field($_POST['instagram'] ?? ''),
        'facebook'     => esc_url_raw($_POST['facebook'] ?? ''),
        'x'            => sanitize_text_field($_POST['x'] ?? ''),
        'tiktok'       => sanitize_text_field($_POST['tiktok'] ?? ''),
        'linkedin'     => esc_url_raw($_POST['linkedin'] ?? ''),
        'youtube'      => esc_url_raw($_POST['youtube'] ?? ''),
        'google_maps'  => esc_url_raw($_POST['google_maps'] ?? ''),
        'wompi'        => esc_url_raw($_POST['wompi'] ?? ''),
        'epayco'       => esc_url_raw($_POST['epayco'] ?? ''),
        'paypal'       => esc_url_raw($_POST['paypal'] ?? '')
    ];

    foreach ($redes_sociales as $clave => $valor) {
        if (!empty($valor)) {
            update_user_meta($user_id, "smartcards_{$clave}", $valor);
        }
    }

    // 4. Recorrer cada campo de la lista y sobrescribir
    foreach ($lista_campos as $campo) {
        // Si el POST no trae nada, usaremos cadena vacía
        $valor_enviado = isset($_POST[$campo]) ? $_POST[$campo] : '';

        // Escoge la función de sanitización apropiada
        // Aquí, para redes y textos, usamos sanitize_text_field;
        // si "correo_electronico" o "sitio_web" prefieres sanitize_email / esc_url_raw, ajústalo.
        if ($campo === 'correo_electronico') {
            $valor_sanit = sanitize_email($valor_enviado);
        } elseif ($campo === 'sitio_web') {
            $valor_sanit = esc_url_raw($valor_enviado);
        } else {
            $valor_sanit = sanitize_text_field($valor_enviado);
        }

        // Guardar en user_meta, sobrescribiendo
        update_user_meta($user_id, 'smartcards_'.$campo, $valor_sanit);
    }

    // Manejo de la imagen (campo "imagen")
$foto_base64 = '';
if ( isset( $_FILES['imagen_vcf'] ) && $_FILES['imagen_vcf']['tmp_name'] ) {
    $file_type     = wp_check_filetype( $_FILES['imagen_vcf']['name'] );
    $allowed_types = array( 'jpg', 'jpeg', 'png' );

    if ( in_array( $file_type['ext'], $allowed_types, true ) ) {
        $file_contents = file_get_contents( $_FILES['imagen_vcf']['tmp_name'] );
        $foto_base64   = base64_encode( $file_contents );

        // Guardar la imagen en los metadatos del usuario
        update_user_meta( $user_id, 'smartcards_foto_vcf', $foto_base64 );
    } else {
        wp_die(
            esc_html__( 'Formato de imagen no permitido. Solo se aceptan JPG o PNG.', 'smartcards' )
        );
    }
}

    // Generar el VCF
    $vcard_content  = "BEGIN:VCARD\r\n";
    $vcard_content .= "VERSION:3.0\r\n";
    $vcard_content .= "N:{$datos_personales['apellido']};{$datos_personales['nombre']}\r\n";
    $vcard_content .= "FN:{$datos_personales['nombre']} {$datos_personales['apellido']}\r\n";
    if (!empty($datos_personales['cargo'])) $vcard_content .= "TITLE:{$datos_personales['cargo']}\r\n";
    if (!empty($datos_personales['empresa'])) $vcard_content .= "ORG:{$datos_personales['empresa']}\r\n";
    if (!empty($datos_personales['correo'])) $vcard_content .= "EMAIL;TYPE=WORK:{$datos_personales['correo']}\r\n";
    if (!empty($datos_personales['telefono'])) $vcard_content .= "TEL;TYPE=WORK,VOICE:{$datos_personales['telefono']}\r\n";
    if (!empty($datos_personales['telefono2'])) $vcard_content .= "TEL;TYPE=CELL,VOICE:{$datos_personales['telefono2']}\r\n";
    if (!empty($datos_personales['direccion'])) {
        $vcard_content .= "ADR;TYPE=WORK:;;{$datos_personales['direccion']};{$datos_personales['ciudad']};{$datos_personales['departamento']};{$datos_personales['codigo_postal']};{$datos_personales['pais']}\r\n";
    }
    if (!empty($datos_personales['sitio_web'])) $vcard_content .= "URL:{$datos_personales['sitio_web']}\r\n";
    if (!empty($datos_personales['notas'])) $vcard_content .= "NOTE:{$datos_personales['notas']}\r\n";
    if (!empty($foto_base64)) $vcard_content .= "PHOTO;ENCODING=b;TYPE=JPEG:{$foto_base64}\r\n";
    $vcard_content .= "END:VCARD\r\n";

    // Guardar el VCF en la biblioteca de medios
    $filename = 'smartcard-' . sanitize_title($datos_personales['nombre'] . '-' . $datos_personales['apellido']) . '-' . time() . '.vcf';
    $upload_dir = wp_upload_dir();
    $file_path = $upload_dir['path'] . '/' . $filename;
    file_put_contents($file_path, $vcard_content);

    // Registrar en la biblioteca de medios
    $attachment = [
        'guid'           => $upload_dir['url'] . '/' . basename($file_path),
        'post_mime_type' => 'text/x-vcard',
        'post_title'     => preg_replace('/\.[^.]+$/', '', $filename),
        'post_content'   => '',
        'post_status'    => 'inherit',
        'post_author'    => $user_id,
    ];
    $attach_id = wp_insert_attachment($attachment, $file_path);
    require_once ABSPATH . 'wp-admin/includes/image.php';
    wp_update_attachment_metadata($attach_id, wp_generate_attachment_metadata($attach_id, $file_path));

    $vcf_url = wp_get_attachment_url($attach_id);

         // Validar que exista foto del perfil (obligatorio)
if ( empty( $_FILES['imagen']['name'] ) ) {
    wp_die(
        esc_html__( '⚠️ Debes subir la foto del perfil.', 'smartcards' )
    );
}

        // Manejar la imagen (Foto del perfil)
if ( isset( $_FILES['imagen'] ) && ! empty( $_FILES['imagen']['name'] ) ) {

    // Validar el peso (max 2MB)
    if ( $_FILES['imagen']['size'] > 2 * 1024 * 1024 ) {
        wp_die(
            esc_html__( 'La imagen excede el tamaño máximo de 2 MB.', 'smartcards' )
        );
    }
        
            // Cargar funciones de WordPress para manejo de archivos
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/media.php');
            require_once(ABSPATH . 'wp-admin/includes/image.php');
    
            // Subir la imagen
            $file   = $_FILES['imagen'];
            $upload = wp_handle_upload($file, array('test_form' => false));
    
            if ( isset($upload['file']) ) {
                // Archivo subido correctamente
                $file_name = $upload['file'];
                $file_type = wp_check_filetype(basename($file_name), null);
    
                // Crear adjunto en la biblioteca de medios
                $attachment = array(
                    'post_mime_type' => $file_type['type'],
                    'post_title'     => sanitize_file_name(basename($file_name)),
                    'post_content'   => '',
                    'post_status'    => 'inherit',
                );
    
                // Insertar adjunto (foto de perfil)
$foto_perfil_attach_id = wp_insert_attachment($attachment, $file_name);

// Generar metadatos (miniaturas, etc.)
$attach_data = wp_generate_attachment_metadata( $foto_perfil_attach_id, $file_name );
wp_update_attachment_metadata( $foto_perfil_attach_id, $attach_data );

// Guardar el ID del adjunto en el user_meta
update_user_meta( $user_id, 'smartcards_foto_perfil_id', $foto_perfil_attach_id );

} else {
    // Error al subir
    wp_die(
        sprintf(
            /* translators: %s: mensaje de error de la subida */
            esc_html__( 'Error al subir la imagen: %s', 'smartcards' ),
            esc_html( $upload['error'] )
        )
    );
}

// Validar que exista foto de portada (obligatorio)
if ( empty( $_FILES['portada']['name'] ) ) {
    wp_die(
        esc_html__( '⚠️ Debes subir la foto de la portada.', 'smartcards' )
    );
}

// Manejo de la foto "portada" (foto de la portada).
if ( isset( $_FILES['portada'] ) && ! empty( $_FILES['portada']['name'] ) ) {

    // Validar el peso (max 5MB)
    if ( $_FILES['portada']['size'] > 5 * 1024 * 1024 ) {
        wp_die(
            esc_html__( 'La foto de la portada excede el tamaño máximo de 5 MB.', 'smartcards' )
        );
    }
    } 

        // Cargar funciones de WordPress para manejo de archivos
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        // Subir la imagen
        $file   = $_FILES['portada'];
        $upload = wp_handle_upload($file, array('test_form' => false));

        if ( isset($upload['file']) ) {
            // Archivo subido correctamente
            $file_name = $upload['file'];
            $file_type = wp_check_filetype(basename($file_name), null);

            // Crear adjunto en la biblioteca de medios
            $attachment = array(
                'post_mime_type' => $file_type['type'],
                'post_title'     => sanitize_file_name(basename($file_name)),
                'post_content'   => '',
                'post_status'    => 'inherit',
            );

            // Insertar adjunto (foto de portada)
$foto_portada_attach_id = wp_insert_attachment($attachment, $file_name);

// Generar metadatos (miniaturas, etc.)
$attach_data = wp_generate_attachment_metadata( $foto_portada_attach_id, $file_name );
wp_update_attachment_metadata( $foto_portada_attach_id, $attach_data );

// Guardar el ID del adjunto en user_meta
update_user_meta( $user_id, 'smartcards_foto_portada_id', $foto_portada_attach_id );

} else {
    // Error al subir la portada
    wp_die( sprintf(
        /* translators: %s es el mensaje de error devuelto por WordPress al subir */
        esc_html__( 'Error al subir la portada: %s', 'smartcards' ),
        esc_html( $upload['error'] )
    ) );
}
}
               // Obtener (IDs) de los archivos adjuntos
               $foto_perfil_id   = $foto_perfil_attach_id;
               $foto_portada_id  = $foto_portada_attach_id;
           
               // Generar URL o fallback "Imagen por defecto si no sube el usuario una imagen"
               $foto_perfil_url  = wp_get_attachment_url($foto_perfil_id);
               $foto_portada_url = wp_get_attachment_url($foto_portada_id);
           
               // Fallback para foto de perfil
               if ( empty($foto_perfil_url) ) {
                   $foto_perfil_url = 'https://app.smartcards.com.co/wp-content/uploads/2025/02/Ft-Perfil.svg';
               }
           
               // Fallback para foto de la portada
               if ( empty($foto_portada_url) ) {
                   $foto_portada_url = 'https://app.smartcards.com.co/wp-content/uploads/2025/02/Banner-3.svg';
               }

    // Crear perfil público con botones de redes sociales
    $contenido = '
<div class="profile-cover-wrapper">
    <img class="cover-image" src="'.$foto_portada_url.'" alt="Portada" />

    <div class="profile-image-wrapper">
        <img class="profile-image" src="'.$foto_perfil_url.'" alt="Foto de perfil" />
    </div>
</div>

<div class="perfil-publico" data-sc-profile-id="{{SC_PROFILE_ID}}">
  <h2>'.$datos_personales['nombre'].' '.$datos_personales['apellido'].'</h2>
  <h4>'.$datos_personales['cargo'].'</h4>

  <a href="'. esc_url( home_url('/descargar-vcf/' . $user_id) . '?ts=' . time() ) .'"
     class="btn-contacto-link sc-btn-contact btn-guardar-contacto"
     data-sc-event="save_contact_click"
     download>
    Guardar contacto ↓
  </a>

  <div class="redes-sociales">
';

// Verificar que la variable está definida antes del foreach
$iconos_redes = [
    'whatsapp' => ["https://wa.me/", "whatsapp.svg"],
    'instagram' => ["https://instagram.com/", "instagram.svg"],
    'facebook' => ["", "facebook.svg"],
    'x' => ["https://x.com/", "x.svg"],
    'tiktok' => ["https://tiktok.com/@", "tiktok.svg"],
    'linkedin' => ["", "linkedin.svg"],
    'youtube' => ["", "youtube.svg"],
    'google_maps' => ["", "maps.svg"],
    'wompi' => ["", "wompi.svg"],
    'epayco' => ["", "epayco.svg"],
    'paypal' => ["", "paypal.svg"],
    'sitio_web'  => ["", "navegador.svg"]
];

// Comprobamos que la variable existe y tiene datos
if (!empty($iconos_redes) && is_array($iconos_redes)) {
    foreach ($iconos_redes as $clave => $info) {
        $valor = get_user_meta($user_id, "smartcards_{$clave}", true);

        if (!empty($valor)) {
            // Si hay una URL base (como WhatsApp), concatenarla con el valor ingresado
            $url = !empty($info[0]) ? $info[0] . urlencode($valor) : $valor;
            $contenido .= sprintf(
                '<a href="%1$s" target="_blank" rel="noopener" class="btn-red-social %2$s" data-sc-event="button_click" data-sc-button="%2$s">%3$s</a>',
                esc_url( $url ),
                esc_attr( $clave ),
                sc_get_social_icon_markup( $info[1], ucfirst( str_replace( '_', ' ', $clave ) ) )
            );
        }
    }
} else {
    error_log( __( '⚠️ La variable iconos_redes está vacía o no es un array válido.', 'smartcards' ) );
}

$contenido .= "</div></div>";

$font_family = '';
if (isset($_POST['sc_font_family'])) {
    $font_family = sc_clean_font_name(wp_unslash($_POST['sc_font_family']));
} elseif (isset($_POST['font_family'])) {
    $font_family = sc_clean_font_name(wp_unslash($_POST['font_family']));
} elseif (isset($_POST['fontName'])) {
    $font_family = sc_clean_font_name(wp_unslash($_POST['fontName']));
}

// El contenedor para el QR dinámico
$contenido .= '<div id="qr-container"></div>';

// Insertar shortcode para incluir el script JS del QR Dinámico
$contenido .= '<div id="qr-container"></div>';

    // Crear la página de perfil en WordPress
    $page_id = wp_insert_post([
        'post_title' => "{$datos_personales['nombre']} {$datos_personales['apellido']}",
        'post_content' => $contenido,
        'post_status' => 'publish',
        'post_type' => 'page'
    ]);

    // Confirmar que la página se creó correctamente
if ($page_id && !is_wp_error($page_id)) {
    update_post_meta( $page_id, 'sc_owner_user_id', $user_id );
    if ($font_family !== '') {
        update_post_meta( $page_id, 'sc_font_family', $font_family );
    }

    $contenido_final = str_replace(
        '{{SC_PROFILE_ID}}',
        (string) $page_id,
        $contenido
    );

    wp_update_post([
        'ID'           => $page_id,
        'post_content' => $contenido_final,
    ]);

    // Guardar los IDs para poder eliminarlos luego si hay error
    update_user_meta($user_id, 'smartcards_perfil_page_id', $page_id); //Página del perfil público
    update_user_meta($user_id, 'smartcards_vcf_attachment_id', $attach_id); // archivo VCF correcto
    update_user_meta($user_id, 'smartcards_foto_perfil_id', $foto_perfil_attach_id); // foto perfil corregida
    update_user_meta($user_id, 'smartcards_foto_portada_id', $foto_portada_attach_id); // foto portada corregida

    // Generar URL del perfil recién creado
    $url_perfil = get_permalink($page_id);
    $resolved_post_id = url_to_postid( $url_perfil );

    error_log('[SC DEBUG][procesar_formulario] page_id: ' . (int) $page_id);
    error_log('[SC DEBUG][procesar_formulario] post_type: ' . (string) get_post_type( $page_id ));
    error_log('[SC DEBUG][procesar_formulario] status: ' . (string) get_post_status( $page_id ));
    error_log('[SC DEBUG][procesar_formulario] permalink: ' . (string) $url_perfil);
    error_log('[SC DEBUG][procesar_formulario] url_to_postid: ' . (int) $resolved_post_id);
    error_log('META SAVED: ' . json_encode([
        'redes'  => get_post_meta($page_id, 'sc_color_redes', true),
        'button' => get_post_meta($page_id, 'sc_color_button', true),
    ]));

    // Llamar función de notificación después de crear exitosamente el perfil
$user_id = get_current_user_id();
$nombre_cliente = get_user_meta($user_id, 'smartcards_nombre', true) . ' ' . get_user_meta($user_id, 'smartcards_apellido', true);
$nombre_empresa = get_user_meta($user_id, 'smartcards_empresa', true);

// Enviar notificación inmediatamente
notificar_aprobacion_perfil($url_perfil, $nombre_cliente, $nombre_empresa);


    // Enviar respuesta JSON con la URL para el QR dinámico
    wp_send_json_success([
        'perfil_url' => $url_perfil,
        'public_url' => $url_perfil,
        'permalink'  => $url_perfil,
    ]);

} else {

   // Enviar error en caso que no se pueda crear la página
wp_send_json_error([
    'message' => esc_html__( 'Error al crear la página del perfil', 'smartcards' )
]);

}

wp_die(); // Importante finalizar así para AJAX en WordPress
}

add_action('wp_ajax_procesar_formulario', 'procesar_formulario');
add_action('wp_ajax_nopriv_procesar_formulario', 'procesar_formulario');

/**
 * Restaura el crédito del usuario, Elimina perfil, VCF, fotos cuando el perfil tiene errores
 */
add_action('wp_ajax_perfil_con_error', 'manejar_perfil_con_error');

function manejar_perfil_con_error() {
  $user_id = get_current_user_id();

  if ($user_id > 0) {
    // Restaurar crédito
    $credits = (int) get_user_meta($user_id, 'smartcards_credits', true);
    update_user_meta($user_id, 'smartcards_credits', $credits + 1);

    // Eliminar página del perfil
    $perfil_page_id = get_user_meta($user_id, 'smartcards_perfil_page_id', true);
    if ($perfil_page_id) {
      wp_delete_post($perfil_page_id, true);
      delete_user_meta($user_id, 'smartcards_perfil_page_id');
    }

    // Eliminar archivo VCF
    $vcf_attachment_id = get_user_meta($user_id, 'smartcards_vcf_attachment_id', true);
    if ($vcf_attachment_id) {
      wp_delete_attachment($vcf_attachment_id, true);
      delete_user_meta($user_id, 'smartcards_vcf_attachment_id');
    }

    // Eliminar foto de perfil
    $foto_perfil_id = get_user_meta($user_id, 'smartcards_foto_perfil_id', true);
    if ($foto_perfil_id) {
      wp_delete_attachment($foto_perfil_id, true);
      delete_user_meta($user_id, 'smartcards_foto_perfil_id');
    }

    // Eliminar foto de portada
$foto_portada_id = get_user_meta( $user_id, 'smartcards_foto_portada_id', true );
if ( $foto_portada_id ) {
    wp_delete_attachment( $foto_portada_id, true );
    delete_user_meta( $user_id, 'smartcards_foto_portada_id' );
}

wp_send_json_success( [
    'message' => esc_html__( '✅ Crédito restaurado. Puedes editar tu perfil nuevamente.', 'smartcards' ),
] );
} else {
    wp_send_json_error( [
        'message' => esc_html__( 'Debes estar logueado para realizar esta acción.', 'smartcards' ),
    ] );
}

wp_die();
}
