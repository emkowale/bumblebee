<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function bumblebee_handle_create_product(){
  $req    = bumblebee_parse_create_request();
  $result = bumblebee_build_product($req);
  bumblebee_maybe_generate_ai_content($result['product_id'], $req['vendor_code'], $result['image_url']);
  $edit_url = get_edit_post_link($result['product_id'], 'raw');
  wp_send_json(['success'=>true,'edit_url'=>$edit_url]);
}
