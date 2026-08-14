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

function bb_decode_entity_text(string $value): string {
  return sanitize_text_field(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
}

function bb_original_art_id_meta_key(string $label): string {
  return 'Original Art ID ' . $label;
}

function bb_original_art_url_is_allowed(string $url, string $production): bool {
  if (!function_exists('bumblebee_extension_from_url') || !function_exists('bumblebee_is_allowed_original_art_extension')) {
    $path = parse_url($url, PHP_URL_PATH);
    $ext = strtolower(pathinfo($path ?: $url, PATHINFO_EXTENSION));
    return $ext === 'png';
  }

  return bumblebee_is_allowed_original_art_extension(
    bumblebee_extension_from_url($url),
    $production
  );
}

function bb_original_art_attachment_is_allowed(int $attachment_id, string $production): bool {
  if (!function_exists('bumblebee_is_allowed_original_art_attachment')) {
    return $attachment_id > 0;
  }

  return bumblebee_is_allowed_original_art_attachment($attachment_id, $production);
}

function bb_render_bumblebee_product_tab(){
  global $post;
  if (!$post || $post->post_type !== 'product') return;

  $production = (string) get_post_meta($post->ID, 'Production', true);
  $special_instructions = (string) get_post_meta($post->ID, 'Special Instructions for production', true);
  if ($special_instructions === '') {
    $special_instructions = (string) get_post_meta($post->ID, 'Special Instructions', true);
  }
  $vendor_code = (string) get_post_meta($post->ID, 'Vendor Code', true);
  $vendor_parts = bb_parse_vendor_code($vendor_code);
  ?>
  <div id="bb_bumblebee_product_data" class="panel woocommerce_options_panel bb-bumblebee-panel">
    <div class="options_group">
      <p class="form-field">
        <label for="bb_production">Production</label>
        <select id="bb_production" name="bb_production">
          <option value="">— Select —</option>
          <?php foreach (function_exists('bumblebee_production_options') ? bumblebee_production_options() : ['DTG', 'DTF', 'Screen Print', 'Embroidery', 'UV', 'Fulfill'] as $opt): ?>
            <option value="<?php echo esc_attr($opt); ?>" <?php selected($production, $opt); ?>><?php echo esc_html($opt); ?></option>
          <?php endforeach; ?>
        </select>
      </p>

      <p class="form-field">
        <label for="bb_vendor_name">Vendor Name</label>
        <select id="bb_vendor_name" name="bb_vendor_name" data-current="<?php echo esc_attr($vendor_parts['vendor']); ?>">
          <option value="">— Select —</option>
        </select>
      </p>

      <p class="form-field">
        <label for="bb_vendor_item">Vendor Item Number
          <span class="bb-tooltip dashicons dashicons-editor-help" aria-label="i.e. DT6000, NL6210, PC43, etc..." title="i.e. DT6000, NL6210, PC43, etc..."></span>
        </label>
        <input type="text" id="bb_vendor_item" name="bb_vendor_item" value="<?php echo esc_attr($vendor_parts['sku']); ?>" />
      </p>

      <p class="form-field">
        <label for="bb_special_instructions">Special Instructions for production
          <span class="bb-tooltip dashicons dashicons-editor-help" aria-label="For example, if the production type is Embroidery list the thread color" title="For example, if the production type is Embroidery list the thread color"></span>
        </label>
        <textarea id="bb_special_instructions" name="bb_special_instructions" rows="4" class="short"><?php echo esc_textarea($special_instructions); ?></textarea>
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
  $vendor_name = isset($_POST['bb_vendor_name']) ? bb_decode_entity_text((string) wp_unslash($_POST['bb_vendor_name'])) : '';
  $vendor_item = isset($_POST['bb_vendor_item']) ? bb_decode_entity_text((string) wp_unslash($_POST['bb_vendor_item'])) : '';
  $existing_vendor_code = (string) get_post_meta($post_id, 'Vendor Code', true);
  $existing_vendor_parts = bb_parse_vendor_code($existing_vendor_code);
  $special_instructions = isset($_POST['bb_special_instructions']) ? sanitize_textarea_field(wp_unslash($_POST['bb_special_instructions'])) : '';

  if ($production !== '') update_post_meta($post_id, 'Production', $production);
  else delete_post_meta($post_id, 'Production');

  delete_post_meta($post_id, 'Print Location');

  // Preserve existing vendor value when one side is missing (common when Hub list omits current vendor).
  $has_vendor_name = ($vendor_name !== '');
  $has_vendor_item = ($vendor_item !== '');
  if ($has_vendor_name xor $has_vendor_item) {
    if (!$has_vendor_name && $existing_vendor_parts['vendor'] !== '') $vendor_name = $existing_vendor_parts['vendor'];
    if (!$has_vendor_item && $existing_vendor_parts['sku'] !== '') $vendor_item = $existing_vendor_parts['sku'];
  }

  if ($vendor_name !== '' && $vendor_item !== '') {
    update_post_meta($post_id, 'Vendor Code', sprintf('%s(%s)', $vendor_name, $vendor_item));
  } else {
    delete_post_meta($post_id, 'Vendor Code');
  }

  if ($special_instructions !== '') {
    update_post_meta($post_id, 'Special Instructions', $special_instructions);
    update_post_meta($post_id, 'Special Instructions for production', $special_instructions);
  } else {
    delete_post_meta($post_id, 'Special Instructions');
    delete_post_meta($post_id, 'Special Instructions for production');
  }
});

add_action('admin_enqueue_scripts', function($hook){
  if (!in_array($hook, ['post.php','post-new.php'], true)) return;
  if (get_post_type() !== 'product') return;
  $style_ver = function_exists('bumblebee_asset_version') ? bumblebee_asset_version('assets/product.edit.css') : BUMBLEBEE_VERSION;
  $script_ver = function_exists('bumblebee_asset_version') ? bumblebee_asset_version('assets/product.edit.js') : BUMBLEBEE_VERSION;
  wp_enqueue_media();
  wp_enqueue_style('bumblebee-product-edit', BUMBLEBEE_URL.'assets/product.edit.css', [], $style_ver);
  wp_enqueue_script('bumblebee-product-edit', BUMBLEBEE_URL.'assets/product.edit.js', ['jquery'], $script_ver, true);
  wp_localize_script('bumblebee-product-edit', 'BumblebeeProductEdit', [
    'ajaxurl' => admin_url('admin-ajax.php'),
    'hubNonce' => wp_create_nonce('bb_hub_vendors'),
    'originalAttachmentNonce' => wp_create_nonce('bb_original_attachment_url'),
  ]);
});
