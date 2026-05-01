<?php
if (!defined('ABSPATH')) exit;

/**
 * DELETE /wp-json/smartcards/v1/delete-card/{id}
 * Elimina una Smart Card del usuario autenticado y sus recursos asociados.
 */
add_action('rest_api_init', function () {
  register_rest_route('smartcards/v1', '/delete-card/(?P<id>\d+)', [
    'methods'  => 'DELETE',
    'callback' => 'sc_delete_card_rest',
    'permission_callback' => function () {
      return is_user_logged_in();
    },
    'args' => [
      'id' => [
        'required' => true,
        'validate_callback' => function ($value) {
          return absint($value) > 0;
        },
      ],
    ],
  ]);
});

function sc_delete_card_delete_attachment($attachment_id) {
  $attachment_id = (int) $attachment_id;
  if ($attachment_id > 0 && get_post($attachment_id)) {
    wp_delete_attachment($attachment_id, true);
  }
}

function sc_delete_card_unlink_upload_file($path) {
  $path = is_string($path) ? $path : '';
  if (!$path) {
    return;
  }

  $uploads = wp_get_upload_dir();
  $basedir = isset($uploads['basedir']) ? wp_normalize_path($uploads['basedir']) : '';
  $path = wp_normalize_path($path);

  if ($basedir && strpos($path, trailingslashit($basedir)) !== 0) {
    return;
  }

  if (file_exists($path) && is_file($path)) {
    unlink($path);
  }
}

function sc_delete_card_remove_from_user_list($user_id, $post_id) {
  $permalink = sc_get_profile_permalink($post_id);
  $perfiles = get_user_meta($user_id, 'smartcards_perfiles_urls', true);

  if (!is_array($perfiles)) {
    return;
  }

  $updated = [];
  foreach ($perfiles as $item) {
    $candidate_url = '';
    if (is_array($item) && !empty($item['url'])) {
      $candidate_url = function_exists('sc_get_live_profile_url')
        ? sc_get_live_profile_url($item['url'])
        : (string) $item['url'];
    } elseif (is_string($item)) {
      $candidate_url = function_exists('sc_get_live_profile_url')
        ? sc_get_live_profile_url($item)
        : $item;
    }

    $matches = false;
    if ($candidate_url && $permalink) {
      $matches = function_exists('sc_url_matches')
        ? sc_url_matches($candidate_url, $permalink)
        : trailingslashit($candidate_url) === trailingslashit($permalink);
    }

    if (!$matches) {
      $updated[] = $item;
    }
  }

  update_user_meta($user_id, 'smartcards_perfiles_urls', $updated);
}

function sc_delete_card_rest(WP_REST_Request $request) {
  $user_id = get_current_user_id();
  $post_id = (int) $request->get_param('id');

  if (!$user_id) {
    return new WP_Error('unauthorized', 'No autorizado', ['status' => 401]);
  }

  if (!$post_id) {
    return new WP_Error('invalid_id', 'ID inválido', ['status' => 400]);
  }

  $post = get_post($post_id);
  if (!$post || $post->post_type !== 'smartcards') {
    return new WP_Error('not_found', 'Smart Card no encontrada', ['status' => 404]);
  }

  if ((int) $post->post_author !== (int) $user_id) {
    return new WP_Error('forbidden', 'No tienes permisos para eliminar esta Smart Card', ['status' => 403]);
  }

  $attachment_ids = array_filter(array_unique([
    (int) get_post_meta($post_id, 'avatar_id', true),
    (int) get_post_meta($post_id, 'cover_id', true),
    (int) get_post_meta($post_id, 'sc_avatar_id', true),
    (int) get_post_meta($post_id, 'sc_cover_id', true),
    (int) get_post_meta($post_id, 'sc_vcf_photo_id', true),
    (int) get_post_meta($post_id, 'sc_vcf_attachment_id', true),
    (int) get_post_thumbnail_id($post_id),
  ]));

  $child_attachments = get_children([
    'post_parent' => $post_id,
    'post_type'   => 'attachment',
    'numberposts' => -1,
  ]);

  if ($child_attachments) {
    foreach ($child_attachments as $attachment) {
      $attachment_ids[] = (int) $attachment->ID;
    }
  }

  $attachment_ids = array_unique(array_filter($attachment_ids));
  foreach ($attachment_ids as $attachment_id) {
    sc_delete_card_delete_attachment($attachment_id);
  }

  $vcf_path = (string) get_post_meta($post_id, 'vcf_path', true);
  sc_delete_card_unlink_upload_file($vcf_path);

  $sc_vcf_path = (string) get_post_meta($post_id, 'sc_vcf_path', true);
  sc_delete_card_unlink_upload_file($sc_vcf_path);

  sc_delete_card_remove_from_user_list($user_id, $post_id);

  $deleted = wp_delete_post($post_id, true);
  if (!$deleted) {
    return new WP_Error('delete_failed', 'No se pudo eliminar la Smart Card', ['status' => 500]);
  }

  return [
    'success' => true,
    'message' => 'Smart Card eliminada correctamente',
  ];
}
