<?php
if ( ! defined( 'ABSPATH' ) ) exit;
add_action('admin_menu', function(){
  $cap='manage_woocommerce';
  add_menu_page('Bumblebee','Bumblebee',$cap,'bumblebee-create','bumblebee_render_create_page','dashicons-buddicons-replies',56);
  add_submenu_page('bumblebee-create','Create a Product','Create a Product',$cap,'bumblebee-create','bumblebee_render_create_page');
  add_submenu_page('bumblebee-create','Settings','Settings',$cap,'bumblebee-settings','bumblebee_render_settings_page');
});

add_action('admin_enqueue_scripts', function($hook){
  if ($hook !== 'toplevel_page_bumblebee-create' && $hook !== 'bumblebee_page_bumblebee-create') {
    return;
  }
  if (function_exists('bumblebee_enqueue_create_assets')) {
    bumblebee_enqueue_create_assets();
  }
});
