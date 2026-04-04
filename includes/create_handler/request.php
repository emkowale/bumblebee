<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function bumblebee_handle_create_product(){
  $req    = bumblebee_parse_create_request();
  $result = bumblebee_build_product($req);
  $ai = bumblebee_maybe_generate_ai_content($result['product_id'], $req['vendor_code'], $result['image_url']);
  $edit_url = get_edit_post_link($result['product_id'], 'raw');
  if (empty($ai['ok'])) {
    $reason = isset($ai['error']) ? wp_strip_all_tags((string)$ai['error']) : 'Unknown error.';
    wp_send_json([
      'success'   => true,
      'edit_url'  => $edit_url,
      'product_id'=> (int) $result['product_id'],
      'ai_failed' => true,
      'ai_error'  => $reason !== '' ? $reason : 'Unknown error.',
    ]);
  }
  wp_send_json(['success'=>true,'edit_url'=>$edit_url]);
}

add_action('wp_ajax_bumblebee_delete_product', function(){
  if (!current_user_can('manage_woocommerce')) wp_send_json_error(['message'=>'Unauthorized'], 403);
  if ( ! isset($_POST['nonce']) || ! wp_verify_nonce($_POST['nonce'], 'bb_delete_product') ) {
    wp_send_json_error(['message'=>'Bad nonce'], 400);
  }
  $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
  if ($product_id <= 0) wp_send_json_error(['message'=>'Missing product ID.'], 400);
  if (function_exists('wc_delete_product')) {
    wc_delete_product($product_id, true);
  } else {
    wp_delete_post($product_id, true);
  }
  wp_send_json_success(['deleted'=>true]);
});
