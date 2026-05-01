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

function sc_create_invited_user( $email ) {
  $username = sc_get_unique_invited_username( $email );
  $password = wp_generate_password( 24, true, true );
  $user_id  = wp_create_user( $username, $password, $email );

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

  update_user_meta( $user_id, 'smartcards_credits', 0 );
  update_user_meta( $user_id, 'smartcards_credits_updated', current_time( 'mysql' ) );

  return get_user_by( 'id', $user_id );
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

  return wp_mail( $email, $subject, $message, $headers );
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
  global $wpdb;

  sc_create_invites_table();

  $token = sanitize_text_field( $request->get_param( 'token' ) );

  if ( '' === $token ) {
    return new WP_Error( 'missing_token', 'Token requerido', [ 'status' => 400 ] );
  }

  $table  = sc_invites_table_name();
  $invite = $wpdb->get_row(
    $wpdb->prepare(
      "SELECT * FROM {$table} WHERE token = %s LIMIT 1",
      $token
    )
  );

  if ( ! $invite ) {
    return new WP_Error( 'invalid_invite', 'Invitación inválida', [ 'status' => 400 ] );
  }

  if ( 'pending' !== $invite->status ) {
    return new WP_Error( 'invite_used', 'Esta invitación ya fue usada', [ 'status' => 409 ] );
  }

  $email = sanitize_email( $invite->email );

  if ( ! is_email( $email ) ) {
    return new WP_Error( 'invalid_invite_email', 'Email inválido en la invitación', [ 'status' => 400 ] );
  }

  $user    = get_user_by( 'email', $email );
  $created = false;

  if ( ! $user ) {
    $user = sc_create_invited_user( $email );

    if ( is_wp_error( $user ) ) {
      return $user;
    }

    $created = true;
  }

  $updated = $wpdb->update(
    $table,
    [ 'status' => 'used' ],
    [
      'id'     => (int) $invite->id,
      'status' => 'pending',
    ],
    [ '%s' ],
    [ '%d', '%s' ]
  );

  if ( ! $updated ) {
    return new WP_Error( 'invite_used', 'Esta invitación ya fue usada', [ 'status' => 409 ] );
  }

  $credit      = max( 1, (int) $invite->credit );
  $new_balance = sc_add_credit_balance( $user->ID, $credit );
  $jwt         = sc_issue_jwt_for_invited_user( $user->ID );

  if ( is_wp_error( $jwt ) ) {
    return $jwt;
  }

  return [
    'success' => true,
    'message' => 'Invitación redimida correctamente',
    'credits' => (int) $new_balance,
    'created' => (bool) $created,
    'token'   => $jwt,
    'user'    => [
      'id'           => (int) $user->ID,
      'email'        => $user->user_email,
      'display_name' => $user->display_name,
      'name'         => $user->display_name,
    ],
  ];
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
