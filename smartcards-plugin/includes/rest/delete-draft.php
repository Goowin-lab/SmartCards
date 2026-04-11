<?php
if (!defined('ABSPATH')) exit;

/**
 * POST /wp-json/smartcards/v1/delete-draft
 * body JSON: { smartcard_id }
 */
add_action('rest_api_init', function () {
  register_rest_route('smartcards/v1', '/delete-draft', [
    'methods'  => 'POST',
    'callback' => 'sc_delete_draft_rest',
    'permission_callback' => function () {
      return is_user_logged_in();
    },
  ]);
});

function sc_delete_draft_rest(WP_REST_Request $request) {
  $user_id     = get_current_user_id();
  $params      = $request->get_json_params();
  $smartcard_id = (int) ($params['smartcard_id'] ?? 0);

  if (!$smartcard_id) {
    return new WP_Error('invalid', 'smartcard_id inválido', ['status' => 400]);
  }

  $post = get_post($smartcard_id);
  if (!$post || $post->post_type !== 'smartcards') {
    return new WP_Error('not_found', 'Smart Card no encontrada', ['status' => 404]);
  }

  if ((int) $post->post_author !== (int) $user_id) {
    return new WP_Error('forbidden', 'No tienes acceso a esta Smart Card', ['status' => 403]);
  }

  if ($post->post_status !== 'draft') {
    return new WP_Error('invalid_state', 'Solo se puede cancelar un borrador', ['status' => 400]);
  }

  // Borra adjuntos relacionados (post_parent = smartcard_id)
  $attachments = get_children([
    'post_parent' => $smartcard_id,
    'post_type'   => 'attachment',
    'numberposts' => -1,
  ]);

  if ($attachments) {
    foreach ($attachments as $att) {
      wp_delete_attachment($att->ID, true);
    }
  }

  // Por si guardaste VCF como attachment en meta
  $vcf_att = (int) get_post_meta($smartcard_id, 'sc_vcf_attachment_id', true);
  if ($vcf_att) wp_delete_attachment($vcf_att, true);

  wp_delete_post($smartcard_id, true);

  return ['success' => true];
}
