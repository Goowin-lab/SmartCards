<?php
if ( ! defined('ABSPATH') ) {
    exit; // Evitar acceso directo
}

// ----------------------------------------------------------------
// 0) Shortcode GLOBAL para el contenedor del QR Dinámico
// ----------------------------------------------------------------
function qr_dynamic_container_shortcode() {
    return '<div id="qr-container"></div>';
}
add_shortcode('qr_dynamic_container', 'qr_dynamic_container_shortcode');


// ----------------------------------------------------------------
// 1) Registrar shortcode [sc_product_list] para delegar en [products]
// ----------------------------------------------------------------
add_shortcode( 'sc_product_list', function( $atts ) {
    // Extraemos el atributo "limit", valor por defecto 14
    $atts = shortcode_atts( [
        'limit' => '14',
    ], $atts, 'sc_product_list' );

    // Aquí usamos el shortcode nativo de WooCommerce "[products]"
    // para mostrar "limit" productos en 3 columnas:
    return do_shortcode( sprintf(
        '[products limit="%1$s" columns="3"]',
        esc_attr( $atts['limit'] )
    ) );
} );


// ----------------------------------------------------------------
// 2) Shortcode [smartcards_form]
//    – formulario de activación / generación de perfil público
// ----------------------------------------------------------------
function smartcards_form_shortcode() {
    // 1. Verificar si el usuario está logueado
    if ( ! is_user_logged_in() ) {
        return
            '<p>'
            . esc_html__( 'Debes iniciar sesión para activar tu contacto.', 'smartcards' )
            . ' <a href="'
            . esc_url( wp_login_url() )
            . '" class="login-button">'
            . esc_html__( 'Iniciar sesión', 'smartcards' )
            . '</a></p>';
    }

    // Obtener el ID del usuario actual
    $perfil_id = get_current_user_id();
    $credits   = (int) get_user_meta( $perfil_id, 'smartcards_credits', true );

    if ( $credits <= 0 ) {
        return
            '<div class="sc-no-credits">'
            . '<h2>🚫 No tienes créditos disponibles</h2>'
            . '<p>Para crear tu Smart Card necesitas al menos 1 crédito.</p>'
            . '<p>✨ Activa un crédito y publica tu perfil en segundos.</p>'
            . '<div class="sc-no-credits-actions">'
            . '<a href="' . esc_url( home_url( '/comprar-creditos/' ) ) . '" class="form-button">Activar crédito ahora</a>'
            . '<a href="' . esc_url( home_url( '/dashboard/' ) ) . '" class="sc-secondary-btn">Volver al dashboard</a>'
            . '</div>'
            . '</div>';
    }

    // 2. Construir el formulario (ya que el usuario está logueado)
    ob_start(); 
    ?>
    <form id="smartcards-form"
      action="<?php echo esc_url( admin_url('admin-ajax.php') ); ?>"
      method="POST"
      enctype="multipart/form-data">

        <!-- Acción que se conectará con procesar-formulario.php -->
        <input type="hidden" name="action" value="procesar_formulario" />
        <?php wp_nonce_field('smartcards_form_nonce', 'smartcards_form_nonce_field'); ?>

        <!-- Campo oculto: ID del perfil -->
        <input type="hidden" name="perfil_id" value="<?php echo esc_attr($perfil_id); ?>">

        <!-- Campo nuevo: imagen -->
        <h3><?php _e( 'Imagen para tu contacto:', 'smartcards' ); ?></h3>
        <input type="file" id="imagen_vcf" name="imagen_vcf" accept="image/jpeg,image/png,image/jpg">

        <!-- Campos existentes -->
        <label for="nombre"><?php _e( 'Nombre:', 'smartcards' ); ?></label>
        <input type="text" name="nombre"
               required
               oninvalid="this.setCustomValidity('<?php echo esc_js( __( '✅ Por favor ingresa tu nombre.', 'smartcards' ) ); ?>')"
               onchange="this.setCustomValidity('')"><br/>

        <label for="apellido"><?php _e( 'Apellido:', 'smartcards' ); ?></label>
        <input type="text" name="apellido"
               required
               oninvalid="this.setCustomValidity('<?php echo esc_js( __( '✅ Por favor ingresa tu apellido.', 'smartcards' ) ); ?>')"
               onchange="this.setCustomValidity('')"><br/>

        <label for="empresa"><?php _e( 'Empresa:', 'smartcards' ); ?></label>
        <input type="text" name="empresa"><br/>

        <label for="cargo"><?php _e( 'Cargo:', 'smartcards' ); ?></label>
    <input type="text" name="cargo">
    <br/>

    <label for="correo_electronico"><?php _e( 'Correo electrónico:', 'smartcards' ); ?></label>
    <input type="email"
           name="correo_electronico"
           required
           oninvalid="this.setCustomValidity('<?php echo esc_js( __( '✅ Por favor ingresa tu correo electrónico.', 'smartcards' ) ); ?>')"
           onchange="this.setCustomValidity('')">
    <br/>

    <label for="telefono"><?php _e( 'Teléfono (número directo, sin espacios, guiones o paréntesis):', 'smartcards' ); ?></label>
    <input type="text"
           name="telefono"
           required
           oninvalid="this.setCustomValidity('<?php echo esc_js( __( '✅ Por favor ingresa tu teléfono.', 'smartcards' ) ); ?>')"
           onchange="this.setCustomValidity('')">
    <br/>

    <label for="telefono2"><?php _e( 'Teléfono 2 (opcional):', 'smartcards' ); ?></label>
    <input type="text" name="telefono2">
    <br/>

    <label for="pais"><?php _e( 'País:', 'smartcards' ); ?></label>
    <input type="text"
           name="pais"
           required
           oninvalid="this.setCustomValidity('<?php echo esc_js( __( '✅ Por favor ingresa tu país.', 'smartcards' ) ); ?>')"
           onchange="this.setCustomValidity('')">
    <br/>

    <label for="direccion"><?php _e( 'Dirección:', 'smartcards' ); ?></label>
    <input type="text" name="direccion">
    <br/>

    <label for="ciudad"><?php _e( 'Ciudad:', 'smartcards' ); ?></label>
    <input type="text" name="ciudad">
    <br/>

    <label for="departamento"><?php _e( 'Departamento:', 'smartcards' ); ?></label>
    <input type="text" name="departamento">
    <br/>

    <label for="codigo_postal"><?php _e( 'Código postal:', 'smartcards' ); ?></label>
    <input type="text" name="codigo_postal">
    <br/>

    <label for="sitio_web"><?php _e( 'Sitio web:', 'smartcards' ); ?></label>
    <input type="url" name="sitio_web">
    <br/>

    <label for="notas"><?php _e( 'Notas:', 'smartcards' ); ?></label>
    <textarea name="notas"></textarea>
    <br/>

    <h3><?php _e( 'Redes Sociales:', 'smartcards' ); ?></h3>

    <label for="whatsapp"><?php _e( 'Número de WhatsApp:', 'smartcards' ); ?></label>
    <input type="text" name="whatsapp">
    <br/>

    <label for="instagram"><?php _e( 'Usuario de Instagram:', 'smartcards' ); ?></label>
    <input type="text" name="instagram">
    <br/>

    <label for="facebook"><?php _e( 'Enlace de Facebook:', 'smartcards' ); ?></label>
    <input type="url" name="facebook">
    <br/>

    <label for="x"><?php _e( 'Usuario de X:', 'smartcards' ); ?></label>
    <input type="text" name="x">
    <br/>

    <label for="tiktok"><?php _e( 'Usuario de TikTok:', 'smartcards' ); ?></label>
    <input type="text" name="tiktok">
    <br/>

    <label for="linkedin"><?php _e( 'Enlace de LinkedIn:', 'smartcards' ); ?></label>
    <input type="url" name="linkedin">
    <br/>

    <label for="youtube"><?php _e( 'Enlace de YouTube:', 'smartcards' ); ?></label>
    <input type="url" name="youtube">
    <br/>

    <label for="google_maps"><?php _e( 'Google Maps:', 'smartcards' ); ?></label>
    <input type="url" name="google_maps">
    <br/>

    <label for="wompi"><?php _e( 'Enlace de Wompi:', 'smartcards' ); ?></label>
    <input type="url" name="wompi">
    <br/>

    <label for="epayco"><?php _e( 'Enlace de ePayco:', 'smartcards' ); ?></label>
    <input type="url" name="epayco">
    <br/>

    <label for="paypal"><?php _e( 'Enlace de PayPal:', 'smartcards' ); ?></label>
    <input type="url" name="paypal">
    <br/>

    <!-- 1. Título debajo de campos de redes sociales -->
    <h3><?php _e( 'Foto del perfil:', 'smartcards' ); ?></h3>

    <!-- 2. Descripción del tamaño y peso máximo -->
    <p><?php _e( 'Las medidas recomendadas para esta foto son 500 píxeles x 500 píxeles y el peso máximo es de 2MB.', 'smartcards' ); ?></p>

        <!-- Texto de sugerencia en negrita -->
    <p><strong><?php _e( 'Sugerencia:', 'smartcards' ); ?></strong> <?php _e( 'Sube tu foto corporativa o el logotipo de tu empresa para reforzar la identidad.', 'smartcards' ); ?></p>

    <!-- Imagen ilustrativa con enlace que abre en nueva pestaña -->
    <a href="https://app.smartcards.com.co/wp-content/uploads/2025/02/perfil-1.svg"
       target="_blank"
       rel="noopener">
        <img src="https://app.smartcards.com.co/wp-content/uploads/2025/02/perfil-1.svg"
             alt="<?php esc_attr_e( 'Ilustración para foto de perfil', 'smartcards' ); ?>"
             style="max-width: 100%; height: auto; margin-bottom: 15px;" />
    </a>

    <!-- 3. Campo para subir la imagen -->
    <label for="imagen"><?php _e( 'Subir foto de perfil:', 'smartcards' ); ?></label>
    <input
      type="file"
      id="profile_picture"
      name="imagen"
      accept="image/jpeg,image/png,image/jpg"
      required
      oninvalid="this.setCustomValidity('<?php echo esc_js( __( '⚠️ Debes subir la foto del perfil.', 'smartcards' ) ); ?>')"
      onchange="this.setCustomValidity('')"
    ><br/><br/>

        <!-- 1. Nuevo bloque para Foto de la portada -->
        <h3><?php _e( 'Foto de la portada:', 'smartcards' ); ?></h3>
        <!-- 2. Descripción del tamaño y peso máximo -->
        <p><?php _e( 'Las medidas recomendadas para esta foto son 1250 píxeles x 481 píxeles y el peso máximo es de 5MB.', 'smartcards' ); ?></p>

        <!-- Recomendación en negrita -->
        <p><strong><?php _e( 'Recomendación:', 'smartcards' ); ?></strong>
        <?php _e( 'Añade una imagen llamativa de tus servicios, productos o instalaciones para destacar aún más tu perfil.', 'smartcards' ); ?></p>

        <!-- Imagen ilustrativa con enlace (abre en nueva pestaña) -->
        <a href="https://app.smartcards.com.co/wp-content/uploads/2025/02/1250x481.svg"
           target="_blank"
           rel="noopener">
            <img src="https://app.smartcards.com.co/wp-content/uploads/2025/02/1250x481.svg"
                 alt="<?php esc_attr_e( 'Ilustración de foto de portada', 'smartcards' ); ?>"
                 style="max-width: 100%; height: auto; margin-bottom: 15px;" />
        </a>

        <!-- 3. Campo para subir la portada -->
        <label for="portada"><?php _e( 'Subir foto de la portada:', 'smartcards' ); ?></label>
        <input
          type="file"
          id="portada"
          name="portada"
          accept="image/jpeg,image/png,image/jpg"
          required
          oninvalid="this.setCustomValidity('<?php echo esc_js( __( '⚠️ Debes subir la foto de la portada.', 'smartcards' ) ); ?>')"
          onchange="this.setCustomValidity('')"
        ><br/><br/>

        <button type="submit" class="form-button" id="btn-generar-perfil">
          <?php _e( 'Generar Perfil Público', 'smartcards' ); ?>
        </button>

    </form>
    <?php
    // Nota: Removimos <div id="qr-container"></div> de aquí para que no interfiera en la página de activación.
    return ob_get_clean();
}
add_shortcode('smartcards_form', 'smartcards_form_shortcode');


// ----------------------------------------------------------------
// 3) Shortcode [sc_dashboard_cliente] para mostrar el dashboard
// ----------------------------------------------------------------
function sc_dashboard_cliente_shortcode() {
    if ( ! is_user_logged_in() ) {
        return '<p>' . esc_html__( 'Debes iniciar sesión para ver tu dashboard.', 'smartcards' ) . '</p>';
    }

    $user_id           = get_current_user_id();
    $total_credits     = (int) get_user_meta( $user_id, 'smartcards_credits', true );
    $used_credits      = (int) get_user_meta( $user_id, 'smartcards_used_credits', true );
    $available_credits = max( 0, $total_credits - $used_credits );

    // --- Config IAP (ajusta a tus IDs/URLs reales)
    $sku_1  = 'creditos_smartcards_1';
$sku_5  = 'creditos_smartcards_5';
$sku_10 = 'creditos_smartcards_10';

// Fallback web (URLs reales de tus productos)
$url_1  = add_query_arg( 'v', 'ab6c04006660', home_url( '/producto/credito-smartcards/' ) );
$url_5  = add_query_arg( 'v', 'ab6c04006660', home_url( '/producto/5-creditos-smartcards/' ) );
$url_10 = home_url( '/producto/10-creditos-smartcards/' );

    // Iconos SVG inline (monedas y tarjeta)
    $coin_svg = '<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false">
      <defs><linearGradient id="scCoinG" x1="0" y1="0" x2="1" y2="1">
        <stop offset="0%" stop-color="#FFD54F"/><stop offset="100%" stop-color="#F9A825"/>
      </linearGradient></defs>
      <ellipse cx="12" cy="8" rx="7" ry="3.5" fill="url(#scCoinG)"/>
      <path d="M5 8v6c0 2 14 2 14 0V8" fill="#FBC02D" opacity=".9"/>
      <ellipse cx="12" cy="14" rx="7" ry="3.5" fill="url(#scCoinG)" opacity=".95"/>
    </svg>';

    $icon_card = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
      <path d="M3 5h18a1 1 0 011 1v12a1 1 0 01-1 1H3a1 1 0 01-1-1V6a1 1 0 011-1zm0 4h18V7H3v2zm0 2v6h18v-6H3z"/>
    </svg>';

    ob_start();
    ?>
    <!-- ======= Contenedor de créditos y botones ======= -->
    <div class="dashboard-container">
      <h2>
        <?php
        /* translators: %s: nombre de usuario */
        printf( __( 'Hola %s 👋', 'smartcards' ), esc_html( wp_get_current_user()->user_login ) );
        ?>
      </h2>

      <?php if ( $available_credits > 0 ) : ?>
        <p>
          <?php
          printf(
            esc_html__( 'Tienes %s crédito%s para activar Smart Cards.', 'smartcards' ),
            '<strong>' . esc_html( $available_credits ) . '</strong>',
            $available_credits === 1 ? '' : 's'
          );
          ?>
        </p>
<!-- Botón Activar (verde pill con icono) -->
        <div class="sc-btn-stack">
          <button class="sc-google-btn sc-google-btn--green sc-google-btn--shiny"
                  type="button"
                  onclick="window.location.href='<?php echo esc_url( home_url( '/formulario-activacion/' ) ); ?>'">
            <span class="sc-google-btn__icon" aria-hidden="true"><?php echo $icon_card; ?></span>
            <span class="sc-google-btn__label"><?php esc_html_e( 'Activar Smart Cards', 'smartcards' ); ?></span>
          </button>
        </div>
      <?php else : ?>
        <p><?php esc_html_e( 'No tienes créditos disponibles para activar Smart Cards.', 'smartcards' ); ?></p>
      <?php endif; ?>

      <!-- Opciones apiladas: 1, 5 y 10 créditos (WooCommerce) -->
      <div class="sc-btn-stack sc-btn-stack--options" style="margin-top:12px;">

        <a class="sc-google-btn sc-google-btn--green sc-google-btn--shiny"
           href="<?php echo esc_url( function_exists('sc_wc_fallback_url_from_sku') ? sc_wc_fallback_url_from_sku($sku_1) : $url_1 ); ?>">
          <span class="sc-google-btn__icon" aria-hidden="true"><?php echo $coin_svg; ?></span>
          <span class="sc-google-btn__label">Comprar 1 crédito</span>
        </a>

        <a class="sc-google-btn sc-google-btn--green sc-google-btn--shiny"
           href="<?php echo esc_url( function_exists('sc_wc_fallback_url_from_sku') ? sc_wc_fallback_url_from_sku($sku_5) : $url_5 ); ?>">
          <span class="sc-google-btn__icon" aria-hidden="true"><?php echo $coin_svg; ?></span>
          <span class="sc-google-btn__label">Comprar 5 créditos</span>
        </a>

        <a class="sc-google-btn sc-google-btn--green sc-google-btn--shiny"
           href="<?php echo esc_url( function_exists('sc_wc_fallback_url_from_sku') ? sc_wc_fallback_url_from_sku($sku_10) : $url_10 ); ?>">
          <span class="sc-google-btn__icon" aria-hidden="true"><?php echo $coin_svg; ?></span>
          <span class="sc-google-btn__label">Comprar 10 créditos</span>
        </a>

      </div>

      <?php
      $mis_smarts_url = home_url('/mis-smart-cards/');
      ?>

      <div class="sc-btn-stack" style="margin-top:12px;">
        <a class="sc-google-btn sc-google-btn--green sc-google-btn--shiny"
           href="<?php echo esc_url($mis_smarts_url); ?>">
          <span class="sc-google-btn__label">Mis Smart</span>
        </a>
      </div>

      <!-- (Opcional) Ancla para mapear usuario en OneSignal/RevenueCat -->
      <div id="sc-iap-root" data-user-id="<?php echo (int) $user_id; ?>"></div>

      <!-- Título para la sección de tarjeta física -->
      <div class="sc-dashboard-physical" style="margin-top:24px; text-align:center;">
        <h2><?php esc_html_e( 'Compra tu tarjeta física:', 'smartcards' ); ?></h2>
      </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode( 'sc_dashboard_cliente', 'sc_dashboard_cliente_shortcode' );


// ----------------------------------------------------------------
// 4) Shortcode [perfil_publico] para mostrar el perfil público y el QR
// ----------------------------------------------------------------
function perfil_publico_shortcode() {
    // 1) Si no está logueado, muestro mensaje y salgo
    if ( ! is_user_logged_in() ) {
        return '<p>'
            . esc_html__( 'Debes iniciar sesión para ver tu perfil público.', 'smartcards' )
            . '</p>';
    }

    // 2) Recupero datos del usuario actual
    $user_id   = get_current_user_id();
    $nombre    = get_user_meta( $user_id, 'smartcards_temp_nombre', true );
    $apellido  = get_user_meta( $user_id, 'smartcards_temp_apellido', true );
    $cargo     = get_user_meta( $user_id, 'smartcards_temp_cargo', true );
    $whatsapp  = get_user_meta( $user_id, 'smartcards_temp_whatsapp', true );
    $instagram = get_user_meta( $user_id, 'smartcards_temp_instagram', true );
    $facebook  = get_user_meta( $user_id, 'smartcards_temp_facebook', true );
    $x         = get_user_meta( $user_id, 'smartcards_temp_x', true );
    $tiktok    = get_user_meta( $user_id, 'smartcards_temp_tiktok', true );
    $linkedin  = get_user_meta( $user_id, 'smartcards_temp_linkedin', true );
    $youtube   = get_user_meta( $user_id, 'smartcards_temp_youtube', true );

    // 3) Construyo el HTML del perfil público
    ob_start();
    ?>
    <div class="smartcards-perfil">
        <!-- Nombre y apellido -->
        <h2><?php echo esc_html( "$nombre $apellido" ); ?></h2>

        <!-- Cargo (si existe) -->
        <?php if ( $cargo ) : ?>
            <p>
                <strong><?php esc_html_e( 'Cargo:', 'smartcards' ); ?></strong>
                <?php echo esc_html( $cargo ); ?>
            </p>
        <?php endif; ?>

        <!-- Redes Sociales -->
        <div class="smartcards-redes">
            <?php if ( $whatsapp ) : ?>
                <p>
                    <strong><?php esc_html_e( 'WhatsApp:', 'smartcards' ); ?></strong>
                    <a href="https://wa.me/<?php echo esc_attr( $whatsapp ); ?>" target="_blank">
                        <?php echo esc_html( $whatsapp ); ?>
                    </a>
                </p>
            <?php endif; ?>

            <?php if ( $instagram ) : ?>
                <p>
                    <strong><?php esc_html_e( 'Instagram:', 'smartcards' ); ?></strong>
                    <a href="https://instagram.com/<?php echo esc_attr( $instagram ); ?>" target="_blank">
                        @<?php echo esc_html( $instagram ); ?>
                    </a>
                </p>
            <?php endif; ?>

            <?php if ( $facebook ) : ?>
                <p>
                    <strong><?php esc_html_e( 'Facebook:', 'smartcards' ); ?></strong>
                    <a href="<?php echo esc_url( $facebook ); ?>" target="_blank">
                        <?php echo esc_html( $facebook ); ?>
                    </a>
                </p>
            <?php endif; ?>

            <?php if ( $x ) : ?>
                <p>
                    <strong><?php esc_html_e( 'X (Twitter):', 'smartcards' ); ?></strong>
                    <a href="https://twitter.com/<?php echo esc_attr( $x ); ?>" target="_blank">
                        @<?php echo esc_html( $x ); ?>
                    </a>
                </p>
            <?php endif; ?>

            <?php if ( $tiktok ) : ?>
                <p>
                    <strong><?php esc_html_e( 'TikTok:', 'smartcards' ); ?></strong>
                    <a href="https://tiktok.com/@<?php echo esc_attr( $tiktok ); ?>" target="_blank">
                        @<?php echo esc_html( $tiktok ); ?>
                    </a>
                </p>
            <?php endif; ?>

            <?php if ( $linkedin ) : ?>
                <p>
                    <strong><?php esc_html_e( 'LinkedIn:', 'smartcards' ); ?></strong>
                    <a href="<?php echo esc_url( $linkedin ); ?>" target="_blank">
                        <?php echo esc_html( $linkedin ); ?>
                    </a>
                </p>
            <?php endif; ?>

            <?php if ( $youtube ) : ?>
                <p>
                    <strong><?php esc_html_e( 'YouTube:', 'smartcards' ); ?></strong>
                    <a href="<?php echo esc_url( $youtube ); ?>" target="_blank">
                        <?php echo esc_html( $youtube ); ?>
                    </a>
                </p>
            <?php endif; ?>
        </div>

        <!-- Contenedor del QR Dinámico -->
        <?php echo do_shortcode( '[qr_dynamic_container]' ); ?>
        <!-- El JS inyectará el código QR dentro de este div -->

    </div>
    <?php
    return ob_get_clean();
}
add_shortcode( 'perfil_publico', 'perfil_publico_shortcode' );

// ----------------------------------------------------------------
// 5) Shortcode [sc_mis_smartcards] para listar Smart Cards desde user_meta
// ----------------------------------------------------------------
add_shortcode( 'sc_mis_smartcards', 'sc_show_mis_smartcards' );
function sc_show_mis_smartcards() {
    // Si no está logueado, mostramos mensaje
    if ( ! is_user_logged_in() ) {
        return '<p>'
            . sprintf(
                esc_html__( 'Por favor %1$s para ver tus Smart Cards.', 'smartcards' ),
                '<a href="' . esc_url( wp_login_url() ) . '">' . esc_html__( 'inicia sesión', 'smartcards' ) . '</a>'
            )
            . '</p>';
    }

    $user_id        = get_current_user_id();
    $perfiles_urls  = get_user_meta( $user_id, 'smartcards_perfiles_urls', true );

    // Si no hay perfiles, devolvemos <h1>No tienes Smart Cards activas.</h1>
    if ( empty( $perfiles_urls ) || ! is_array( $perfiles_urls ) ) {
        return '<h1>' . esc_html__( 'No tienes Smart Cards activas.', 'smartcards' ) . '</h1>';
    }

    ob_start();
    ?>
    <!-- Título personalizado -->
    <h3 style="text-align:center; margin-top:20px;">
        <?php esc_html_e( 'Mis Smart Cards', 'smartcards' ); ?>
    </h3>

    <!-- Contenedor para deslizar tarjetas de Smart Cards -->
    <div class="smartcards-slider">
      <?php foreach ( $perfiles_urls as $perfil ) : ?>
        <?php
        $perfil_url    = isset( $perfil['url'] ) ? sc_get_live_profile_url( $perfil['url'] ) : '';
        $profile_id    = url_to_postid( $perfil_url );
        $analytics_url = '';
        if ( $profile_id > 0 ) {
            $analytics_url = add_query_arg(
                'profile_id',
                $profile_id,
                home_url( '/mis-smart-cards-estadisticas/' )
            );
        }
        ?>
        <div class="smartcard-item">

          <!-- Foto -->
          <div class="smartcard-photo">
            <img src="<?php echo esc_url( $perfil['foto_perfil'] ); ?>"
                 alt="<?php
                 /* translators: %s: nombre completo del usuario */
                 printf(
                     esc_attr__( 'Foto de perfil de %s', 'smartcards' ),
                     esc_attr( $perfil['nombre_completo'] )
                 );
                 ?>">
          </div>

          <!-- Nombre completo -->
          <div class="smartcard-name">
            <?php echo esc_html( $perfil['nombre_completo'] ); ?>
          </div>

          <!-- Botones de acción -->
          <div class="smartcard-buttons">
            <!-- Enlace Ver Perfil corregido para Android WebView -->
            <a href="<?php echo esc_url( $perfil['url'] ); ?>"
               class="smartcard-btn">
              <?php esc_html_e( 'Ver Perfil', 'smartcards' ); ?>
            </a>

            <!-- Compartir y opciones siguen igual -->
            <button class="smartcard-btn share-btn"
                    data-url="<?php echo esc_url( $perfil['url'] ); ?>">
              <?php esc_html_e( 'Compartir', 'smartcards' ); ?>
            </button>
            <button class="smartcard-btn options-btn"
                    data-url="<?php echo esc_url( $perfil['url'] ); ?>">
              <?php esc_html_e( '⋯', 'smartcards' ); ?>
            </button>

            <?php if ( $profile_id > 0 ) : ?>
              <a href="<?php echo esc_url( $analytics_url ); ?>"
                 class="smartcard-btn analytics-btn">
                <?php esc_html_e( '📊 Estadísticas', 'smartcards' ); ?>
              </a>
            <?php endif; ?>
          </div>

        </div>
      <?php endforeach; ?>
    </div>
    <!-- FIN "Mis Smart Cards" -->

    <?php
    // Capturamos todo lo generado y lo devolvemos
    $html = ob_get_clean();
    return $html;
}

add_shortcode( 'sc_profile_analytics', 'sc_profile_analytics_shortcode' );
function sc_profile_analytics_shortcode() {
    if ( ! is_user_logged_in() ) {
        return '<p>' . esc_html__( 'Debes iniciar sesión para ver las estadísticas.', 'smartcards' ) . '</p>';
    }

    $profile_id = isset( $_GET['profile_id'] ) ? absint( $_GET['profile_id'] ) : 0;
    if ( ! $profile_id ) {
        return '<p>' . esc_html__( 'Perfil no válido.', 'smartcards' ) . '</p>';
    }

    $post = get_post( $profile_id );
    if ( ! $post || 'page' !== $post->post_type ) {
        return '<p>' . esc_html__( 'El perfil seleccionado no existe.', 'smartcards' ) . '</p>';
    }

    $current_user_id = get_current_user_id();
    $owner_user_id   = (int) get_post_meta( $profile_id, 'sc_owner_user_id', true );
    if ( ( $owner_user_id <= 0 || $owner_user_id !== $current_user_id ) && ! current_user_can( 'manage_options' ) ) {
        $profile_url   = get_permalink( $profile_id );
        $perfiles_urls = get_user_meta( $current_user_id, 'smartcards_perfiles_urls', true );
        if ( ! is_array( $perfiles_urls ) ) {
            $perfiles_urls = [];
        }

        $is_owner_by_fallback = false;
        foreach ( $perfiles_urls as $perfil_data ) {
            $candidate_url = '';
            if ( is_array( $perfil_data ) && ! empty( $perfil_data['url'] ) ) {
                $candidate_url = sc_get_live_profile_url( $perfil_data['url'] );
            } elseif ( is_string( $perfil_data ) ) {
                $candidate_url = sc_get_live_profile_url( $perfil_data );
            }

            if ( '' === $candidate_url || '' === (string) $profile_url ) {
                continue;
            }

            if ( function_exists( 'sc_url_matches' ) ) {
                if ( sc_url_matches( $profile_url, $candidate_url ) ) {
                    $is_owner_by_fallback = true;
                    break;
                }
                continue;
            }

            $profile_path   = parse_url( $profile_url, PHP_URL_PATH );
            $candidate_path = parse_url( $candidate_url, PHP_URL_PATH );
            $profile_path   = trailingslashit( is_string( $profile_path ) ? $profile_path : '/' );
            $candidate_path = trailingslashit( is_string( $candidate_path ) ? $candidate_path : '/' );

            if ( $profile_path === $candidate_path ) {
                $is_owner_by_fallback = true;
                break;
            }
        }

        if ( $is_owner_by_fallback ) {
            update_post_meta( $profile_id, 'sc_owner_user_id', $current_user_id );
            $owner_user_id = $current_user_id;
        } else {
            return '<p>' . esc_html__( 'No tienes permisos para ver estas estadísticas.', 'smartcards' ) . '</p>';
        }
    }

    global $wpdb;
    $table = $wpdb->prefix . 'smartcards_events';

    $visitas = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE profile_id = %d AND event_type = %s",
            $profile_id,
            'profile_view'
        )
    );

    $guardar_contacto = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE profile_id = %d AND event_type = %s",
            $profile_id,
            'save_contact_click'
        )
    );

    $clicks_botones = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE profile_id = %d AND event_type = %s",
            $profile_id,
            'button_click'
        )
    );

    $buttons = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT button_key, COUNT(*) AS total
             FROM {$table}
             WHERE event_type = %s AND profile_id = %d
             GROUP BY button_key
             ORDER BY total DESC",
            'button_click',
            $profile_id
        )
    );

    $back_url = home_url( '/mis-smart-cards/' );

    wp_enqueue_script(
        'smartcards-chartjs',
        'https://cdn.jsdelivr.net/npm/chart.js',
        [],
        '4.4.0',
        true
    );
    wp_add_inline_script(
        'smartcards-frontend',
        'window.smartcardsL10n = window.smartcardsL10n || {}; window.smartcardsL10n.profile_id = ' . (int) $profile_id . ';',
        'before'
    );

    ob_start();
    ?>
    <div class="sc-profile-analytics">
      <div class="sc-analytics-header">
        <h2><?php esc_html_e( '📊 Estadísticas de tu Smart Card', 'smartcards' ); ?></h2>
        <p class="sc-analytics-subtitle"><strong><?php echo esc_html( get_the_title( $profile_id ) ); ?></strong></p>
      </div>

      <div class="sc-chart-filters">
        <button class="sc-chart-btn" data-days="7">7 días</button>
        <button class="sc-chart-btn" data-days="15">15 días</button>
        <button class="sc-chart-btn active" data-days="30">30 días</button>
      </div>

      <div class="sc-chart-container" style="margin-bottom:40px;">
        <canvas id="sc-visits-chart"></canvas>
      </div>

      <div class="sc-analytics-grid">
        <div class="sc-analytics-card">
          <div class="sc-analytics-card-title"><?php esc_html_e( 'Visitas', 'smartcards' ); ?></div>
          <div class="sc-analytics-card-value"><?php echo esc_html( $visitas ); ?></div>
        </div>
        <div class="sc-analytics-card">
          <div class="sc-analytics-card-title"><?php esc_html_e( 'Guardar contacto', 'smartcards' ); ?></div>
          <div class="sc-analytics-card-value"><?php echo esc_html( $guardar_contacto ); ?></div>
        </div>
        <div class="sc-analytics-card">
          <div class="sc-analytics-card-title"><?php esc_html_e( 'Clicks botones', 'smartcards' ); ?></div>
          <div class="sc-analytics-card-value"><?php echo esc_html( $clicks_botones ); ?></div>
        </div>
      </div>

      <?php
      $labels = [
        'sitio_web'   => 'Sitio web',
        'whatsapp'    => 'WhatsApp',
        'instagram'   => 'Instagram',
        'facebook'    => 'Facebook',
        'x'           => 'X',
        'tiktok'      => 'TikTok',
        'linkedin'    => 'LinkedIn',
        'youtube'     => 'YouTube',
        'google_maps' => 'Maps',
        'paypal'      => 'PayPal',
      ];
      ?>

      <h3 class="sc-analytics-section-title"><?php esc_html_e( 'Clicks por botón', 'smartcards' ); ?></h3>
      <div class="sc-analytics-table-wrap">
        <table class="sc-analytics-table widefat striped">
          <thead>
            <tr>
              <th><?php esc_html_e( 'Botón', 'smartcards' ); ?></th>
              <th><?php esc_html_e( 'Total', 'smartcards' ); ?></th>
            </tr>
          </thead>
          <tbody>
            <?php if ( ! empty( $buttons ) ) : ?>
              <?php foreach ( $buttons as $row ) : ?>
                <?php
                $key = sanitize_key( (string) $row->button_key );
                $label = isset( $labels[ $key ] ) ? $labels[ $key ] : $row->button_key;
                ?>
                <tr>
                  <td><?php echo esc_html( $row->button_key ? $label : '—' ); ?></td>
                  <td><?php echo esc_html( (int) $row->total ); ?></td>
                </tr>
              <?php endforeach; ?>
            <?php else : ?>
              <tr>
                <td colspan="2" class="sc-analytics-empty">
                  <strong>Aún no tienes interacción registrada.</strong><br>
                  Comparte tu Smart Card para empezar a ver estadísticas.
                </td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <div class="sc-analytics-actions">
        <a class="smartcard-btn sc-analytics-back-btn" href="<?php echo esc_url( $back_url ); ?>">
          <?php esc_html_e( '⬅ Volver a Mis Smart Cards', 'smartcards' ); ?>
        </a>
      </div>
    </div>
    <?php
    return ob_get_clean();
}

// SmartCards Plugin — Sección “Ajustes”
// Shortcode [smartcards_ajustes]: permite editar nombre/apellido, enviar sugerencias y más.

// ✅ PASO 1: Registrar el Shortcode [smartcards_ajustes]
add_shortcode('smartcards_ajustes', 'smartcards_render_ajustes');
function smartcards_render_ajustes() {
    if ( ! is_user_logged_in() ) {
        return '<p>' . __( '🔒 Debes iniciar sesión para ver esta sección.', 'smartcards' ) . '</p>';
    }
    $current_user = wp_get_current_user();
    ob_start();

    // — Procesar nombre/apellido
    if ( isset( $_POST['actualizar_nombre_apellido'] ) ) {
        update_user_meta( $current_user->ID, 'first_name', sanitize_text_field( $_POST['nombre'] ) );
        update_user_meta( $current_user->ID, 'last_name',  sanitize_text_field( $_POST['apellido'] ) );
        echo '<p>' . __( '✅ Nombre actualizado.', 'smartcards' ) . '</p>';
    }

    // — Procesar sugerencia
    if ( isset( $_POST['enviar_sugerencia'] ) ) {
        wp_mail(
            'info@smartcards.com.co',
            __( 'Nueva sugerencia de usuario', 'smartcards' ),
            sanitize_textarea_field( $_POST['sugerencia'] )
        );
        echo '<p>' . __( '✅ Gracias por tu sugerencia.', 'smartcards' ) . '</p>';
    }
    ?>
    <div class="form-container">
      <h2><?php _e( '🔧 Ajustes de tu cuenta', 'smartcards' ); ?></h2>

<?php
  // Esto crea el checkbox con id="sc_lang_toggle"
  $current = get_locale(); // lee el locale activo (es_ES o en_US)
?>
<div class="sc-lang-toggle" style="margin-top:2rem; display:flex; align-items:center;">
  <input
    type="checkbox"
    id="sc_lang_toggle"
    <?php checked( $current, 'en_US' ); ?>
  >
  <!-- Ahora el slider es un label, así es clicable -->
  <label class="slider" for="sc_lang_toggle"></label>
  <!-- Texto aparte -->
  <label for="sc_lang_toggle" style="margin-left:0.5rem; cursor:pointer;">
    <?php _e( 'English mode', 'smartcards' ); ?>
  </label>
</div>

      <!-- Nombre / Apellido -->
      <form method="post" style="margin-bottom:1.5rem;">
        <label><?php _e( 'Nombre', 'smartcards' ); ?></label>
        <input type="text"
               name="nombre"
               value="<?php echo esc_attr( get_user_meta( $current_user->ID, 'first_name', true ) ); ?>"
               required>

        <label style="margin-top:1rem;"><?php _e( 'Apellido', 'smartcards' ); ?></label>
        <input type="text"
               name="apellido"
               value="<?php echo esc_attr( get_user_meta( $current_user->ID, 'last_name', true ) ); ?>"
               required>

        <button class="form-button"
                name="actualizar_nombre_apellido"
                type="submit">
          <?php _e( 'Guardar Cambios', 'smartcards' ); ?>
        </button>
      </form>

      <!-- Invitar por WhatsApp -->
<?php
  // Texto de invitación
  $invite_text  = sprintf(
    __( '¡Hola! Soy %s y te invito a usar Smart Cards. https://smartcards.com.co/', 'smartcards' ),
    $current_user->first_name
  );
  $encoded_text = urlencode( $invite_text );
  // Enlace web por defecto
  $web_link     = 'https://api.whatsapp.com/send?text=' . $encoded_text;
?>
<a 
  class="form-button invite-whatsapp" 
  style="margin-bottom:1rem; display:block;"
  href="<?php echo esc_url( $web_link ); ?>"
>
  <?php esc_html_e( '🎁 Invitar por WhatsApp', 'smartcards' ); ?>
</a>

      <!-- Cambiar contraseña -->
      <a class="form-button"
         style="margin-bottom:1rem; display:block;"
         href="<?php echo wp_lostpassword_url(); ?>">
        <?php _e( '🔐 Cambiar contraseña', 'smartcards' ); ?>
      </a>

      <!-- Historial de Activaciones -->
<h3 class="sc-activations-title">
  <?php esc_html_e( '📄 Historial de Activaciones', 'smartcards' ); ?>
</h3>
<?php
// Obtener todas las URLs de perfil activadas por el usuario
$raw_profiles = get_user_meta( get_current_user_id(), 'smartcards_perfiles_urls', true );
$activations  = [];

if ( is_array( $raw_profiles ) ) {
    foreach ( $raw_profiles as $profile ) {
        if ( ! empty( $profile['url'] ) ) {
            // Saneamos la URL y la almacenamos
            $activations[] = esc_url( sc_get_live_profile_url( $profile['url'] ) );
        }
    }
}

if ( ! empty( $activations ) ) : ?>
  <ul class="sc-activations-list">
    <?php foreach ( $activations as $url ) : ?>
      <li>
        <a href="<?php echo $url; ?>">
          <?php echo esc_html( $url ); ?>
        </a>
      </li>
    <?php endforeach; ?>
  </ul>
<?php else : ?>
  <p class="sc-no-activations">
    <?php esc_html_e( 'No tienes activaciones todavía.', 'smartcards' ); ?>
  </p>
<?php endif; ?>

      <!-- FAQ -->
      <div class="faq-section" style="margin-bottom:2rem;">
        <h3><?php _e( '❓ Preguntas Frecuentes', 'smartcards' ); ?></h3>

        <details>
          <summary><?php _e( '¿Qué es un crédito y cómo funciona?', 'smartcards' ); ?></summary>
          <p><?php _e( 'Un crédito te permite crear o modificar tu perfil Smart Card, incluyendo nombre, foto de perfil, portada y enlaces.', 'smartcards' ); ?></p>
        </details>

        <details>
          <summary><?php _e( '¿Cómo activo mi Smart Card?', 'smartcards' ); ?></summary>
          <p><?php _e( 'Tras adquirirla, completa el formulario y tu perfil se generará automáticamente con QR incluido.', 'smartcards' ); ?></p>
        </details>

        <details>
          <summary><?php _e( '¿Cómo comparto mi perfil?', 'smartcards' ); ?></summary>
          <p><?php _e( 'En “Mis Smart Cards” haz clic en Compartir y elige entre WhatsApp, correo o enlace.', 'smartcards' ); ?></p>
        </details>

        <details>
          <summary><?php _e( '¿Qué servicios complementarios ofrecemos?', 'smartcards' ); ?></summary>
          <p>
            <?php _e(
              'Además de las Smart Cards, ponemos a tu disposición:
• Diseño web profesional
• Posicionamiento SEO orgánico en Google
• Marketplace de plugins para WordPress
• Consultoría y gestión de campañas en Google Ads
• Publicidad en Google
• Planes de correo electrónico corporativos',
              'smartcards'
            ); ?>
          </p>
        </details>
      </div>

      <!-- Sugerencias -->
      <form method="post" style="margin-bottom:2rem;">
        <label><?php _e( '💡 Enviar sugerencia:', 'smartcards' ); ?></label>
        <textarea name="sugerencia"
                  rows="4"
                  style="width:100%; margin-bottom:0.5rem;"
                  placeholder="<?php _e( 'Escribe tu idea...', 'smartcards' ); ?>"></textarea>
        <button class="form-button"
                style="display:block;"
                type="submit"
                name="enviar_sugerencia">
          <?php _e( 'Enviar', 'smartcards' ); ?>
        </button>
      </form>

      <!-- Eliminar cuenta -->
      <a href="#"
         class="form-button"
         style="background-color:#e74c3c; margin-bottom:2rem; display:block;"
         onclick="if(confirm('<?php echo esc_js( __( '¿Seguro que deseas eliminar tu cuenta?', 'smartcards' ) ); ?>')){window.location.href='<?php echo wp_nonce_url( admin_url('admin-post.php?action=eliminar_cuenta_usuario'), 'eliminar_cuenta' ); ?>';}return false;">
        <?php _e( '🗑️ Eliminar mi cuenta', 'smartcards' ); ?>
      </a>
    </div>

    <style>
      /* 1) Mover “Guardar Cambios” un poco abajo */
      .form-container form button[name="actualizar_nombre_apellido"] {
        margin-top: 1.5rem;
      }

      /* 3) Tamaños de fuente */
      .form-container h2 { font-size: 2rem; margin-bottom: 1rem; }
      .form-container h3 { font-size: 1.75rem; }
      .form-container label,
      .form-container input,
      .form-container textarea,
      .form-container .form-button {
        font-size: 1rem;
      }

      /* Botones verdes por defecto */
      .form-button {
        background-color: #01a350 !important;
        color: #fff;
        padding: 10px 15px;
        border-radius: 5px;
        text-decoration: none;
        display: inline-block;
        text-align: center;
      }
      .form-button:hover {
        opacity: 0.9;
      }
    </style>
    <?php
    return ob_get_clean();
}

add_action('wp_ajax_sc_get_visits_range', 'sc_get_visits_range');
add_action('wp_ajax_nopriv_sc_get_visits_range', 'sc_get_visits_range');

function sc_get_visits_range() {
  global $wpdb;
  $profile_id = isset($_GET['profile_id']) ? absint($_GET['profile_id']) : 0;
  $days = isset($_GET['days']) ? absint($_GET['days']) : 30;

  if (!$profile_id) {
    wp_send_json_error(['message' => 'Perfil inválido'], 400);
  }

  $end = current_time('Y-m-d');
  $start = date('Y-m-d', strtotime("-{$days} days", strtotime($end)));

  $table = $wpdb->prefix . 'smartcards_events';

  $results = $wpdb->get_results(
    $wpdb->prepare(
      "SELECT DATE(created_at) as day, COUNT(*) as total
       FROM {$table}
       WHERE profile_id = %d
         AND event_type = %s
         AND created_at >= %s
       GROUP BY DATE(created_at)
       ORDER BY DATE(created_at) ASC",
      $profile_id,
      'profile_view',
      $start . ' 00:00:00'
    )
  );

  $data = [];
  foreach ($results as $row) {
    $data[] = [
      'day' => $row->day,
      'total' => intval($row->total),
    ];
  }

  wp_send_json_success($data);
}

// ----------------------------------------------------------------
// 8) Shortcode de diagnóstico IAP/RevenueCat: [sc_iap_diag]
// ----------------------------------------------------------------
add_shortcode('sc_iap_diag', function () {
    if ( ! is_user_logged_in() ) return '';
    ob_start(); ?>
    <div id="sc-iap-diag" class="sc-iap-diag">
      <h3>Diagnóstico IAP / RevenueCat</h3>
      <button id="sc-iap-run">Ejecutar diagnóstico</button>
      <pre id="sc-iap-out" aria-live="polite"></pre>
    </div>
    <?php
    return ob_get_clean();
});
