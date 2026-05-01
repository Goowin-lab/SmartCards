<?php
if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

function sc_invites_table_name() {
  global $wpdb;
  return $wpdb->prefix . 'smartcards_invites';
}

function sc_create_invites_table() {
  global $wpdb;

  $table_name      = sc_invites_table_name();
  $charset_collate = $wpdb->get_charset_collate();

  require_once ABSPATH . 'wp-admin/includes/upgrade.php';

  $sql = "CREATE TABLE {$table_name} (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    sender_user_id BIGINT UNSIGNED NOT NULL,
    email VARCHAR(190) NOT NULL,
    credit INT NOT NULL DEFAULT 1,
    token VARCHAR(191) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    created_at DATETIME NOT NULL,
    PRIMARY KEY  (id),
    UNIQUE KEY token (token),
    KEY sender_user_id (sender_user_id),
    KEY email (email),
    KEY status (status)
  ) {$charset_collate};";

  dbDelta( $sql );
}

function sc_get_credit_balance( $user_id ) {
  return (int) get_user_meta( (int) $user_id, 'smartcards_credits', true );
}

function sc_add_credit_balance( $user_id, $credit = 1 ) {
  $user_id = (int) $user_id;
  $credit  = max( 1, (int) $credit );
  $current = sc_get_credit_balance( $user_id );
  $next    = $current + $credit;

  update_user_meta( $user_id, 'smartcards_credits', $next );
  update_user_meta( $user_id, 'smartcards_credits_updated', current_time( 'mysql' ) );
  update_user_meta( $user_id, 'smartcards_credits_updated_at', time() );

  return $next;
}

function sc_subtract_credit_balance( $user_id, $credit = 1 ) {
  global $wpdb;

  $user_id = (int) $user_id;
  $credit  = max( 1, (int) $credit );

  if ( '' === get_user_meta( $user_id, 'smartcards_credits', true ) ) {
    add_user_meta( $user_id, 'smartcards_credits', 0, true );
  }

  $updated = $wpdb->query(
    $wpdb->prepare(
      "UPDATE {$wpdb->usermeta}
       SET meta_value = CAST(meta_value AS SIGNED) - %d
       WHERE user_id = %d
       AND meta_key = %s
       AND CAST(meta_value AS SIGNED) >= %d",
      $credit,
      $user_id,
      'smartcards_credits',
      $credit
    )
  );

  if ( ! $updated ) {
    return false;
  }

  wp_cache_delete( $user_id, 'user_meta' );
  update_user_meta( $user_id, 'smartcards_credits_updated', current_time( 'mysql' ) );

  return sc_get_credit_balance( $user_id );
}

function sc_generate_invite_token() {
  global $wpdb;

  $table_name = sc_invites_table_name();

  do {
    $token = wp_generate_password( 48, false, false );
    $found = $wpdb->get_var(
      $wpdb->prepare(
        "SELECT id FROM {$table_name} WHERE token = %s LIMIT 1",
        $token
      )
    );
  } while ( $found );

  return $token;
}

function sc_get_invite_by_token( $token ) {
  global $wpdb;

  $table = sc_invites_table_name();

  return $wpdb->get_row(
    $wpdb->prepare(
      "SELECT * FROM {$table} WHERE token = %s LIMIT 1",
      $token
    )
  );
}

function sc_get_unique_invited_username( $email ) {
  $local = sanitize_user( current( explode( '@', $email ) ), true );

  if ( '' === $local ) {
    $local = 'smartcards';
  }

  $base     = $local;
  $username = $base;
  $suffix   = 1;

  while ( username_exists( $username ) ) {
    $username = $base . $suffix;
    $suffix++;
  }

  return $username;
}

function sc_grant_welcome_bonus_once( $user_id ) {
  $user_id = (int) $user_id;
  error_log( "SC DEBUG BEFORE: user {$user_id} credits = " . get_user_meta( $user_id, 'smartcards_credits', true ) );

  $already_bonus = get_user_meta( $user_id, 'welcome_bonus_given', true );
  error_log( 'SC DEBUG WELCOME FLAG: ' . $already_bonus );

  if ( $already_bonus ) {
    error_log( "SC BONUS SKIPPED user {$user_id}" );
    error_log( "SC DEBUG AFTER BONUS: user {$user_id} credits = " . get_user_meta( $user_id, 'smartcards_credits', true ) );
    return sc_get_credit_balance( $user_id );
  }

  $credits = (int) get_user_meta( $user_id, 'smartcards_credits', true );
  $next    = $credits + 1;

  update_user_meta( $user_id, 'smartcards_credits', $next );
  update_user_meta( $user_id, 'welcome_bonus_given', 1 );
  update_user_meta( $user_id, 'smartcards_credits_updated', current_time( 'mysql' ) );
  update_user_meta( $user_id, 'smartcards_credits_updated_at', time() );

  error_log( "SC BONUS APPLIED user {$user_id}" );
  error_log( "SC DEBUG AFTER BONUS: user {$user_id} credits = " . get_user_meta( $user_id, 'smartcards_credits', true ) );

  return $next;
}

function sc_create_invited_user( $email ) {
  $username = $email;
  $password = wp_generate_password( 20, true, true );

  $initial_credit_priority = has_action( 'user_register', 'sc_regalo_credito_inicial' );

  if ( false !== $initial_credit_priority ) {
    remove_action( 'user_register', 'sc_regalo_credito_inicial', $initial_credit_priority );
  }

  $user_id  = wp_create_user( $username, $password, $email );

  if ( is_wp_error( $user_id ) && in_array( $user_id->get_error_code(), [ 'existing_user_login', 'invalid_username' ], true ) ) {
    $username = sc_get_unique_invited_username( $email );
    $user_id  = wp_create_user( $username, $password, $email );
  }

  if ( false !== $initial_credit_priority ) {
    add_action( 'user_register', 'sc_regalo_credito_inicial', $initial_credit_priority );
  }

  if ( is_wp_error( $user_id ) ) {
    return $user_id;
  }

  $display_name = current( explode( '@', $email ) );
  wp_update_user(
    [
      'ID'           => $user_id,
      'display_name' => $display_name,
      'nickname'     => $display_name,
    ]
  );

  error_log( "SmartCards: usuario creado automático {$email}" );

  return get_user_by( 'id', $user_id );
}

function sc_update_invite_status( $invite_id, $status, $current_status = null ) {
  global $wpdb;

  $table = sc_invites_table_name();
  $where = [ 'id' => (int) $invite_id ];
  $where_format = [ '%d' ];

  if ( null !== $current_status ) {
    $where['status'] = (string) $current_status;
    $where_format[]  = '%s';
  }

  return $wpdb->update(
    $table,
    [ 'status' => (string) $status ],
    $where,
    [ '%s' ],
    $where_format
  );
}

function sc_apply_invite_credit_once( $user_id, $invite ) {
  $user_id = (int) $user_id;

  if ( ! $invite || 'processing' !== $invite->status ) {
    return new WP_Error( 'invalid_invite_credit', 'Invite no válido para crédito', [ 'status' => 409 ] );
  }

  error_log( "SC DEBUG BEFORE: user {$user_id} credits = " . get_user_meta( $user_id, 'smartcards_credits', true ) );

  $credits = (int) get_user_meta( $user_id, 'smartcards_credits', true );
  $next    = $credits + 1;

  update_user_meta( $user_id, 'smartcards_credits', $next );
  update_user_meta( $user_id, 'smartcards_credits_updated', current_time( 'mysql' ) );
  update_user_meta( $user_id, 'smartcards_credits_updated_at', time() );

  error_log( "SC INVITE CREDIT APPLIED user {$user_id}" );
  error_log( "SC DEBUG AFTER INVITE: user {$user_id} credits = " . get_user_meta( $user_id, 'smartcards_credits', true ) );

  return $next;
}

function sc_send_logged_mail( $email, $subject, $message, $headers = '', $attachments = [] ) {
  error_log( "SmartCards EMAIL START: {$email}" );

  $start = microtime( true );
  $sent  = wp_mail( $email, $subject, $message, $headers, $attachments );
  $time  = microtime( true ) - $start;

  error_log( "SmartCards EMAIL END: {$email}" );
  error_log( 'SmartCards EMAIL TIME: ' . sprintf( '%.4f', $time ) );

  if ( $time > 1 ) {
    error_log( "SmartCards EMAIL SMTP WARNING: wp_mail tardó " . sprintf( '%.4f', $time ) . "s para {$email}" );
  }

  return $sent;
}

function sc_issue_jwt_for_invited_user( $user_id ) {
  $user_id = (int) $user_id;

  if ( function_exists( 'jwt_auth_generate_token' ) ) {
    $jwt = jwt_auth_generate_token( $user_id );

    if ( is_wp_error( $jwt ) ) {
      return $jwt;
    }

    if ( is_array( $jwt ) && ! empty( $jwt['token'] ) ) {
      return (string) $jwt['token'];
    }

    if ( is_string( $jwt ) ) {
      return $jwt;
    }
  }

  if ( function_exists( 'sc_issue_jwt_for_user' ) ) {
    $jwt = sc_issue_jwt_for_user( $user_id );
    return is_wp_error( $jwt ) ? $jwt : (string) $jwt;
  }

  if ( function_exists( 'sc_generate_jwt_for_user' ) ) {
    $jwt = sc_generate_jwt_for_user( $user_id );
    return is_wp_error( $jwt ) ? $jwt : (string) $jwt;
  }

  if ( defined( 'JWT_AUTH_SECRET_KEY' ) && class_exists( '\Firebase\JWT\JWT' ) ) {
    $payload = [
      'iss'  => get_bloginfo( 'url' ),
      'iat'  => time(),
      'exp'  => time() + DAY_IN_SECONDS,
      'data' => [
        'user' => [
          'id' => $user_id,
        ],
      ],
    ];

    return \Firebase\JWT\JWT::encode( $payload, JWT_AUTH_SECRET_KEY, 'HS256' );
  }

  return new WP_Error( 'jwt_unavailable', 'JWT no disponible', [ 'status' => 500 ] );
}

function sc_send_credit_invite_email( $email, WP_User $sender, $token ) {
  $link = 'https://app.smartcards.com.co/magic?token=' . rawurlencode( $token );

  $subject = 'Te enviaron un crédito en SmartCards';
  $headers = [
    'Content-Type: text/html; charset=UTF-8',
    'From: Smart Cards <no-reply@smartcards.com.co>',
  ];
  $message = '
    <div style="font-family:Arial,sans-serif; line-height:1.5; color:#111827;">
      <h2 style="margin:0 0 12px;">Tienes un crédito disponible</h2>
      <p>' . esc_html( $sender->display_name ?: $sender->user_email ) . ' te envió un crédito para crear tu Smart Card.</p>
      <p>Usa este enlace para activar tu cuenta y recibir el crédito:</p>
      <p>
        <a href="' . esc_url( $link ) . '" style="background:#01A350;color:#fff;padding:12px 18px;border-radius:10px;text-decoration:none;display:inline-block;font-weight:700;">
          Activar crédito
        </a>
      </p>
      <p style="color:#6b7280;">Si el botón no abre, copia este enlace:<br>' . esc_html( $link ) . '</p>
    </div>
  ';

  return sc_send_logged_mail( $email, $subject, $message, $headers );
}

function sc_assign_credit_rest( WP_REST_Request $request ) {
  global $wpdb;

  sc_create_invites_table();

  $sender = wp_get_current_user();

  if ( ! $sender || 0 === (int) $sender->ID ) {
    return new WP_Error( 'unauthorized', 'No autorizado', [ 'status' => 401 ] );
  }

  $email = sanitize_email( $request->get_param( 'email' ) );

  if ( ! is_email( $email ) ) {
    return new WP_Error( 'invalid_email', 'Email inválido', [ 'status' => 400 ] );
  }

  $credit = 1;
  $token  = sc_generate_invite_token();
  $table  = sc_invites_table_name();
  $target = get_user_by( 'email', $email );

  $new_sender_balance = sc_subtract_credit_balance( $sender->ID, $credit );

  if ( false === $new_sender_balance ) {
    return new WP_Error( 'no_credits', 'No tienes créditos disponibles', [ 'status' => 400 ] );
  }

  if ( $target ) {
    $recipient_balance = sc_add_credit_balance( $target->ID, $credit );
    $email             = $target->user_email;
    $name              = $target->display_name;

    $subject = '🎉 Has recibido un crédito en SmartCards';
    $message = "
Hola {$name},

Te han asignado un crédito en SmartCards 🚀

Ya puedes crear una nueva Smart Card desde tu cuenta.

👉 Ingresa aquí:
https://app.smartcards.com.co

¡Aprovecha tu crédito ahora!

Equipo SmartCards
";
    $headers = [ 'Content-Type: text/html; charset=UTF-8' ];

    sc_send_logged_mail( $email, $subject, nl2br( $message ), $headers );
    error_log( "SmartCards: crédito enviado por email a {$email}" );

    $inserted = $wpdb->insert(
      $table,
      [
        'sender_user_id' => (int) $sender->ID,
        'email'          => $email,
        'credit'         => $credit,
        'token'          => $token,
        'status'         => 'used',
        'created_at'     => current_time( 'mysql' ),
      ],
      [ '%d', '%s', '%d', '%s', '%s', '%s' ]
    );

    if ( false === $inserted ) {
      sc_add_credit_balance( $sender->ID, $credit );
      sc_subtract_credit_balance( $target->ID, $credit );

      return new WP_Error( 'assign_failed', 'No se pudo registrar la asignación', [ 'status' => 500 ] );
    }

    return [
      'success'             => true,
      'status'              => 'used',
      'recipient_exists'    => true,
      'message'             => 'Crédito enviado correctamente',
      'sender_credits'      => (int) $new_sender_balance,
      'recipient_user_id'   => (int) $target->ID,
      'recipient_credits'   => (int) $recipient_balance,
      'invite_id'           => (int) $wpdb->insert_id,
    ];
  }

  $inserted = $wpdb->insert(
    $table,
    [
      'sender_user_id' => (int) $sender->ID,
      'email'          => $email,
      'credit'         => $credit,
      'token'          => $token,
      'status'         => 'pending',
      'created_at'     => current_time( 'mysql' ),
    ],
    [ '%d', '%s', '%d', '%s', '%s', '%s' ]
  );

  if ( false === $inserted ) {
    sc_add_credit_balance( $sender->ID, $credit );

    return new WP_Error( 'invite_failed', 'No se pudo crear la invitación', [ 'status' => 500 ] );
  }

  $sent = sc_send_credit_invite_email( $email, $sender, $token );

  if ( ! $sent ) {
    $wpdb->delete( $table, [ 'id' => (int) $wpdb->insert_id ], [ '%d' ] );
    sc_add_credit_balance( $sender->ID, $credit );

    return new WP_Error( 'mail_failed', 'No se pudo enviar el correo de invitación', [ 'status' => 500 ] );
  }

  return [
    'success'          => true,
    'status'           => 'pending',
    'recipient_exists' => false,
    'message'          => 'Crédito enviado correctamente',
    'sender_credits'   => (int) $new_sender_balance,
    'invite_id'        => (int) $wpdb->insert_id,
  ];
}

function sc_redeem_invite_rest( WP_REST_Request $request ) {
  sc_create_invites_table();

  $token = sanitize_text_field( $request->get_param( 'token' ) );

  if ( '' === $token ) {
    return new WP_Error( 'missing_token', 'Token requerido', [ 'status' => 400 ] );
  }

  $invite = sc_get_invite_by_token( $token );

  if ( ! $invite ) {
    return new WP_Error( 'invalid_invite', 'Invitación inválida', [ 'status' => 400 ] );
  }

  if ( 'used' === $invite->status ) {
    return new WP_Error( 'invite_used', 'Esta invitación ya fue usada', [ 'status' => 409 ] );
  }

  if ( 'processing' === $invite->status ) {
    return new WP_Error( 'invite_processing', 'Este enlace ya fue usado o está en proceso', [ 'status' => 409 ] );
  }

  if ( 'pending' !== $invite->status ) {
    return new WP_Error( 'invalid_invite_status', 'Invitación inválida', [ 'status' => 400 ] );
  }

  $locked = sc_update_invite_status( $invite->id, 'processing', 'pending' );

  if ( ! $locked ) {
    return new WP_Error( 'invite_processing', 'Este enlace ya fue usado o está en proceso', [ 'status' => 409 ] );
  }

  $invite->status = 'processing';
  $credit_assigned = false;
  try {
    $email = sanitize_email( $invite->email );

    if ( ! is_email( $email ) ) {
      sc_update_invite_status( $invite->id, 'pending', 'processing' );
      return new WP_Error( 'invalid_invite_email', 'Email inválido en la invitación', [ 'status' => 400 ] );
    }

    $user    = get_user_by( 'email', $email );
    $created = false;

    if ( ! $user ) {
      $user = sc_create_invited_user( $email );

      if ( is_wp_error( $user ) ) {
        sc_update_invite_status( $invite->id, 'pending', 'processing' );
        return $user;
      }

      $created = true;
    }

    $user_id = (int) $user->ID;

    if ( $created ) {
      sc_grant_welcome_bonus_once( $user_id );
    } else {
      error_log( 'SC DEBUG WELCOME FLAG: ' . get_user_meta( $user_id, 'welcome_bonus_given', true ) );
      error_log( "SC BONUS SKIPPED user {$user_id}" );
    }

    $jwt = sc_issue_jwt_for_invited_user( $user->ID );

    if ( is_wp_error( $jwt ) ) {
      sc_update_invite_status( $invite->id, 'pending', 'processing' );
      return $jwt;
    }

    $new_balance = sc_apply_invite_credit_once( $user_id, $invite );

    if ( is_wp_error( $new_balance ) ) {
      sc_update_invite_status( $invite->id, 'pending', 'processing' );
      return $new_balance;
    }

    $credit_assigned = true;
    error_log( "SmartCards: crédito asignado {$user_id}" );

    $updated = sc_update_invite_status( $invite->id, 'used', 'processing' );

    if ( ! $updated ) {
      sc_update_invite_status( $invite->id, 'used' );
      error_log( "SmartCards: no se pudo finalizar invite {$invite->id} tras asignar crédito" );

      return new WP_Error( 'invite_finalize_failed', 'No se pudo finalizar la invitación', [ 'status' => 500 ] );
    }

    error_log( "SC FINAL CREDITS user {$user_id} = " . get_user_meta( $user_id, 'smartcards_credits', true ) );

    return [
      'success' => true,
      'message' => 'Invitación redimida correctamente',
      'credits' => (int) $new_balance,
      'created' => (bool) $created,
      'token'   => $jwt,
      'user_id' => (int) $user->ID,
      'user'    => [
        'id'           => (int) $user->ID,
        'email'        => $user->user_email,
        'display_name' => $user->display_name,
        'name'         => $user->display_name,
      ],
    ];
  } catch ( Throwable $e ) {
    if ( ! $credit_assigned ) {
      sc_update_invite_status( $invite->id, 'pending', 'processing' );
    }

    error_log( 'SmartCards redeem-invite error: ' . $e->getMessage() );

    return new WP_Error( 'redeem_failed', 'No se pudo activar el crédito', [ 'status' => 500 ] );
  }
}

add_action(
  'rest_api_init',
  function () {
    register_rest_route(
      'smartcards/v1',
      '/assign-credit',
      [
        'methods'             => 'POST',
        'callback'            => 'sc_assign_credit_rest',
        'permission_callback' => function () {
          return get_current_user_id() > 0;
        },
      ]
    );

    register_rest_route(
      'smartcards/v1',
      '/redeem-invite',
      [
        'methods'             => 'POST',
        'callback'            => 'sc_redeem_invite_rest',
        'permission_callback' => '__return_true',
      ]
    );
  }
);
