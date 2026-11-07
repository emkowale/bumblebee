<?php
if ( ! defined( 'ABSPATH' ) ) exit;

require_once __DIR__.'/create/scrubs.php';
require_once __DIR__.'/create_handler/validate.php';
require_once __DIR__.'/create_handler/art_meta.php';
require_once __DIR__.'/create_handler/product.php';
require_once __DIR__.'/create_handler/ai_content.php';
require_once __DIR__.'/create_handler/request.php';

add_action('wp_ajax_bumblebee_create_product', 'bumblebee_handle_create_product');

add_action('wp_ajax_bb_hub_vendors', function(){
  if (!current_user_can('manage_woocommerce')) wp_send_json_error(['message'=>'Unauthorized'], 403);
  check_ajax_referer('bb_hub_vendors','nonce');
  $vendors = bumblebee_hub_get_vendors();
  if (is_wp_error($vendors)) {
    wp_send_json_error(['message'=>$vendors->get_error_message()], 400);
  }
  wp_send_json_success(['vendors'=>$vendors]);
});

add_action('wp_ajax_bb_original_attachment_url', function(){
  if (!current_user_can('manage_woocommerce')) wp_send_json_error(['message'=>'Unauthorized'], 403);
  check_ajax_referer('bb_original_attachment_url', 'nonce');

  $attachment_id = isset($_POST['attachment_id']) ? absint($_POST['attachment_id']) : 0;
  if ($attachment_id <= 0) wp_send_json_error(['message'=>'Missing attachment.'], 400);

  $url = function_exists('bumblebee_original_attachment_url') ? bumblebee_original_attachment_url($attachment_id) : '';
  if ($url === '') wp_send_json_error(['message'=>'Original attachment URL could not be resolved.'], 404);

  wp_send_json_success(['url' => esc_url_raw($url)]);
});

add_action('wp_ajax_bb_prepare_mockup', function(){
  if (!current_user_can('manage_woocommerce')) wp_send_json_error(['message'=>'Unauthorized'], 403);
  check_ajax_referer('bb_prepare_mockup','nonce');

  $attachment_id = isset($_POST['attachment_id']) ? absint($_POST['attachment_id']) : 0;
  if ($attachment_id <= 0) wp_send_json_error(['message'=>'Missing mockup image.'], 400);

  // Validate only at picker-time. Actual conversion happens at final submit,
  // where we can safely preserve any attachments selected as Original Art.
  $check = bumblebee_validate_mockup_attachment($attachment_id);
  if (is_wp_error($check)) {
    wp_send_json_error(['message'=>$check->get_error_message()], 400);
  }

  $prepared_id = $attachment_id;
  if ($prepared_id <= 0) {
    wp_send_json_error(['message'=>'Mockup image could not be prepared.'], 400);
  }

  $url = wp_get_attachment_url($prepared_id);
  if (!$url) {
    wp_send_json_error(['message'=>'Mockup image URL could not be resolved.'], 400);
  }

  wp_send_json_success([
    'image_id'  => $prepared_id,
    'url'       => esc_url_raw($url),
    'converted' => false,
  ]);
});

add_action('wp_ajax_bb_reject_mockup', function(){
  if (!current_user_can('manage_woocommerce')) wp_send_json_error(['message'=>'Unauthorized'], 403);
  check_ajax_referer('bb_reject_mockup', 'nonce');

  $attachment_id = isset($_POST['attachment_id']) ? absint($_POST['attachment_id']) : 0;
  if ($attachment_id <= 0) wp_send_json_error(['message'=>'Missing mockup image.'], 400);

  $ext = function_exists('bumblebee_attachment_extension_from_id')
    ? bumblebee_attachment_extension_from_id($attachment_id)
    : '';
  if ($ext !== 'png' && $ext !== 'webp' && $ext !== 'jpg' && $ext !== 'jpeg') {
    wp_send_json_error(['message'=>'Mockup Image must be a .webp, .png, or .jpg file.'], 400);
  }

  // Legacy endpoint retained for cached clients. Mockup size is no longer restricted,
  // so this remains a no-op and should not delete uploads.
  wp_send_json_success(['deleted' => false]);
});
