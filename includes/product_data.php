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

function bb_is_png_url(string $url): bool {
  $path = parse_url($url, PHP_URL_PATH);
  $ext = strtolower(pathinfo($path ?: $url, PATHINFO_EXTENSION));
  return $ext === 'png';
}

function bb_is_png_attachment_id(int $attachment_id): bool {
  if ($attachment_id <= 0) return false;

  $mime = strtolower((string) get_post_mime_type($attachment_id));
  if ($mime === 'image/png') return true;
  if ($mime !== '' && strpos($mime, 'image/') === 0 && $mime !== 'image/png') return false;

  $file = get_attached_file($attachment_id, true);
  if ($file) {
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    if ($ext !== '') return $ext === 'png';
  }
  return true;
}

function bb_render_bumblebee_product_tab(){
  global $post;
  if (!$post || $post->post_type !== 'product') return;

  $production = (string) get_post_meta($post->ID, 'Production', true);
  $special_instructions = (string) get_post_meta($post->ID, 'Special Instructions for production', true);
  if ($special_instructions === '') {
    $special_instructions = (string) get_post_meta($post->ID, 'Special Instructions', true);
  }
  $print_raw  = (string) get_post_meta($post->ID, 'Print Location', true);
  $print_locs = array_filter(array_map('trim', explode(',', $print_raw)));
  $vendor_code = (string) get_post_meta($post->ID, 'Vendor Code', true);
  $vendor_parts = bb_parse_vendor_code($vendor_code);
  $bb_locations = function_exists('bumblebee_locations_map') ? bumblebee_locations_map() : [];
  $img_exts = ['png','jpg','jpeg','gif','webp'];
  $is_fulfill = strcasecmp($production, 'Fulfill') === 0;
  ?>
  <div id="bb_bumblebee_product_data" class="panel woocommerce_options_panel bb-bumblebee-panel">
    <div class="options_group">
      <p class="form-field">
        <label for="bb_production">Production</label>
        <select id="bb_production" name="bb_production">
          <option value="">— Select —</option>
          <?php foreach (['DTG','DTF','Embroidery','UV','Fulfill'] as $opt): ?>
            <option value="<?php echo esc_attr($opt); ?>" <?php selected($production, $opt); ?>><?php echo esc_html($opt); ?></option>
          <?php endforeach; ?>
        </select>
      </p>

      <p class="form-field bb-print-location-field"<?php if ($is_fulfill) echo ' style="display:none;"'; ?>>
        <label>Print Location</label>
        <span class="bb-print-locations">
          <?php foreach ($bb_locations as $slug => $label): ?>
            <?php
              $checked = in_array($label, $print_locs, true);
              $val = $label;
              $safe_slug = function_exists('bumblebee_slugify_label') ? bumblebee_slugify_label($label) : sanitize_title($label);
              $art_url = (string) get_post_meta($post->ID, 'Original Art ' . $label, true);
              $art_id = (int) get_post_meta($post->ID, bb_original_art_id_meta_key($label), true);
              if ($art_id <= 0 && $art_url !== '') {
                $art_id = (int) attachment_url_to_postid($art_url);
              }
              if ($art_id > 0 && bb_is_png_attachment_id($art_id) && function_exists('bumblebee_original_attachment_url')) {
                $original_art_url = bumblebee_original_attachment_url($art_id);
                if ($original_art_url !== '') $art_url = $original_art_url;
              }
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
  $print_locs = isset($_POST['bb_print_locations']) && is_array($_POST['bb_print_locations'])
    ? array_values(array_filter(array_map('sanitize_text_field', wp_unslash($_POST['bb_print_locations']))))
    : [];
  $is_fulfill = strcasecmp($production, 'Fulfill') === 0;
  if ($is_fulfill) $print_locs = [];
  $vendor_name = isset($_POST['bb_vendor_name']) ? bb_decode_entity_text((string) wp_unslash($_POST['bb_vendor_name'])) : '';
  $vendor_item = isset($_POST['bb_vendor_item']) ? bb_decode_entity_text((string) wp_unslash($_POST['bb_vendor_item'])) : '';
  $existing_vendor_code = (string) get_post_meta($post_id, 'Vendor Code', true);
  $existing_vendor_parts = bb_parse_vendor_code($existing_vendor_code);
  $special_instructions = isset($_POST['bb_special_instructions']) ? sanitize_textarea_field(wp_unslash($_POST['bb_special_instructions'])) : '';

  if ($production !== '') update_post_meta($post_id, 'Production', $production);
  else delete_post_meta($post_id, 'Production');

  if (!empty($print_locs)) update_post_meta($post_id, 'Print Location', implode(', ', $print_locs));
  else delete_post_meta($post_id, 'Print Location');

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

  if (!empty($print_locs)) {
    foreach ($print_locs as $loc) {
      $slug = function_exists('bumblebee_slugify_label') ? bumblebee_slugify_label($loc) : sanitize_title($loc);
      $url_key = 'art_' . $slug . '_url';
      $id_key  = 'art_' . $slug . '_id';
      $meta_key = 'Original Art ' . $loc;
      $meta_id_key = bb_original_art_id_meta_key($loc);
      $url = isset($_POST[$url_key]) ? esc_url_raw(wp_unslash($_POST[$url_key])) : '';
      $aid = isset($_POST[$id_key]) ? absint($_POST[$id_key]) : 0;
      if ($aid > 0) {
        if (bb_is_png_attachment_id($aid)) {
          $aid_url = function_exists('bumblebee_original_attachment_url') ? bumblebee_original_attachment_url($aid) : '';
          if ($aid_url) $url = esc_url_raw($aid_url);
        } else {
          $url = '';
        }
      }
      if ($url !== '' && bb_is_png_url($url)) {
        update_post_meta($post_id, $meta_key, $url);
        if ($aid > 0) update_post_meta($post_id, $meta_id_key, $aid);
        else delete_post_meta($post_id, $meta_id_key);
      } else {
        delete_post_meta($post_id, $meta_key);
        delete_post_meta($post_id, $meta_id_key);
      }
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
