<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * AJAX: Test OpenAI key WITHOUT consuming tokens.
 * Uses GET /v1/models/gpt-4o-mini (auth validation only).
 */
add_action('wp_ajax_bb_test_openai_key', function(){
  if ( ! current_user_can('manage_woocommerce') ) wp_send_json_error(['message'=>'Unauthorized'], 403);
  if ( ! isset($_POST['nonce']) || ! wp_verify_nonce($_POST['nonce'], 'bb_test_openai_key') ) wp_send_json_error(['message'=>'Bad nonce'], 400);

  $which = isset($_POST['which']) ? sanitize_text_field(wp_unslash($_POST['which'])) : 'primary';
  $key   = $which === 'secondary' ? get_option(BEE_OPT_OPENAI_KEY_SECONDARY,'') : get_option(BEE_OPT_OPENAI_KEY_PRIMARY,'');

  if ($key === '') wp_send_json_error(['message'=>'Key not set']);

  $res = wp_remote_get('https://api.openai.com/v1/models/gpt-4o-mini', [
    'timeout' => 15,
    'headers' => [ 'Authorization' => 'Bearer '.$key ],
  ]);

  if (is_wp_error($res)) wp_send_json_error(['message'=>$res->get_error_message()]);
  $code = wp_remote_retrieve_response_code($res);
  $raw  = wp_remote_retrieve_body($res);
  $json = json_decode($raw, true);

  if ($code >= 200 && $code < 300 && is_array($json)) wp_send_json_success(['message'=>'OK']);

  $err = 'HTTP '.$code;
  if (is_array($json) && !empty($json['error']['message'])) $err .= ': '.$json['error']['message'];
  wp_send_json_error(['message'=>$err]);
});

add_action('wp_ajax_bb_test_hub', function(){
  if ( ! current_user_can('manage_woocommerce') ) wp_send_json_error(['message'=>'Unauthorized'], 403);
  check_ajax_referer('bb_test_hub','nonce');

  $res = bumblebee_hub_get_vendors();
  if (is_wp_error($res)) {
    wp_send_json_error(['message'=>$res->get_error_message()], 400);
  }
  wp_send_json_success(['message'=>'OK','count'=> is_array($res) ? count($res) : 0]);
});

add_action('wp_ajax_bb_hub_get_vendors', function(){
  if ( ! current_user_can('manage_woocommerce') ) wp_send_json_error(['message'=>'Unauthorized'], 403);
  check_ajax_referer('bb_test_hub','nonce');
  $res = bumblebee_hub_get_vendors();
  if (is_wp_error($res)) wp_send_json_error(['message'=>$res->get_error_message()], 400);
  wp_send_json_success(['vendors'=>$res]);
});

add_action('wp_ajax_bb_hub_save_vendor', function(){
  if ( ! current_user_can('manage_woocommerce') ) wp_send_json_error(['message'=>'Unauthorized'], 403);
  check_ajax_referer('bb_test_hub','nonce');
  $payload = [
    'id'          => isset($_POST['id']) ? absint($_POST['id']) : 0,
    'name'        => isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '',
    'code'        => isset($_POST['code']) ? sanitize_text_field(wp_unslash($_POST['code'])) : '',
    'description' => isset($_POST['description']) ? sanitize_textarea_field(wp_unslash($_POST['description'])) : '',
  ];
  $res = bumblebee_hub_save_vendor($payload);
  if (is_wp_error($res)) {
    $data = $res->get_error_data();
    wp_send_json_error([
      'message'=>$res->get_error_message(),
      'code'=>$res->get_error_code(),
      'data'=>$data,
      'request'=>$payload,
    ], 400);
  }
  wp_send_json_success(['vendor'=>$res, 'request'=>$payload]);
});

add_action('wp_ajax_bb_hub_delete_vendor', function(){
  if ( ! current_user_can('manage_woocommerce') ) wp_send_json_error(['message'=>'Unauthorized'], 403);
  check_ajax_referer('bb_test_hub','nonce');
  $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
  if (!$id) wp_send_json_error(['message'=>'Missing vendor ID'], 400);
  $res = bumblebee_hub_delete_vendor($id);
  if (is_wp_error($res)) wp_send_json_error(['message'=>$res->get_error_message()], 400);
  wp_send_json_success(['deleted'=>$id]);
});
