<?php
// smartcards-apple.php
if ( ! defined('ABSPATH') ) exit;

/**
 * REST: /wp-json/sc/v1/oauth/apple
 * Valida el authorizationCode/identityToken y crea/inicia sesión en WP.
 */
add_action('rest_api_init', function () {
  register_rest_route('sc/v1', '/oauth/apple', [
    'methods'  => 'POST',
    'permission_callback' => '__return_true',
    'callback' => 'sc_handle_apple_oauth',
  ]);
});

if ( ! function_exists('sc_handle_apple_oauth') ) {
function sc_handle_apple_oauth( WP_REST_Request $req ) {
  // Comprobar que SIWA está configurado (en wp-config.php)
  foreach (['SC_APPLE_TEAM_ID','SC_APPLE_KEY_ID','SC_APPLE_CLIENT_ID','SC_APPLE_P8'] as $c) {
    if ( ! defined($c) || ! constant($c) ) {
      return new WP_REST_Response(['ok'=>false,'error'=>'siwa_not_configured'], 500);
    }
  }

  $p      = $req->get_json_params();
  $code   = isset($p['authorizationCode']) ? sanitize_text_field($p['authorizationCode']) : '';
  $idtok  = isset($p['identityToken'])     ? sanitize_text_field($p['identityToken'])     : '';
  $email  = isset($p['email'])             ? sanitize_email($p['email'])                  : '';
  $name   = isset($p['fullName'])          ? sanitize_text_field($p['fullName'])          : '';

  if ( ! $code && ! $idtok ) {
    return new WP_REST_Response(['ok'=>false,'error'=>'missing_payload'], 400);
  }

  // 1) client_secret (JWT ES256)
  $secret = sc_apple_client_secret( SC_APPLE_TEAM_ID, SC_APPLE_CLIENT_ID, SC_APPLE_KEY_ID, SC_APPLE_P8 );

  // 2) Intercambiar code -> tokens (preferido)
  $jwt = '';
  if ( $code ) {
    $resp = wp_remote_post('https://appleid.apple.com/auth/token', [
      'body' => [
        'grant_type'    => 'authorization_code',
        'code'          => $code,
        'client_id'     => SC_APPLE_CLIENT_ID,
        'client_secret' => $secret,
      ],
      'timeout' => 15,
    ]);
    if ( ! is_wp_error($resp) ) {
      $body = json_decode( wp_remote_retrieve_body($resp), true );
      if ( ! empty($body['id_token']) ) $jwt = $body['id_token'];
    }
  }
  if ( ! $jwt && $idtok ) $jwt = $idtok;
  if ( ! $jwt ) return new WP_REST_Response(['ok'=>false,'error'=>'token_exchange_failed'], 400);

  // 3) Validar claims mínimos
  $parts  = explode('.', $jwt);
  if ( count($parts) !== 3 ) return new WP_REST_Response(['ok'=>false,'error'=>'bad_token'], 400);
  $claims = json_decode( base64_decode( strtr($parts[1], '-_', '+/') ), true );
  if ( ! $claims || ($claims['aud'] ?? '') !== SC_APPLE_CLIENT_ID || ($claims['iss'] ?? '') !== 'https://appleid.apple.com' ) {
    return new WP_REST_Response(['ok'=>false,'error'=>'invalid_claims'], 400);
  }
  if ( ! empty($claims['exp']) && time() > (int) $claims['exp'] ) {
    return new WP_REST_Response(['ok'=>false,'error'=>'token_expired'], 400);
  }

  $apple_sub = sanitize_text_field($claims['sub']);
  if ( ! $email && ! empty($claims['email']) ) $email = sanitize_email($claims['email']);

  // 4) Vincular/crear usuario
  $user_id = sc_find_user_by_apple_sub($apple_sub);
  if ( ! $user_id && $email ) {
    $u = get_user_by('email', $email);
    if ( $u ) $user_id = (int) $u->ID;
  }
  if ( ! $user_id ) {
    $login = $email ? sanitize_user( current( explode('@', $email) ), true ) : 'apple_' . $apple_sub;
    if ( username_exists($login) ) $login .= '_' . wp_generate_password(4, false, false);
    $user_id = wp_insert_user([
      'user_login'   => $login,
      'user_pass'    => wp_generate_password(24, true, true),
      'user_email'   => $email,
      'display_name' => $name ?: $login,
      'role'         => get_option('default_role', 'subscriber'),
    ]);
    if ( is_wp_error($user_id) ) {
      return new WP_REST_Response(['ok'=>false,'error'=>$user_id->get_error_message()], 400);
    }
  }
  update_user_meta($user_id, 'apple_sub', $apple_sub);

  // 5) Iniciar sesión
  wp_set_auth_cookie($user_id, true);
  wp_set_current_user($user_id);

  return new WP_REST_Response(['ok'=>true,'user_id'=>$user_id], 200);
}}

/** Buscar usuario por apple_sub (metadato) */
if ( ! function_exists('sc_find_user_by_apple_sub') ) {
function sc_find_user_by_apple_sub($sub){
  global $wpdb;
  $id = $wpdb->get_var( $wpdb->prepare(
    "SELECT user_id FROM $wpdb->usermeta WHERE meta_key='apple_sub' AND meta_value=%s LIMIT 1", $sub
  ));
  return $id ? (int) $id : 0;
}}

/** Generar client_secret ES256 (JWT) */
if ( ! function_exists('sc_apple_client_secret') ) {
function sc_apple_client_secret($team_id, $client_id, $key_id, $p8){
  $b64 = function($d){ return rtrim(strtr(base64_encode(json_encode($d)), '+/', '-_'), '='); };
  $now = time();
  $header = ['alg' => 'ES256', 'kid' => $key_id];
  $claims = ['iss'=>$team_id, 'iat'=>$now, 'exp'=>$now + 60*60*24*180, 'aud'=>'https://appleid.apple.com', 'sub'=>$client_id];
  $input  = $b64($header) . '.' . $b64($claims);

  $key = openssl_pkey_get_private($p8);
  openssl_sign($input, $sig, $key, OPENSSL_ALGO_SHA256);

  // DER -> JOSE (r||s, 64 bytes)
  $hex = bin2hex($sig);
  $p=2; $p+=2; $p+=2; $rlen=hexdec(substr($hex,$p,2)); $p+=2; $r=substr($hex,$p,$rlen*2); $p+=$rlen*2;
  $p+=2; $slen=hexdec(substr($hex,$p,2)); $p+=2; $s=substr($hex,$p,$slen*2);
  $r=str_pad(ltrim($r,'0'),64,'0',STR_PAD_LEFT); $s=str_pad(ltrim($s,'0'),64,'0',STR_PAD_LEFT);
  $rs = hex2bin($r.$s);

  return $input . '.' . rtrim(strtr(base64_encode($rs), '+/', '-_'), '=');
}}

// ==========================================================
// Endurecer endpoint sc_iap_complete (versión básica)
// ==========================================================
add_action('wp_ajax_sc_iap_complete', 'sc_iap_complete_handler');
add_action('wp_ajax_nopriv_sc_iap_complete', 'sc_iap_complete_handler'); // si NO quieres permitir sin login, quita esta línea

function sc_iap_complete_handler() {
  // 1) Requiere login (recomendado)
  if ( ! is_user_logged_in() ) {
    wp_send_json_error(['message' => 'No autorizado']); 
  }
  $user_id = get_current_user_id();

  // 2) Leer JSON
  $raw = file_get_contents('php://input');
  $data = json_decode($raw, true) ?: [];

  $sku   = isset($data['productId'])    ? sanitize_text_field($data['productId']) : '';
  $txid  = isset($data['transactionId'])? sanitize_text_field($data['transactionId']) : '';
  $plat  = isset($data['platform'])     ? sanitize_text_field($data['platform']) : '';
  $rcpt  = isset($data['receipt'])      ? $data['receipt'] : null;

  // 3) Lista blanca de SKUs → cuántos créditos suma cada uno
  $sku_map = [
    'creditos_smartcards_1'  => 1,
    'creditos_smartcards_5'  => 5,
    'creditos_smartcards_10' => 10,
  ];
  if ( empty($sku) || !isset($sku_map[$sku]) ) {
    wp_send_json_error(['message' => 'SKU inválido']);
  }

  // 4) iOS: exigir recibo (para IAP reales)
  if ( strtolower($plat) === 'ios' ) {
    if ( empty($rcpt) ) {
      wp_send_json_error(['message' => 'Recibo iOS faltante']);
    }
  }

  // 5) Evitar duplicados por transactionId
  if ( !empty($txid) ) {
    $already = get_option('sc_iap_tx_'.$txid);
    if ( $already ) {
      wp_send_json_error(['message' => 'Transacción ya procesada']);
    }
  }

  // 6) Acreditar créditos
  $to_add = intval($sku_map[$sku]);
  $meta_key = 'smartcards_credits';
  $current  = intval( get_user_meta($user_id, $meta_key, true) );
  update_user_meta($user_id, $meta_key, $current + $to_add);

  // 7) Marcar el TX como usado (si vino txid)
  if ( !empty($txid) ) {
    update_option('sc_iap_tx_'.$txid, [
      'user' => $user_id,
      'sku'  => $sku,
      'ts'   => time(),
    ], false);
  }

  wp_send_json_success(['message' => 'Créditos acreditados', 'credits_added' => $to_add]);
}