<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action('admin_enqueue_scripts', function($hook){
  if($hook !== 'bumblebee_page_bumblebee-settings') return;
  $ver = BUMBLEBEE_VERSION;
  $path = BUMBLEBEE_PATH.'assets/settings.js';
  if (file_exists($path)) $ver = filemtime($path);
  wp_enqueue_script('bumblebee-settings', BUMBLEBEE_URL.'assets/settings.js', ['jquery'], $ver, true);
  wp_localize_script('bumblebee-settings','BumblebeeSettings',[
    'ajaxurl' => admin_url('admin-ajax.php'),
    'nonce'   => wp_create_nonce('bb_test_openai_key'),
    'hubNonce'=> wp_create_nonce('bb_test_hub'),
  ]);
});
