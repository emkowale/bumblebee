<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function bb_parse_vendor_code(string $code): array {
  $code = trim($code);
  if ($code === '') return ['vendor'=>'', 'sku'=>''];
  if (preg_match('/^(.+)\\(([^)]+)\\)\\s*$/', $code, $m)) {
    return ['vendor'=>trim($m[1]), 'sku'=>trim($m[2])];
  }
  return ['vendor'=>$code, 'sku'=>''];
}

function bb_render_bumblebee_product_tab(){
  global $post;
  if (!$post || $post->post_type !== 'product') return;

  $production = (string) get_post_meta($post->ID, 'Production', true);
  $print_raw  = (string) get_post_meta($post->ID, 'Print Location', true);
  $print_locs = array_filter(array_map('trim', explode(',', $print_raw)));
  $vendor_code = (string) get_post_meta($post->ID, 'Vendor Code', true);
  $vendor_parts = bb_parse_vendor_code($vendor_code);
  $bb_locations = function_exists('bumblebee_locations_map') ? bumblebee_locations_map() : [];
  $img_exts = ['png','jpg','jpeg','gif','webp'];
  ?>
  <div id="bb_bumblebee_product_data" class="panel woocommerce_options_panel bb-bumblebee-panel">
    <div class="options_group">
      <p class="form-field">
        <label for="bb_production">Production</label>
        <select id="bb_production" name="bb_production">
          <option value="">— Select —</option>
          <?php foreach (['DTG','DTF','Embroidery','UV'] as $opt): ?>
            <option value="<?php echo esc_attr($opt); ?>" <?php selected($production, $opt); ?>><?php echo esc_html($opt); ?></option>
          <?php endforeach; ?>
        </select>
      </p>

      <p class="form-field">
        <label>Print Location</label>
        <span class="bb-print-locations">
          <?php foreach ($bb_locations as $slug => $label): ?>
            <?php
              $checked = in_array($label, $print_locs, true);
              $val = $label;
              $safe_slug = function_exists('bumblebee_slugify_label') ? bumblebee_slugify_label($label) : sanitize_title($label);
              $art_url = (string) get_post_meta($post->ID, 'Original Art ' . $label, true);
              $art_id = $art_url !== '' ? (int) attachment_url_to_postid($art_url) : 0;
              $file_name = '';
              $thumb_url = '';
              if ($art_url !== '') {
                $path = parse_url($art_url, PHP_URL_PATH);
                $file_name = $path ? basename($path) : basename($art_url);
                $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                if (in_array($ext, $img_exts, true)) $thumb_url = $art_url;
              }
            ?>
            <label class="bb-location-check" data-slug="<?php echo esc_attr($safe_slug); ?>">
              <input type="checkbox" class="bb-location-checkbox" name="bb_print_locations[]" value="<?php echo esc_attr($val); ?>" <?php checked($checked); ?> />
              <span class="bb-location-name"><?php echo esc_html($label); ?></span>
              <span class="bb-art-tools">
                <button type="button" class="button bb-upload-original" data-slug="<?php echo esc_attr($safe_slug); ?>">Upload/Choose</button>
                <a class="bb-art-preview" href="<?php echo esc_url($art_url); ?>" target="_blank" rel="noopener">
                  <img class="bb-art-thumb" src="<?php echo esc_url($thumb_url); ?>" alt="" <?php if ($thumb_url === '') echo 'style="display:none"'; ?> />
                </a>
                <a class="bb-art-filename" href="<?php echo esc_url($art_url); ?>" target="_blank" rel="noopener"><?php echo esc_html($file_name); ?></a>
              </span>
              <input type="hidden" class="bb-art-url" name="art_<?php echo esc_attr($safe_slug); ?>_url" value="<?php echo esc_attr($art_url); ?>" />
              <input type="hidden" class="bb-art-id" name="art_<?php echo esc_attr($safe_slug); ?>_id" value="<?php echo esc_attr($art_id); ?>" />
              <span class="bb-art-error" style="display:none;"></span>
            </label>
          <?php endforeach; ?>
        </span>
      </p>

      <p class="form-field">
        <label for="bb_vendor_name">Vendor Name</label>
        <select id="bb_vendor_name" name="bb_vendor_name" data-current="<?php echo esc_attr($vendor_parts['vendor']); ?>">
          <option value="">— Select —</option>
        </select>
      </p>

      <p class="form-field">
        <label for="bb_vendor_item">Vendor Item Number
          <span class="bb-tooltip" aria-label="i.e. DT6000, NL6210, PC43, etc...">?</span>
          <span class="bb-tooltip__text">i.e. DT6000, NL6210, PC43, etc...</span>
        </label>
        <input type="text" id="bb_vendor_item" name="bb_vendor_item" value="<?php echo esc_attr($vendor_parts['sku']); ?>" />
      </p>
    </div>
  </div>
  <?php
}

add_filter('woocommerce_product_data_tabs', function($tabs){
  $tabs['bumblebee'] = [
    'label'    => 'Bumblebee',
    'target'   => 'bb_bumblebee_product_data',
    'priority' => 70,
  ];
  return $tabs;
});

add_action('woocommerce_product_data_panels', 'bb_render_bumblebee_product_tab');

add_action('woocommerce_process_product_meta', function($post_id){
  if (!current_user_can('manage_woocommerce')) return;
  $production = isset($_POST['bb_production']) ? sanitize_text_field(wp_unslash($_POST['bb_production'])) : '';
  $print_locs = isset($_POST['bb_print_locations']) && is_array($_POST['bb_print_locations'])
    ? array_values(array_filter(array_map('sanitize_text_field', wp_unslash($_POST['bb_print_locations']))))
    : [];
  $vendor_name = isset($_POST['bb_vendor_name']) ? sanitize_text_field(wp_unslash($_POST['bb_vendor_name'])) : '';
  $vendor_item = isset($_POST['bb_vendor_item']) ? sanitize_text_field(wp_unslash($_POST['bb_vendor_item'])) : '';

  if ($production !== '') update_post_meta($post_id, 'Production', $production);
  else delete_post_meta($post_id, 'Production');

  if (!empty($print_locs)) update_post_meta($post_id, 'Print Location', implode(', ', $print_locs));
  else delete_post_meta($post_id, 'Print Location');

  if ($vendor_name !== '' && $vendor_item !== '') {
    update_post_meta($post_id, 'Vendor Code', sprintf('%s(%s)', $vendor_name, $vendor_item));
  } else {
    delete_post_meta($post_id, 'Vendor Code');
  }

  if (!empty($print_locs)) {
    foreach ($print_locs as $loc) {
      $slug = function_exists('bumblebee_slugify_label') ? bumblebee_slugify_label($loc) : sanitize_title($loc);
      $url_key = 'art_' . $slug . '_url';
      $id_key  = 'art_' . $slug . '_id';
      $url = isset($_POST[$url_key]) ? esc_url_raw(wp_unslash($_POST[$url_key])) : '';
      $aid = isset($_POST[$id_key]) ? absint($_POST[$id_key]) : 0;
      if ($url !== '') update_post_meta($post_id, 'Original Art ' . $loc, $url);
      if ($aid > 0) {
        wp_update_post([
          'ID'          => $aid,
          'post_parent' => (int) $post_id,
        ]);
      }
    }
  }
});

add_action('admin_enqueue_scripts', function($hook){
  if (!in_array($hook, ['post.php','post-new.php'], true)) return;
  if (get_post_type() !== 'product') return;
  wp_enqueue_media();
  wp_enqueue_style('bumblebee-product-edit', BUMBLEBEE_URL.'assets/product.edit.css', [], BUMBLEBEE_VERSION);
  wp_enqueue_script('bumblebee-product-edit', BUMBLEBEE_URL.'assets/product.edit.js', ['jquery'], BUMBLEBEE_VERSION, true);
  wp_localize_script('bumblebee-product-edit', 'BumblebeeProductEdit', [
    'ajaxurl'  => admin_url('admin-ajax.php'),
    'hubNonce' => wp_create_nonce('bb_hub_vendors'),
  ]);
});
