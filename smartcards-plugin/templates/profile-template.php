<?php
/**
 * SmartCards Profile Template
 * Standalone template for app profiles.
 */
if (!defined('ABSPATH')) exit;

global $post;

if (!$post instanceof WP_Post) {
    exit;
}

$post_id = (int) $post->ID;

// Cover + avatar (compatibilidad con perfiles existentes).
$cover_id   = (int) get_post_meta($post_id, 'sc_cover_id', true);
$avatar_id  = (int) get_post_thumbnail_id($post_id);
$cover_url  = $cover_id ? wp_get_attachment_url($cover_id) : '';
$avatar_url = $avatar_id ? wp_get_attachment_url($avatar_id) : '';

$first_name = (string) get_post_meta($post_id, 'firstName', true);
$last_name  = (string) get_post_meta($post_id, 'lastName', true);
$job_title  = (string) get_post_meta($post_id, 'jobTitle', true);
$full_name  = trim($first_name . ' ' . $last_name);
if (!$full_name) {
    $full_name = get_the_title($post_id);
}

// Tema dinámico.
$theme = sanitize_key((string) get_post_meta($post_id, 'sc_theme', true));
if (!$theme) {
    $theme = 'classic';
}

$allowed_themes = ['classic', 'dark', 'corporate', 'premium'];
if (!in_array($theme, $allowed_themes, true)) {
    $theme = 'classic';
}

$theme_body_class_map = [
    'classic'   => 'theme-esencial',
    'dark'      => 'theme-dark',
    'corporate' => 'theme-corporativo',
    'premium'   => 'theme-premium',
];
$theme_body_class = $theme_body_class_map[$theme] ?? 'theme-esencial';
$allow_dynamic_socials = ( 'premium' === $theme );
$profile_styles = sc_get_profile_style_settings($post_id, $theme);
$user_color = $profile_styles['primary_color'];
$font_urls = $profile_styles['font_urls'];
$is_dark_theme = ('dark' === $theme);
$user_text_color = $profile_styles['primary_text_color'];
$button_text_color = $profile_styles['button_text_color'];
$sc_color_redes = sanitize_hex_color((string) get_post_meta($post_id, 'sc_color_redes', true));
if (!$sc_color_redes) {
    $sc_color_redes = $profile_styles['social_color'] ?: $user_color;
}

$sc_color_button = sanitize_hex_color((string) get_post_meta($post_id, 'sc_color_button', true));
if (!$sc_color_button) {
    $sc_color_button = $profile_styles['button_color'] ?: $user_color;
}

if ($is_dark_theme) {
    $body_style = '--user-color:' . $user_color . ';--sc-color-redes:' . $sc_color_redes . ';--sc-color-button:' . $sc_color_button . ';--sc-primary:#ffffff;--sc-text:#000000;--sc-text-dynamic:#ffffff;';
} else {
    $body_style = $user_color
        ? '--user-color:' . $user_color . ';--sc-color-redes:' . $sc_color_redes . ';--sc-color-button:' . $sc_color_button . ';--sc-text-dynamic:' . $user_color . ';--sc-text:' . $user_text_color . ';'
        : '';
}

$theme_css_path = plugin_dir_path(__FILE__) . '../assets/themes/' . $theme . '.css';
if (!file_exists($theme_css_path)) {
    $theme = 'classic';
}
$theme_css_url = plugin_dir_url(__FILE__) . '../assets/themes/' . $theme . '.css';

$icon_map = [
    'whatsapp'        => ['icon' => 'whatsapp.svg',   'type' => 'whatsapp'],
    'instagram'       => ['icon' => 'instagram.svg',  'type' => 'instagram'],
    'facebook'        => ['icon' => 'facebook.svg',   'type' => 'url_or_handle', 'base' => 'https://facebook.com/'],
    'linkedin'        => ['icon' => 'linkedin.svg',   'type' => 'url_or_handle', 'base' => 'https://linkedin.com/in/'],
    'x'               => ['icon' => 'x.svg',          'type' => 'x'],
    'tiktok'          => ['icon' => 'tiktok.svg',     'type' => 'tiktok'],
    'youtube'         => ['icon' => 'youtube.svg',    'type' => 'url_or_handle', 'base' => 'https://youtube.com/@'],
    'googleMaps'      => ['icon' => 'maps.svg',       'type' => 'google_maps'],
    'website'         => ['icon' => 'navegador.svg',  'type' => 'website'],
    'pay_wompi'       => ['icon' => 'wompi.svg',      'type' => 'url'],
    'pay_epayco'      => ['icon' => 'epayco.svg',     'type' => 'url'],
    'pay_paypal'      => ['icon' => 'paypal.svg',     'type' => 'url'],
    'pay_payu'        => ['icon' => 'payu.svg',       'type' => 'url'],
    'pay_bold'        => ['icon' => 'bold.svg',       'type' => 'url'],
    'pay_stripe'      => ['icon' => 'stripe.svg',     'type' => 'url'],
    'pay_mercadopago' => ['icon' => 'mercadopago.svg','type' => 'url'],
    'pay_wise'        => ['icon' => 'wise.svg',       'type' => 'url'],
];

$has_url = static function ($value) {
    return (bool) preg_match('#^https?://#i', (string) $value);
};

$normalize_url = static function ($value) use ($has_url) {
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }
    if ($has_url($value)) {
        return $value;
    }
    return 'https://' . ltrim($value, '/');
};

$build_profile_url = static function ($raw, $config) use ($has_url, $normalize_url) {
    $raw = trim((string) $raw);
    if ($raw === '') {
        return '';
    }
    if ($has_url($raw)) {
        return $raw;
    }

    $type = isset($config['type']) ? $config['type'] : 'url';
    $base = isset($config['base']) ? $config['base'] : '';

    if ($type === 'whatsapp') {
        $number = preg_replace('/\D+/', '', $raw);
        return $number ? 'https://wa.me/' . $number : '';
    }

    if ($type === 'instagram') {
        return 'https://instagram.com/' . ltrim($raw, '@');
    }

    if ($type === 'x') {
        return 'https://x.com/' . ltrim($raw, '@');
    }

    if ($type === 'tiktok') {
        return 'https://tiktok.com/@' . ltrim($raw, '@');
    }

    if ($type === 'google_maps') {
        return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($raw);
    }

    if ($type === 'website' || $type === 'url') {
        return $normalize_url($raw);
    }

    if ($type === 'url_or_handle') {
        return $base ? ($base . ltrim($raw, '@')) : $normalize_url($raw);
    }

    return $normalize_url($raw);
};

$links = [];
foreach ($icon_map as $meta_key => $config) {
    $raw = get_post_meta($post_id, $meta_key, true);
    if ($raw === '' || $raw === null) {
        continue;
    }

    $url = $build_profile_url($raw, $config);
    if (!$url) {
        continue;
    }

    $links[] = [
        'key'  => $meta_key,
        'url'  => $url,
        'icon' => $config['icon'],
    ];
}

// Guardar contacto (compatibilidad: URL directa o attachment).
$vcf_url = (string) get_post_meta($post_id, 'sc_vcf_url', true);
if (!$vcf_url) {
    $vcf_attachment_id = (int) get_post_meta($post_id, 'sc_vcf_attachment_id', true);
    if ($vcf_attachment_id) {
        $vcf_url = (string) wp_get_attachment_url($vcf_attachment_id);
    }
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="<?php echo esc_url($theme_css_url); ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<?php foreach ($font_urls as $font_url): ?>
<link rel="stylesheet" href="<?php echo esc_url($font_url); ?>">
<?php endforeach; ?>
<style>
  :root {
    --sc-color-primary: <?php echo esc_html($profile_styles['primary_color']); ?>;
    --sc-color-role: <?php echo esc_html($profile_styles['role_color']); ?>;
    --sc-color-button: <?php echo esc_html($sc_color_button); ?>;
    --sc-color-button-text: <?php echo esc_html($button_text_color); ?>;
    --sc-color-redes: <?php echo esc_html($sc_color_redes); ?>;
    --sc-font-name: "<?php echo esc_attr($profile_styles['name_font']); ?>", "Montserrat", sans-serif;
    --sc-font-role: "<?php echo esc_attr($profile_styles['role_font']); ?>", "Montserrat", sans-serif;
  }
</style>
<?php if ($is_dark_theme): ?>
<style>
  :root {
    --sc-primary: #ffffff;
    --sc-text: #000000;
    --sc-text-dynamic: #ffffff;
  }

  .theme-dark .perfil-publico h2,
  .theme-dark .perfil-publico h4 {
    color: #ffffff !important;
  }

  .theme-dark .sc-btn-contact {
    background: #ffffff !important;
    color: #000000 !important;
  }

  .theme-dark .sc-btn-contact svg,
  .theme-dark .sc-btn-contact i {
    color: #000000 !important;
    fill: #000000 !important;
  }

  .perfil-publico,
  .perfil-publico h2,
  .perfil-publico h4,
  .sc-btn-contact,
  .btn-red-social {
    font-family: var(--sc-font-family) !important;
  }
</style>
<?php else: ?>
<style>
  :root {
<?php if ($user_color): ?>
    --user-color: <?php echo esc_html($user_color); ?>;
    --sc-text-dynamic: <?php echo esc_html($user_color); ?>;
    --sc-text: <?php echo esc_html($user_text_color); ?>;
<?php endif; ?>
  }

  .theme-corporativo {
    --sc-primary: var(--user-color);
    --sc-text-dynamic: var(--user-color);
  }

  .perfil-publico,
  .perfil-publico h2,
  .perfil-publico h4,
  .sc-btn-contact,
  .btn-red-social {
    font-family: var(--sc-font-family) !important;
  }

  .theme-corporativo .btn-red-social {
    border: none !important;
    background: transparent !important;
    box-shadow: none !important;
  }

  .theme-corporativo .btn-red-social img,
  .theme-corporativo .btn-red-social svg.icon-social {
    border: none !important;
    box-shadow: none !important;
    background: transparent !important;
    border-radius: 12px;
  }
</style>
<?php endif; ?>
<style>
  html[data-font-loaded="true"] {
    --sc-font-family: "<?php echo esc_attr($profile_styles['base_font']); ?>", "Montserrat", sans-serif;
    --sc-font-name: "<?php echo esc_attr($profile_styles['name_font']); ?>", "Montserrat", sans-serif;
    --sc-font-role: "<?php echo esc_attr($profile_styles['role_font']); ?>", "Montserrat", sans-serif;
  }
</style>
<?php wp_head(); ?>
</head>
<body class="smartcards-profile sc-theme-<?php echo esc_attr($theme); ?> <?php echo esc_attr($theme_body_class); ?>"<?php echo $body_style ? ' style="' . esc_attr($body_style) . '"' : ''; ?>>
<?php
echo '<pre style="color:red">';
print_r([
    'sc_color_redes'  => $sc_color_redes,
    'sc_color_button' => $sc_color_button,
    'user_color'      => $user_color,
]);
echo '</pre>';
?>

<div class="profile-cover-wrapper">
<?php if ($cover_url): ?>
<img class="cover-image" src="<?php echo esc_url($cover_url); ?>" alt="<?php echo esc_attr($full_name); ?>">
<?php endif; ?>

<div class="profile-image-wrapper">
<?php if ($avatar_url): ?>
<img class="profile-image" src="<?php echo esc_url($avatar_url); ?>" alt="<?php echo esc_attr($full_name); ?>">
<?php endif; ?>
</div>
</div>

<div class="perfil-publico" data-sc-profile-id="<?php echo esc_attr($post_id); ?>">
<h2><?php echo esc_html($full_name); ?></h2>

<?php if ($job_title): ?>
<h4><?php echo esc_html($job_title); ?></h4>
<?php endif; ?>

<?php if ($vcf_url): ?>
<a href="<?php echo esc_url($vcf_url); ?>" target="_blank" rel="noopener" download class="btn-contacto-link sc-btn-contact btn-guardar-contacto">
Guardar contacto ↓
</a>
<?php endif; ?>

<?php if (!empty($links)): ?>
<div class="redes-sociales">
<?php foreach ($links as $link): ?>
<a href="<?php echo esc_url($link['url']); ?>"
target="_blank"
rel="noopener"
class="btn-red-social <?php echo esc_attr($link['key']); ?>"
data-sc-event="button_click"
data-sc-button="<?php echo esc_attr($link['key']); ?>">
<?php echo sc_get_social_icon_markup($link['icon'], $link['key'], $allow_dynamic_socials); ?>
</a>
<?php endforeach; ?>
</div>
<?php endif; ?>

<div id="qr-container"></div>
</div>

<?php wp_footer(); ?>
<script>
<?php if ($is_dark_theme): ?>
document.documentElement.style.setProperty('--sc-primary', '#ffffff');
document.documentElement.style.setProperty('--sc-text', '#000000');
document.documentElement.style.setProperty('--sc-text-dynamic', '#ffffff');
<?php else: ?>
<?php if ($user_color): ?>
document.documentElement.style.setProperty('--user-color', '<?php echo esc_js($user_color); ?>');
document.documentElement.style.setProperty('--sc-text-dynamic', '<?php echo esc_js($user_color); ?>');
document.documentElement.style.setProperty('--sc-text', '<?php echo esc_js($user_text_color); ?>');
<?php endif; ?>
<?php endif; ?>

(function () {
  if (!document.fonts || !document.fonts.ready || typeof document.fonts.ready.then !== 'function') {
    document.documentElement.setAttribute('data-font-loaded', 'true');
    return;
  }

  document.fonts.ready
    .then(() => {
      document.documentElement.setAttribute('data-font-loaded', 'true');
    })
    .catch(() => {
      document.documentElement.setAttribute('data-font-loaded', 'true');
    });
}());
</script>
</body>
</html>
