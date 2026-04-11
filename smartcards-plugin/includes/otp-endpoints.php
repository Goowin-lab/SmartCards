<?php
if (!defined('ABSPATH')) exit;

class SC_OTP_Endpoints {

  public static function init() {
    add_action('rest_api_init', [__CLASS__, 'routes']);
  }

  public static function routes() {

    register_rest_route('smartcards/v1', '/otp/request', [
      'methods'  => 'POST',
      'callback' => [__CLASS__, 'request_otp'],
      'permission_callback' => '__return_true',
    ]);

    register_rest_route('smartcards/v1', '/otp/verify', [
      'methods'  => 'POST',
      'callback' => [__CLASS__, 'verify_otp'],
      'permission_callback' => '__return_true',
    ]);

  }

  // ---------------------------------------------
  // 1) Enviar código OTP al email
  // ---------------------------------------------
  public static function request_otp($req) {
    $email = sanitize_email($req['email']);
    if (!is_email($email)) {
      return new WP_REST_Response(['ok' => false, 'message' => 'Email inválido'], 400);
    }

    $code = rand(100000, 999999);
    set_transient("sc_otp_$email", $code, 20 * MINUTE_IN_SECONDS);

    add_filter('wp_mail_content_type', function() { return 'text/html'; });
    $subject = "Tu código de acceso a Smartcards";

$message = "
<div style='font-family: Arial, sans-serif; padding: 20px;'>
  <h2 style='color:#111;'>Tu código de acceso a <strong>Smartcards</strong></h2>
  
  <p>Hola <strong>{$user->display_name}</strong>,</p>
  <p>Usa este código para entrar (vence en <strong>20 minutos</strong>):</p>

  <div style='
    font-size: 40px;
    padding: 20px;
    color: #28a745;
    border: 2px solid #eee;
    border-radius: 12px;
    margin: 20px 0;
    text-align: center;
    font-weight: bold;
  '>
    {$code}
  </div>

  <p style='color:#555;'>Si no fuiste tú, ignora este correo.</p>
</div>
";

wp_mail($email, $subject, $message);
remove_filter('wp_mail_content_type', 'set_html_content_type');

    return ['ok' => true];
  }

  // ---------------------------------------------
  // 2) Validar OTP + devolver token JWT
  // ---------------------------------------------
  public static function verify_otp($req) {
    $email = sanitize_email($req['email']);
    $code  = sanitize_text_field($req['code']);

    $saved = get_transient("sc_otp_$email");
    if (!$saved || $saved != $code) {
      return new WP_REST_Response(['ok' => false, 'message' => 'Código incorrecto'], 400);
    }

    $user = get_user_by('email', $email);
    if (!$user) {
      return new WP_REST_Response(['ok' => false, 'message' => 'Usuario no existe'], 404);
    }

    delete_transient("sc_otp_$email");

    // JWT
    $secret = defined('JWT_AUTH_SECRET_KEY') ? JWT_AUTH_SECRET_KEY : false;

    $payload = [
      'iss'     => get_bloginfo('url'),
      'iat'     => time(),
      'exp'     => time() + DAY_IN_SECONDS,
      'data' => [
  'user' => [
    'id' => $user->ID
  ]
],
    ];

    $jwt = Firebase\JWT\JWT::encode($payload, $secret, 'HS256');

   $credits = (int) get_user_meta($user->ID, 'smartcards_credits', true);

return [
  'ok' => true,
  'token' => $jwt,
  'user' => [
    'id' => $user->ID,
    'email' => $user->user_email,
    'name' => $user->display_name,
    'credits' => $credits,
  ]
];
  }
}

SC_OTP_Endpoints::init();
