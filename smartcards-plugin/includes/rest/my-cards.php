<?php
/**
 * Endpoint REST: Mis Smart Cards
 */

add_action('rest_api_init', function () {
  register_rest_route('smartcards/v1', '/my-cards', [
    'methods'             => 'GET',
    'callback'            => 'sc_get_my_smartcards',
    'permission_callback' => function () {
      return is_user_logged_in();
    },
  ]);
});

function sc_extract_profile_image_from_html($html) {
  $html = (string) $html;

  if (!$html) {
    return '';
  }

  if (preg_match('/<img[^>]+class=["\'][^"\']*\bprofile-image\b[^"\']*["\'][^>]+src=["\']([^"\']+)["\']/i', $html, $matches)) {
    return html_entity_decode((string) $matches[1], ENT_QUOTES, 'UTF-8');
  }

  if (preg_match('/<img[^>]+src=["\']([^"\']+)["\'][^>]+class=["\'][^"\']*\bprofile-image\b[^"\']*["\']/i', $html, $matches)) {
    return html_entity_decode((string) $matches[1], ENT_QUOTES, 'UTF-8');
  }

  if (preg_match('/<img[^>]+src=["\']([^"\']*uploads[^"\']+)["\']/i', $html, $matches)) {
    return html_entity_decode((string) $matches[1], ENT_QUOTES, 'UTF-8');
  }

  return '';
}

function sc_get_my_smartcards(WP_REST_Request $request) {
  $user_id = get_current_user_id();

  if (!$user_id) {
    return new WP_Error('unauthorized', 'No autorizado', ['status' => 401]);
  }

  $query = new WP_Query([
    'post_type'      => 'smartcards', // plural exacto
    'post_status'    => ['draft', 'publish'],
    'author'         => (int) $user_id, // filtra por post_author
    'posts_per_page' => -1,
    'orderby'        => 'date',
    'order'          => 'DESC',
    'no_found_rows'  => true,
  ]);

  $cards = [];

  foreach ($query->posts as $post) {
    $status = $post->post_status === 'publish' ? 'published' : 'draft';
    $name = trim((string) get_post_meta($post->ID, 'firstName', true) . ' ' . (string) get_post_meta($post->ID, 'lastName', true));
    if (!$name) {
      $name = (string) $post->post_title;
    }

    $avatar_url = '';
    $profile_url = get_permalink($post->ID);

    if ($profile_url) {
      $response = wp_remote_get($profile_url, [
        'timeout'     => 8,
        'redirection' => 3,
      ]);

      if (!is_wp_error($response)) {
        $html = wp_remote_retrieve_body($response);
        $avatar_url = sc_extract_profile_image_from_html($html);
      }
    }

    if ($avatar_url && strpos($avatar_url, 'logo') !== false) {
      $avatar_url = '';
    }

    if ($avatar_url && strpos($avatar_url, 'http') !== 0) {
      $avatar_url = (string) site_url($avatar_url);
    }

    if (!$avatar_url) {
      $avatar_url = '';
    }

    $cards[] = [
      'id'         => (int) $post->ID,
      'name'       => $name,
      'title'      => (string) $post->post_title,
      'slug'       => (string) $post->post_name,
      'permalink'  => $profile_url,
      'status'     => $status,
      'avatar'     => $avatar_url,
      'created_at' => (string) $post->post_date,
    ];
  }

  return [
    'success' => true,
    'count'   => count($cards),
    'cards'   => $cards,
  ];
}
