<?php
/**
 * Endpoint REST: Publicar Smart Card (APP)
 * Flujo por fases:
 * - Publica SOLO desde draft
 * - Valida imágenes requeridas
 * - Genera VCF al publicar (con foto base64)
 * - Descuenta 1 crédito solo si todo sale bien
 */

if (!defined('ABSPATH')) exit;

add_action('rest_api_init', function () {
  register_rest_route('smartcards/v1', '/publish-card', [
    'methods'  => 'POST',
    'callback' => 'sc_publish_smartcard_rest',
    'permission_callback' => function () {
      return get_current_user_id() > 0;
    },
  ]);
});

if (!function_exists('sc_get_theme_cost')) {
  function sc_get_theme_cost($theme) {
    $map = [
      'classic'   => 0,
      'dark'      => 1,
      'corporate' => 2,
      'premium'   => 3,
    ];

    return isset($map[$theme]) ? (int) $map[$theme] : 0;
  }
}

/**
 * Inserta saltos de línea compatibles con vCard para payload base64.
 */
function sc_vcf_photo_line($prefix, $base64) {
  $max_line_len = 75;
  $available = max(1, $max_line_len - strlen($prefix));
  $chunks = str_split($base64, $available);

  if (empty($chunks)) {
    return '';
  }

  $line = $prefix . array_shift($chunks) . "\r\n";
  foreach ($chunks as $chunk) {
    $line .= ' ' . $chunk . "\r\n";
  }

  return $line;
}

if (!function_exists('sc_log_smartcard_publish_state')) {
  function sc_log_smartcard_publish_state($post_id, $stage = '') {
    $prefix = $stage ? '[SC DEBUG][' . $stage . ']' : '[SC DEBUG]';

    error_log($prefix . ' post_id: ' . (int) $post_id);

    $post = get_post($post_id);
    if (!$post instanceof WP_Post) {
      error_log('[SC ERROR] post no existe');
      return;
    }

    error_log($prefix . ' post_type: ' . (string) $post->post_type);
    error_log($prefix . ' status: ' . (string) $post->post_status);
    error_log($prefix . ' slug real: ' . (string) get_post_field('post_name', $post_id));
    error_log($prefix . ' permalink: ' . (string) get_permalink($post_id));
    error_log($prefix . ' direct url: ' . (string) add_query_arg([
      'post_type' => 'smartcards',
      'p' => (int) $post_id,
    ], home_url('/')));
  }
}

function sc_publish_smartcard_rest(WP_REST_Request $request) {
  $user_id      = get_current_user_id();
  $smartcard_id = (int) $request->get_param('smartcard_id');
  $cache_ttl    = 3600;

  if (!$user_id || !$smartcard_id) {
    return new WP_Error('invalid', 'Datos inválidos', ['status' => 400]);
  }

  sc_log_smartcard_publish_state($smartcard_id, 'before_publish');

  $post = get_post($smartcard_id);
  if (!$post || $post->post_type !== 'smartcards') {
    return new WP_Error('not_found', 'Smart Card no encontrada', ['status' => 404]);
  }

  if ((int) $post->post_author !== (int) $user_id) {
    return new WP_Error('forbidden', 'No tienes permisos', ['status' => 403]);
  }

  // Si ya está publicada, no recobra ni regenera VCF.
  if ($post->post_status === 'publish') {
    $permalink = get_permalink($smartcard_id);
    $public_url = (string) get_post_meta($smartcard_id, 'sc_public_url', true);
    $vcf_url = (string) get_post_meta($smartcard_id, 'sc_vcf_url', true);
    $cached_html = (string) get_post_meta($smartcard_id, 'sc_cached_html', true);
    $cached_time = (int) get_post_meta($smartcard_id, 'sc_cached_at', true);
    $is_cached = ($cached_html !== '' && (time() - $cached_time < $cache_ttl));

    if (!$public_url) {
      $public_url = $permalink;
      update_post_meta($smartcard_id, 'sc_public_url', $public_url);
    }

    if (!$vcf_url) {
      $existing_vcf_attachment_id = (int) get_post_meta($smartcard_id, 'sc_vcf_attachment_id', true);
      if ($existing_vcf_attachment_id > 0) {
        $vcf_url = (string) wp_get_attachment_url($existing_vcf_attachment_id);
        if ($vcf_url) {
          update_post_meta($smartcard_id, 'sc_vcf_url', $vcf_url);
        }
      }
    }

    if (!$is_cached) {
      $cached_html = (string) get_post_field('post_content', $smartcard_id);
      if ($cached_html !== '') {
        update_post_meta($smartcard_id, 'sc_cached_html', $cached_html);
        update_post_meta($smartcard_id, 'sc_cached_at', time());
        $is_cached = true;
      }
    }

    sc_log_smartcard_publish_state($smartcard_id, 'already_published');

    return [
      'success'    => true,
      'public_url' => $public_url ?: $permalink,
      'permalink'  => $permalink,
      'vcf_url'    => $vcf_url,
      'html'       => $cached_html,
      'cached'     => $is_cached,
      'status'     => 'publish',
    ];
  }

  if ($post->post_status !== 'draft') {
    return new WP_Error('invalid_state', 'Solo puedes publicar Smart Cards en estado draft', ['status' => 400]);
  }

  delete_post_meta($smartcard_id, 'sc_cached_html');
  delete_post_meta($smartcard_id, 'sc_cached_at');

  // Tema seleccionado (con fallback seguro).
  $theme = sanitize_key((string) $request->get_param('theme'));
  if (!$theme) {
    $theme = 'classic';
  }
  $allowed = ['classic', 'dark', 'corporate', 'premium'];
  if (!in_array($theme, $allowed, true)) {
    $theme = 'classic';
  }
  $publish_cost = 1;
  $theme_cost   = sc_get_theme_cost($theme);
  $total_cost   = $publish_cost + $theme_cost;

  // Créditos para tema.
  $credits = (int) get_user_meta($user_id, 'smartcards_credits', true);
  if ($credits < $total_cost) {
    return new WP_Error(
      'no_credits_theme',
      'No tienes créditos suficientes para este tema',
      ['status' => 400]
    );
  }

  // Validar imágenes requeridas
  $cover_id = (int) get_post_meta($smartcard_id, 'sc_cover_id', true);
  $avatar_id = (int) get_post_thumbnail_id($smartcard_id);
  $vcf_photo_id = (int) get_post_meta($smartcard_id, 'sc_vcf_photo_id', true);

  if ($cover_id <= 0) {
    return new WP_Error('missing_cover', 'Debes subir una imagen de portada antes de publicar', ['status' => 400]);
  }

  if ($avatar_id <= 0) {
    return new WP_Error('missing_avatar', 'Debes subir una foto de perfil (avatar) antes de publicar', ['status' => 400]);
  }

  $cover_url = wp_get_attachment_url($cover_id);
  $avatar_url = wp_get_attachment_url($avatar_id);

  if (!$cover_url) {
    return new WP_Error('invalid_cover', 'No se pudo obtener la URL de la portada', ['status' => 400]);
  }

  if (!$avatar_url) {
    return new WP_Error('invalid_avatar', 'No se pudo obtener la URL del avatar', ['status' => 400]);
  }

  // Datos de perfil
  $firstName = trim((string) $request->get_param('firstName'));
  $lastName  = trim((string) $request->get_param('lastName'));

  // fallback a base de datos
  if (!$firstName) {
    $firstName = trim((string) get_post_meta($smartcard_id, 'firstName', true));
  }

  if (!$lastName) {
    $lastName = trim((string) get_post_meta($smartcard_id, 'lastName', true));
  }

  if (!$firstName || !$lastName) {
    return new WP_Error('missing_name', 'Nombre y apellido son obligatorios', ['status' => 400]);
  }

  update_post_meta($smartcard_id, 'firstName', $firstName);
  update_post_meta($smartcard_id, 'lastName', $lastName);

  $fullName = trim($firstName . ' ' . $lastName);
  $jobTitle = (string) $request->get_param('jobTitle');
  if ($jobTitle) {
    update_post_meta($smartcard_id, 'jobTitle', $jobTitle);
  } else {
    $jobTitle = (string) get_post_meta($smartcard_id, 'jobTitle', true);
  }

  $company = (string) $request->get_param('company');
  if ($company) {
    update_post_meta($smartcard_id, 'company', $company);
  } else {
    $company = (string) get_post_meta($smartcard_id, 'company', true);
  }

  $email = (string) $request->get_param('email');
  if ($email) {
    update_post_meta($smartcard_id, 'email', $email);
  } else {
    $email = (string) get_post_meta($smartcard_id, 'email', true);
  }

  $phone = (string) $request->get_param('phone');
  if ($phone) {
    update_post_meta($smartcard_id, 'phone', $phone);
  } else {
    $phone = (string) get_post_meta($smartcard_id, 'phone', true);
  }

  $whatsapp = (string) $request->get_param('whatsapp');
  if ($whatsapp) {
    update_post_meta($smartcard_id, 'whatsapp', $whatsapp);
  }

  $instagram = (string) $request->get_param('instagram');
  if ($instagram) {
    update_post_meta($smartcard_id, 'instagram', $instagram);
  }

  $facebook = (string) $request->get_param('facebook');
  if ($facebook) {
    update_post_meta($smartcard_id, 'facebook', $facebook);
  }

  $x = (string) $request->get_param('x');
  if ($x) {
    update_post_meta($smartcard_id, 'x', $x);
  }

  $tiktok = (string) $request->get_param('tiktok');
  if ($tiktok) {
    update_post_meta($smartcard_id, 'tiktok', $tiktok);
  }

  $linkedin = (string) $request->get_param('linkedin');
  if ($linkedin) {
    update_post_meta($smartcard_id, 'linkedin', $linkedin);
  }

  $youtube = (string) $request->get_param('youtube');
  if ($youtube) {
    update_post_meta($smartcard_id, 'youtube', $youtube);
  }

  $googleMaps = (string) $request->get_param('googleMaps');
  if ($googleMaps) {
    update_post_meta($smartcard_id, 'googleMaps', $googleMaps);
  }

  $pay_wompi = (string) $request->get_param('pay_wompi');
  if ($pay_wompi) {
    update_post_meta($smartcard_id, 'pay_wompi', $pay_wompi);
  }

  $pay_epayco = (string) $request->get_param('pay_epayco');
  if ($pay_epayco) {
    update_post_meta($smartcard_id, 'pay_epayco', $pay_epayco);
  }

  $pay_paypal = (string) $request->get_param('pay_paypal');
  if ($pay_paypal) {
    update_post_meta($smartcard_id, 'pay_paypal', $pay_paypal);
  }

  $pay_bold = (string) $request->get_param('pay_bold');
  if ($pay_bold) {
    update_post_meta($smartcard_id, 'pay_bold', $pay_bold);
  }

  $pay_stripe = (string) $request->get_param('pay_stripe');
  if ($pay_stripe) {
    update_post_meta($smartcard_id, 'pay_stripe', $pay_stripe);
  }

  $pay_mercadopago = (string) $request->get_param('pay_mercadopago');
  if ($pay_mercadopago) {
    update_post_meta($smartcard_id, 'pay_mercadopago', $pay_mercadopago);
  }

  $pay_wise = (string) $request->get_param('pay_wise');
  if ($pay_wise) {
    update_post_meta($smartcard_id, 'pay_wise', $pay_wise);
  }

  $style = $request->get_param('style');
  if (is_array($style)) {
    $user_color = isset($style['primaryColor']) ? sanitize_hex_color((string) $style['primaryColor']) : '';
    $user_font_family = isset($style['fontName']) ? sc_clean_font_name((string) $style['fontName']) : '';
    if ($theme === 'dark') {
      delete_post_meta($smartcard_id, 'sc_user_color');
    } elseif ($user_color) {
      update_post_meta($smartcard_id, 'sc_user_color', $user_color);
    } else {
      delete_post_meta($smartcard_id, 'sc_user_color');
    }

    if ($user_font_family) {
      update_post_meta($smartcard_id, 'sc_font_family', $user_font_family);
    } else {
      delete_post_meta($smartcard_id, 'sc_font_family');
    }
  }

  $address = (string) $request->get_param('address');
  if ($address) {
    update_post_meta($smartcard_id, 'address', $address);
  }

  $city = (string) $request->get_param('city');
  if ($city) {
    update_post_meta($smartcard_id, 'city', $city);
  }

  $country = (string) $request->get_param('country');
  if ($country) {
    update_post_meta($smartcard_id, 'country', $country);
  }

  $phone2 = (string) $request->get_param('phone2');
  if ($phone2) {
    update_post_meta($smartcard_id, 'phone2', $phone2);
  }

  // Generar VCF (solo en publicación)
  $photo_attachment_id = $vcf_photo_id > 0 ? $vcf_photo_id : $avatar_id;
  $vcf_photo_line = '';

  if ($photo_attachment_id > 0) {
    $photo_file = get_attached_file($photo_attachment_id);
    if ($photo_file && file_exists($photo_file) && is_readable($photo_file)) {
      $filetype = wp_check_filetype($photo_file);
      $ext = strtolower((string) ($filetype['ext'] ?? ''));
      $type = '';

      if (in_array($ext, ['jpg', 'jpeg'], true)) {
        $type = 'JPEG';
      } elseif ($ext === 'png') {
        $type = 'PNG';
      }

      if ($type) {
        $photo_binary = file_get_contents($photo_file);
        if ($photo_binary !== false) {
          $photo_b64 = base64_encode($photo_binary);
          $vcf_photo_line = sc_vcf_photo_line('PHOTO;ENCODING=b;TYPE=' . $type . ':', $photo_b64);
        }
      }
    }
  }

  $vcf  = "BEGIN:VCARD\r\n";
  $vcf .= "VERSION:3.0\r\n";
  $vcf .= 'N:' . $lastName . ';' . $firstName . ";;;;\r\n";
  $vcf .= 'FN:' . $fullName . "\r\n";
  if ($company)  $vcf .= 'ORG:' . $company . "\r\n";
  if ($jobTitle) $vcf .= 'TITLE:' . $jobTitle . "\r\n";
  if ($email)    $vcf .= 'EMAIL;TYPE=INTERNET:' . $email . "\r\n";
  if ($phone)    $vcf .= 'TEL;TYPE=CELL:' . $phone . "\r\n";

  // Dirección completa
  $address = get_post_meta($smartcard_id, 'address', true);
  $city    = get_post_meta($smartcard_id, 'city', true);
  $country = get_post_meta($smartcard_id, 'country', true);

  if ($address || $city || $country) {
    $vcf .= 'ADR:;;' . $address . ';' . $city . ';;;' . $country . "\r\n";
  }

  // Segundo teléfono
  $phone2 = get_post_meta($smartcard_id, 'phone2', true);
  if ($phone2) {
    $vcf .= 'TEL;TYPE=WORK:' . $phone2 . "\r\n";
  }

  // URL (puedes usar website o redes principales)
  $website = get_post_meta($smartcard_id, 'website', true);
  if ($website) {
    $vcf .= 'URL:' . $website . "\r\n";
  }

  // Nota
  $note = get_post_meta($smartcard_id, 'notes', true);
  if ($note) {
    $vcf .= 'NOTE:' . $note . "\r\n";
  }

  if ($vcf_photo_line) $vcf .= $vcf_photo_line;
  $vcf .= "END:VCARD\r\n";

  $upload = wp_upload_bits(
    'smartcard-' . sanitize_title($fullName) . '-' . time() . '.vcf',
    null,
    $vcf
  );

  if (!empty($upload['error'])) {
    return new WP_Error('vcf_error', 'No se pudo generar el VCF: ' . $upload['error'], ['status' => 500]);
  }

  $vcf_file = $upload['file'];
  $vcf_url  = $upload['url'];

  $attachment = [
    'post_mime_type' => 'text/vcard',
    'post_title'     => basename($vcf_file),
    'post_content'   => '',
    'post_status'    => 'inherit',
  ];

  $vcf_attachment_id = wp_insert_attachment($attachment, $vcf_file);
  if (is_wp_error($vcf_attachment_id)) {
    return new WP_Error('vcf_attachment_error', 'No se pudo registrar el VCF en la biblioteca', ['status' => 500]);
  }

  require_once ABSPATH . 'wp-admin/includes/image.php';
  wp_update_attachment_metadata($vcf_attachment_id, wp_generate_attachment_metadata($vcf_attachment_id, $vcf_file));

  update_post_meta($smartcard_id, 'sc_vcf_attachment_id', (int) $vcf_attachment_id);
  update_post_meta($smartcard_id, 'sc_vcf_url', $vcf_url);

  // Construir HTML del perfil público
  $iconos = [
    'whatsapp' => ['https://wa.me/', 'whatsapp.svg'],
    'instagram' => ['https://instagram.com/', 'instagram.svg'],
    'facebook' => ['', 'facebook.svg'],
    'x' => ['https://x.com/', 'x.svg'],
    'tiktok' => ['https://tiktok.com/@', 'tiktok.svg'],
    'linkedin' => ['', 'linkedin.svg'],
    'youtube' => ['', 'youtube.svg'],

    'google_maps' => ['', 'maps.svg'],
    'googleMaps' => ['', 'maps.svg'],

    'pay_wompi' => ['', 'wompi.svg'],
    'pay_epayco' => ['', 'epayco.svg'],
    'pay_paypal' => ['', 'paypal.svg'],
    'pay_bold' => ['', 'bold.svg'],
    'pay_stripe' => ['', 'stripe.svg'],
    'pay_mercadopago' => ['', 'mercadopago.svg'],
    'pay_wise' => ['', 'wise.svg'],
    'pay_payu' => ['', 'payu.svg'],

    'website' => ['', 'navegador.svg'],
    'sitio_web' => ['', 'navegador.svg'],
  ];

  $html  = '<div class="profile-cover-wrapper">';
  $html .= '  <img class="cover-image" src="' . esc_url($cover_url) . '" alt="Cover">';
  $html .= '  <div class="profile-image-wrapper">';
  $html .= '    <img class="profile-image" src="' . esc_url($avatar_url) . '" alt="Foto de perfil">';
  $html .= '  </div>';
  $html .= '</div>';

  $html .= '<div class="perfil-publico">';
  $html .= '  <h2>' . esc_html($fullName) . '</h2>';
  if ($jobTitle) {
    $html .= '  <h4>' . esc_html($jobTitle) . '</h4>';
  }

  $html .= '  <a href="' . esc_url($vcf_url) . '" target="_blank" rel="noopener" download class="btn-contacto-link sc-btn-contact">';
  $html .= '    Guardar contacto ↓';
  $html .= '  </a>';

  $html .= '  <div class="redes-sociales">';
  $allow_dynamic_socials = ('premium' === $theme);
  foreach ($iconos as $meta_key => $info) {
    $valor = trim((string) get_post_meta($smartcard_id, $meta_key, true));
    if (!$valor) continue;

    $base = $info[0];
    $icon = $info[1];
    $url = $base ? ($base . urlencode($valor)) : $valor;
    $html .= '<a href="' . esc_url($url) . '" target="_blank" rel="noopener" class="btn-red-social ' . esc_attr($meta_key) . '" data-sc-event="button_click" data-sc-button="' . esc_attr($meta_key) . '">';
    $html .= sc_get_social_icon_markup($icon, $meta_key, $allow_dynamic_socials);
    $html .= '</a>';
  }
  $html .= '  </div>';

  $html .= '  <div id="qr-container"></div>';
  $html .= '</div>';

  if ($theme === 'dark') {
    $html .= '<style>';
    $html .= ':root{--sc-primary:#ffffff;--sc-text:#000000;--sc-text-dynamic:#ffffff;}';
    $html .= '.theme-dark .perfil-publico h2,.theme-dark .perfil-publico h4,.perfil-publico h2,.perfil-publico h4{color:#ffffff !important;}';
    $html .= '.theme-dark .sc-btn-contact,.sc-btn-contact{background:#ffffff !important;color:#000000 !important;}';
    $html .= '.theme-dark .sc-btn-contact svg,.theme-dark .sc-btn-contact i,.sc-btn-contact svg,.sc-btn-contact i{color:#000000 !important;fill:#000000 !important;}';
    $html .= '</style>';
  }

  $update = wp_update_post([
    'ID'           => $smartcard_id,
    'post_title'   => $fullName,
    'post_content' => $html,
    'post_status'  => 'publish',
  ], true);

  if (is_wp_error($update)) {
    return new WP_Error('publish_failed', $update->get_error_message(), ['status' => 500]);
  }

  sc_log_smartcard_publish_state($smartcard_id, 'after_publish');

  $permalink = get_permalink($smartcard_id);

  update_post_meta($smartcard_id, 'sc_cached_html', $html);
  update_post_meta($smartcard_id, 'sc_cached_at', time());

  update_post_meta($smartcard_id, 'sc_public_url', $permalink);
  update_post_meta($smartcard_id, 'sc_source', 'app');
  update_post_meta($smartcard_id, 'sc_theme', $theme);

  $new_balance = $credits - $total_cost;
  update_user_meta($user_id, 'smartcards_credits', $new_balance);

  return [
    'success'    => true,
    'public_url' => $permalink,
    'permalink'  => $permalink,
    'vcf_url'    => $vcf_url,
    'html'       => $html,
    'cached'     => false,
    'status'     => 'publish',
  ];
}
