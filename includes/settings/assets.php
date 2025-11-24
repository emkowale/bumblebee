<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action('admin_enqueue_scripts', function($hook){
  if($hook !== 'bumblebee_page_bumblebee-settings') return;
  wp_enqueue_script('bumblebee-settings', BUMBLEBEE_URL.'assets/settings.js', ['jquery'], BUMBLEBEE_VERSION, true);
  wp_localize_script('bumblebee-settings','BumblebeeSettings',[
    'ajaxurl' => admin_url('admin-ajax.php'),
    'nonce'   => wp_create_nonce('bb_test_openai_key'),
  ]);
});
