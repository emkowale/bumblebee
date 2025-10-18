<?php
if ( ! defined( 'ABSPATH' ) ) exit;
add_action('admin_menu', function(){
  $cap='manage_woocommerce';
  add_menu_page('Bumblebee','Bumblebee',$cap,'bumblebee-create','bumblebee_render_create_page','dashicons-buddicons-replies',56);
  add_submenu_page('bumblebee-create','Create a Product','Create a Product',$cap,'bumblebee-create','bumblebee_render_create_page');
  add_submenu_page('bumblebee-create','Settings','Settings',$cap,'bumblebee-settings','bumblebee_render_settings_page');
});