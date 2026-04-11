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
    $cards[] = [
      'id'         => (int) $post->ID,
      'title'      => (string) $post->post_title,
      'slug'       => (string) $post->post_name,
      'permalink'  => get_permalink($post->ID),
      'status'     => (string) $post->post_status,
      'created_at' => (string) $post->post_date,
    ];
  }

  return [
    'success' => true,
    'count'   => count($cards),
    'cards'   => $cards,
  ];
}
