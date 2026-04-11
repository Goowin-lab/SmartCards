<?php
add_action('rest_api_init', function () {

  register_rest_route('smartcards/v1', '/magic/request', [
    'methods'  => 'POST',
    'args'     => [
      'email' => ['required' => true, 'sanitize_callback' => 'sanitize_email'],
    ],
    'permission_callback' => '__return_true',
    'callback' => function (WP_REST_Request $req) {
      try {
        $email = $req->get_param('email');
        if (!is_email($email)) {
          return new WP_REST_Response(['message' => 'Email inválido'], 400);
        }
        $user = get_user_by('email', $email);

        // Respuesta genérica por seguridad
        if (!$user) {
          return new WP_REST_Response(['message' => 'Si el email existe, te enviaremos un acceso'], 200);
        }

        $token   = wp_generate_password(48, false);
        $expires = time() + 15 * 60; // 15 min

        // ✅ usar wp_hash_password para poder validar con wp_check_password
        $hash    = wp_hash_password($token);
        update_user_meta($user->ID, 'sc_magic_token_hash', $hash);
        update_user_meta($user->ID, 'sc_magic_token_expires', $expires);

        $deeplink    = 'smartcards://magic?token=' . rawurlencode($token);
        $webFallback = 'https://app.smartcards.com.co/magic?token=' . rawurlencode($token);

        $subject = 'Acceso rápido a Smartcards';
        $msg  = "Hola {$user->display_name},\n\n";
        $msg .= "Entra sin contraseña (vence en 15 minutos):\n$deeplink\n\n";
        $msg .= "Si no se abre la app, usa este enlace web:\n$webFallback\n\n";
        $msg .= "Si no fuiste tú, ignora este correo.\n";

        // (opcional) fuerza texto plano
        // add_filter('wp_mail_content_type', function(){ return 'text/plain'; });
        $sent = wp_mail($email, $subject, $msg);
        // remove_filter('wp_mail_content_type', '__return_false');

        if (!$sent) {
          return new WP_REST_Response(['message' => 'No se pudo enviar el correo'], 500);
        }

        return new WP_REST_Response(['message' => 'Si el email existe, te enviamos un acceso'], 200);
      } catch (\Throwable $e) {
        return new WP_REST_Response(['message' => 'Error interno', 'error' => $e->getMessage()], 500);
      }
    }
  ]);

  register_rest_route('smartcards/v1', '/magic/exchange', [
    'methods'  => 'POST',
    'args'     => [
      'token' => ['required' => true, 'sanitize_callback' => 'sanitize_text_field'],
    ],
    'permission_callback' => '__return_true',
    'callback' => function (WP_REST_Request $req) {
      try {
        $token = $req->get_param('token');
        $uid   = 0;

        $users = get_users(['meta_key' => 'sc_magic_token_hash', 'fields' => ['ID']]);
        foreach ($users as $u) {
          $hash = get_user_meta($u->ID, 'sc_magic_token_hash', true);
          $exp  = intval(get_user_meta($u->ID, 'sc_magic_token_expires', true));
          if ($hash && wp_check_password($token, $hash) && $exp > time()) { $uid = $u->ID; break; }
        }
        if (!$uid) return new WP_REST_Response(['message' => 'Token inválido o vencido'], 400);

        // One-time: invalida token
        delete_user_meta($uid, 'sc_magic_token_hash');
        delete_user_meta($uid, 'sc_magic_token_expires');

        // Genera JWT
        if (function_exists('jwt_auth_generate_token')) {
          $jwt = jwt_auth_generate_token($uid);
          if (is_wp_error($jwt)) return new WP_REST_Response(['message' => 'No se pudo generar el token'], 500);
          return new WP_REST_Response([
            'token' => $jwt['token'],
            'user'  => [
              'id'           => $uid,
              'display_name' => get_the_author_meta('display_name', $uid),
              'email'        => get_the_author_meta('user_email', $uid),
            ],
          ], 200);
        }
        return new WP_REST_Response(['message' => 'JWT plugin no disponible'], 500);
      } catch (\Throwable $e) {
        return new WP_REST_Response(['message' => 'Error interno', 'error' => $e->getMessage()], 500);
      }
    }
  ]);

});


// hooks de REST
add_action('rest_api_init', function () {
  register_rest_route('smartcards/v1', '/otp/request', [
    'methods'  => 'POST',
    'callback' => 'sc_otp_request',
    'permission_callback' => '__return_true',
  ]);

  register_rest_route('smartcards/v1', '/otp/verify', [
    'methods'  => 'POST',
    'callback' => 'sc_otp_verify',
    'permission_callback' => '__return_true',
  ]);
});

function sc_find_user_by_email($email) {
  $user = get_user_by('email', $email);
  if (!$user) return new WP_Error('not_found', 'No existe un usuario con ese correo', ['status'=>404]);
  return $user;
}

function sc_otp_key_for($user_id){ return "sc_otp_user_{$user_id}"; }

/**
 * POST { email }
 */
function sc_otp_request( WP_REST_Request $req ) {
  $email = sanitize_email( $req->get_param('email') );
  if ( ! is_email($email) ) {
    return new WP_Error('bad_email','Email inválido',['status'=>400]);
  }

  $user = sc_find_user_by_email($email);
  if ( is_wp_error($user) ) {
    return $user; // devolver WP_Error permite a la REST API responder bien
  }

  // Generar OTP de 6 dígitos
  $code = str_pad( strval( wp_rand(0, 999999) ), 6, '0', STR_PAD_LEFT );

  // Guardar hash + expiración 20 min en transient
  $payload = [
    'hash'    => wp_hash_password( $code ),
    'expires' => time() + (20 * MINUTE_IN_SECONDS),
    'attempts'=> 0,
  ];
  $key = sc_otp_key_for( $user->ID );
  set_transient( $key, $payload, 20 * MINUTE_IN_SECONDS );

  // === Email HTML bonito (verde corporativo) ===
  $subject = 'Tu código de acceso a Smartcards';
  $primary = '#01a350';
  $headers = [
    'Content-Type: text/html; charset=UTF-8',
    'From: Smart Cards <no-reply@smartcards.com.co>',
  ];
  $message = '
    <div style="font-family:Arial,sans-serif; line-height:1.45;">
      <h2 style="margin:0 0 12px 0;">Tu código de acceso a Smartcards</h2>
      <p>Hola '. esc_html($user->display_name) .',</p>
      <p>Usa este código para entrar (vence en <strong>20 minutos</strong>):</p>
      <div style="
        font-size:32px;
        letter-spacing:8px;
        font-weight:700;
        color:'. $primary .';
        padding:12px 16px;
        border:1px solid #e5e7eb;
        border-radius:12px;
        display:inline-block;">
        '. esc_html($code) .'
      </div>
      <p style="margin-top:16px;">Si no fuiste tú, ignora este correo.</p>
    </div>
  ';

  $sent = wp_mail( $email, $subject, $message, $headers );
  if ( ! $sent ) {
    return new WP_REST_Response( [ 'error' => 'mail_failed' ], 500 );
  }

  // ✅ SIEMPRE devolver respuesta JSON
  return new WP_REST_Response( [ 'ok' => true ], 200 );
}

/**
 * POST { email, code }
 */
function sc_otp_verify(WP_REST_Request $req) {
  $email = sanitize_email($req->get_param('email'));
  $code  = preg_replace('/\D/', '', $req->get_param('code') ?? '');
  if (!is_email($email) || strlen($code)!==6) return new WP_Error('bad_req','Datos inválidos',['status'=>400]);

  $user = sc_find_user_by_email($email);
  if (is_wp_error($user)) return $user;

  $key = sc_otp_key_for($user->ID);
  $data = get_transient($key);
  if (!$data) return new WP_Error('expired','Código expirado o no solicitado',['status'=>410]);

  if (time() > ($data['expires'] ?? 0)) {
    delete_transient($key);
    return new WP_Error('expired','Código expirado',['status'=>410]);
  }

  // anti-fuerza bruta simple
  if (($data['attempts'] ?? 0) >= 5) {
    delete_transient($key);
    return new WP_Error('locked','Demasiados intentos, solicita un nuevo código',['status'=>429]);
  }

  // validar
  if (!wp_check_password($code, $data['hash'])) {
    $data['attempts'] = ($data['attempts'] ?? 0) + 1;
    set_transient($key, $data, max(1, $data['expires'] - time()));
    return new WP_Error('invalid','Código incorrecto',['status'=>401]);
  }

  // ok: destruir OTP (consumido) y emitir token
  delete_transient( $key ); // o delete_transient( sc_otp_key_for($user->ID) );

  // ✅ emitir token SIN romper si no existe tu helper
  $token = '';
  if ( function_exists( 'sc_issue_jwt_for_user' ) ) {
    $token = (string) sc_issue_jwt_for_user( $user->ID );
  } elseif ( function_exists( 'sc_generate_jwt_for_user' ) ) {
    $token = (string) sc_generate_jwt_for_user( $user->ID );
  } else {
    // Parche temporal: no fatal si aún no tienes emisor de JWT
    $token = '';
  }

  // ✅ devolver SIEMPRE JSON 200
  return new WP_Rest_Response( [
    'ok'    => true,
    'token' => $token,
    'user'  => [
      'id'    => $user->ID,
      'email' => $user->user_email,
      'name'  => $user->display_name,
    ],
  ], 200 );
}