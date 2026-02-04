<?php
if ( ! defined( 'ABSPATH' ) ) exit;

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
