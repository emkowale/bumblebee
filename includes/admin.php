<?php
if ( ! defined( 'ABSPATH' ) ) exit;
add_action('admin_menu', function(){
  $cap='manage_woocommerce';
  add_menu_page('Bumblebee','Bumblebee',$cap,'bumblebee-create','bumblebee_render_create_page','dashicons-buddicons-replies',56);
  add_submenu_page('bumblebee-create','Create a Product','Create a Product',$cap,'bumblebee-create','bumblebee_render_create_page');
  add_submenu_page('bumblebee-create','Settings','Settings',$cap,'bumblebee-settings','bumblebee_render_settings_page');
});

add_action('admin_menu', function(){
  // Hide WooCommerce "Add new product" left-nav entry when Bumblebee is active.
  remove_submenu_page('edit.php?post_type=product', 'post-new.php?post_type=product');
}, 999);

add_action('admin_head', function(){
  if (!is_admin()) return;
  $post_type = '';
  $screen = function_exists('get_current_screen') ? get_current_screen() : null;
  if ($screen && !empty($screen->post_type)) $post_type = (string) $screen->post_type;
  if ($post_type === '' && isset($_GET['post_type'])) {
    $post_type = sanitize_key(wp_unslash($_GET['post_type']));
  }
  if ($post_type === '' && isset($_GET['post'])) {
    $post_type = get_post_type(absint($_GET['post']));
  }
  if ($post_type !== 'product') return;
  // Hide "Add new product" buttons on Products list and Product edit screens.
  echo '<style>
    .page-title-action,
    .add-new-h2,
    a[href*="post-new.php?post_type=product"]{display:none!important;}
  </style>';
});

add_action('admin_bar_menu', function($wp_admin_bar){
  if (!is_admin() || !is_object($wp_admin_bar)) return;
  // Remove top admin-bar shortcut: New > Product.
  $wp_admin_bar->remove_node('new-product');
}, 999);

add_action('admin_init', function(){
  // Route direct product creation attempts through Bumblebee.
  if (!is_admin()) return;
  $pagenow = $GLOBALS['pagenow'] ?? '';
  if ($pagenow !== 'post-new.php') return;
  $post_type = isset($_GET['post_type']) ? sanitize_key(wp_unslash($_GET['post_type'])) : 'post';
  if ($post_type !== 'product') return;
  if (!current_user_can('manage_woocommerce')) return;
  wp_safe_redirect(admin_url('admin.php?page=bumblebee-create'));
  exit;
});

add_action('admin_enqueue_scripts', function($hook){
  if ($hook !== 'toplevel_page_bumblebee-create' && $hook !== 'bumblebee_page_bumblebee-create') {
    return;
  }
  if (function_exists('bumblebee_enqueue_create_assets')) {
    bumblebee_enqueue_create_assets();
  }
});
