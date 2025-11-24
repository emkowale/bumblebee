<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function bumblebee_soundwave_attr_present(WC_Product $product, string $target): bool {
  $target = strtolower($target);
  foreach ($product->get_attributes() as $attr) {
    $name  = $attr->get_name();
    $label = strtolower(wc_attribute_label($name));
    $slug  = strtolower(preg_replace('/^pa_/', '', $name));
    if ($label === $target || $slug === $target) return true;
  }
  return false;
}

function bumblebee_soundwave_status(int $product_id): array {
  $product = wc_get_product($product_id);
  if (!$product) return ['ready'=>false,'checks'=>[]];

  $checks = [];

  $image_id = (int) $product->get_image_id();
  $checks[] = ['label'=>'Product Image','ok'=>$image_id > 0];

  $has_color = bumblebee_soundwave_attr_present($product, 'color');
  $checks[] = ['label'=>'Color Attribute','ok'=>$has_color];

  $has_size = bumblebee_soundwave_attr_present($product, 'size');
  $checks[] = ['label'=>'Size Attribute','ok'=>$has_size];

  $parent_sku = (string) $product->get_sku();
  $checks[] = ['label'=>'Parent product SKU','ok'=> $parent_sku !== ''];

  $variations = [];
  if ($product->is_type('variable')) {
    foreach ($product->get_children() as $vid) {
      $v = wc_get_product($vid);
      if ($v) $variations[] = $v;
    }
  }

  $all_var_price = true; $all_var_image = true; $all_var_sku = true;
  foreach ($variations as $v) {
    $price = (float) $v->get_price();
    if ($price <= 0) $all_var_price = false;
    if ((int) $v->get_image_id() === 0) $all_var_image = false;
    if ((string) $v->get_sku() === '') $all_var_sku = false;
  }

  $checks[] = ['label'=>'Price for each variation','ok'=>$product->is_type('variable') ? $all_var_price && count($variations)>0 : false];
  $checks[] = ['label'=>'All variations have image','ok'=>$product->is_type('variable') ? $all_var_image && count($variations)>0 : false];
  $checks[] = ['label'=>'All variations have SKU','ok'=>$product->is_type('variable') ? $all_var_sku && count($variations)>0 : false];

  $meta_keys = [
    'Company Name'      => 'Company Name',
    'Production'        => 'Production',
    'Print Location'    => 'Print Location',
    'Product Image URL' => 'Product Image URL',
    'Site Slug'         => 'Site Slug',
    'Vendor Code'       => 'Vendor Code',
  ];

  foreach ($meta_keys as $label => $meta_key) {
    $val = (string) get_post_meta($product_id, $meta_key, true);
    $checks[] = ['label'=>$label, 'ok'=> $val !== ''];
  }

  $print_locations_raw = (string) get_post_meta($product_id, 'Print Location', true);
  $print_locations = array_filter(array_map('trim', explode(',', $print_locations_raw)));
  if (!empty($print_locations)) {
    foreach ($print_locations as $loc) {
      $val = (string) get_post_meta($product_id, 'Original Art ' . $loc, true);
      $checks[] = ['label'=>"Original Art {$loc}", 'ok'=> $val !== ''];
    }
  } else {
    $checks[] = ['label'=>'Original Art (per Print Location)','ok'=>false];
  }

  $ready = true;
  foreach ($checks as $c) { if (empty($c['ok'])) { $ready=false; break; } }

  return ['ready'=>$ready, 'checks'=>$checks];
}

add_filter('manage_edit-product_columns', function($cols){
  $cols['bb_soundwave'] = 'Soundwave Ready?';
  return $cols;
});

add_filter('get_hidden_columns', function($hidden, $screen) {
  if (isset($screen->id) && $screen->id === 'edit-product') {
    $hidden = array_diff($hidden, ['bb_soundwave']);
  }
  return $hidden;
}, 10, 2);

add_action('manage_product_posts_custom_column', function($col, $post_id){
  if ($col !== 'bb_soundwave') return;
  $status = bumblebee_soundwave_status((int)$post_id);
  $icon = $status['ready'] ? 'yes' : 'no-alt';
  $color = $status['ready'] ? '#1a7f37' : '#b32d2e';
  echo '<span class="dashicons dashicons-' . esc_attr($icon) . '" style="color:' . esc_attr($color) . ';"></span>';
}, 10, 2);

add_action('admin_enqueue_scripts', function($hook){
  if ($hook === 'edit.php' && isset($_GET['post_type']) && $_GET['post_type'] === 'product') {
    wp_enqueue_style('dashicons');
    wp_add_inline_style('dashicons', '.column-bb_soundwave, th.manage-column.column-bb_soundwave{ text-align:center; } .column-bb_soundwave .dashicons{font-size:18px;line-height:20px; display:inline-block;}');
  }

  if (in_array($hook, ['post.php','post-new.php'], true) && get_post_type() === 'product') {
    wp_enqueue_style('dashicons');
    wp_enqueue_script('bb-soundwave-admin', BUMBLEBEE_URL.'assets/admin.soundwave.js', ['jquery'], BUMBLEBEE_VERSION, true);
    wp_localize_script('bb-soundwave-admin', 'BumblebeeSoundwave', [
      'ajaxurl' => admin_url('admin-ajax.php'),
      'nonce'   => wp_create_nonce('bb_soundwave_status'),
      'post_id' => get_the_ID(),
    ]);
    wp_add_inline_style('dashicons', '
      #bb-soundwave-box .bb-sw-list{margin:0;padding-left:0;list-style:none;}
      #bb-soundwave-box .bb-sw-item{display:flex;align-items:center;gap:6px;margin:4px 0;}
      #bb-soundwave-box .bb-sw-item .dashicons-yes{color:#1a7f37;}
      #bb-soundwave-box .bb-sw-item .dashicons-no-alt{color:#b32d2e;}
      #bb-soundwave-box .bb-sw-status{margin-bottom:6px;font-weight:600;}
    ');
  }
});

add_action('add_meta_boxes', function($post_type){
  if ($post_type !== 'product') return;
  add_meta_box('bb-soundwave-box', 'Soundwave Ready?', function($post){
    echo '<div id="bb-soundwave-box">';
    echo '<div class="bb-sw-status">Checking…</div>';
    echo '<ul class="bb-sw-list"><li class="bb-sw-item"><span class="dashicons dashicons-update"></span>Loading...</li></ul>';
    echo '<p class="description">Live readiness for Soundwave requirements.</p>';
    echo '</div>';
  }, 'product', 'side', 'high');
});

add_action('wp_ajax_bumblebee_soundwave_status', function(){
  if (!current_user_can('manage_woocommerce')) wp_send_json_error('Unauthorized', 403);
  check_ajax_referer('bb_soundwave_status','nonce');
  $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
  if (!$post_id) wp_send_json_error('Missing product', 400);
  $status = bumblebee_soundwave_status($post_id);
  wp_send_json_success($status);
});
