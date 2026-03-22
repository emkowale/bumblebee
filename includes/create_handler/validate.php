<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function bumblebee_clean_text_entity_aware($raw): string {
  $value = is_scalar($raw) ? (string) $raw : '';
  $value = wp_unslash($value);
  $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
  return sanitize_text_field($value);
}

function bumblebee_required_field($key, $label){
  if(!isset($_POST[$key]) || trim(wp_unslash($_POST[$key]))===''){
    wp_send_json(['success'=>false,'message'=> $label.' is required']);
  }
  return $_POST[$key];
}

function bumblebee_color_options_from_entries(array $colors): array {
  $opts = [];
  $image_map = [];
  foreach ($colors as $c) {
    if (!is_array($c)) continue;
    $name = isset($c['name']) ? (string) $c['name'] : '';
    if ($name === '') continue;
    if (!isset($image_map[$name])) {
      $opts[] = $name;
      $image_map[$name] = isset($c['image_id']) ? absint($c['image_id']) : 0;
    }
  }
  return [
    'options'   => $opts,
    'image_map' => $image_map,
  ];
}

function bumblebee_attachment_extension_from_id(int $attachment_id): string {
  $file = get_attached_file($attachment_id, true);
  if ($file) {
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    if ($ext !== '') return $ext;
  }

  $url = wp_get_attachment_url($attachment_id);
  if ($url) {
    $path = parse_url($url, PHP_URL_PATH);
    $ext = strtolower(pathinfo($path ?: $url, PATHINFO_EXTENSION));
    if ($ext !== '') return $ext;
  }

  $mime = strtolower((string) get_post_mime_type($attachment_id));
  if ($mime === 'image/webp') return 'webp';
  if ($mime === 'image/png') return 'png';
  if ($mime === 'image/jpeg' || $mime === 'image/jpg') return 'jpg';
  return '';
}

function bumblebee_attachment_dimensions(int $attachment_id): array {
  $width = 0;
  $height = 0;

  $meta = wp_get_attachment_metadata($attachment_id);
  if (is_array($meta)) {
    $width = isset($meta['width']) ? absint($meta['width']) : 0;
    $height = isset($meta['height']) ? absint($meta['height']) : 0;
  }
  if ($width > 0 && $height > 0) {
    return ['width' => $width, 'height' => $height];
  }

  $file = get_attached_file($attachment_id, true);
  if ($file && file_exists($file)) {
    $size = function_exists('wp_getimagesize') ? wp_getimagesize($file) : @getimagesize($file);
    if (is_array($size) && isset($size[0], $size[1])) {
      $width = absint($size[0]);
      $height = absint($size[1]);
    }
  }
  return ['width' => $width, 'height' => $height];
}

function bumblebee_validate_mockup_attachment(int $attachment_id) {
  if ($attachment_id <= 0) {
    return new WP_Error('bb_mockup_missing', 'mockup image is required.');
  }

  $file = get_attached_file($attachment_id, true);
  if (!$file || !file_exists($file)) {
    return new WP_Error('bb_mockup_missing', 'mockup image is invalid or missing.');
  }

  $ext = bumblebee_attachment_extension_from_id($attachment_id);
  if ($ext !== 'webp' && $ext !== 'png' && $ext !== 'jpg' && $ext !== 'jpeg') {
    return new WP_Error('bb_mockup_type', 'mockup image must be a .webp, .png, or .jpg file.');
  }

  $dims = bumblebee_attachment_dimensions($attachment_id);

  return [
    'ext'    => $ext,
    'width'  => (int) $dims['width'],
    'height' => (int) $dims['height'],
  ];
}

function bumblebee_prepare_single_mockup_attachment(int $attachment_id, string $company_name, string $product_title, bool $preserve_original = false) {
  $check = bumblebee_validate_mockup_attachment($attachment_id);
  if (is_wp_error($check)) return $check;

  $image_id = $attachment_id;
  $converted = false;

  if (($check['ext'] ?? '') !== 'webp') {
    if (!function_exists('bumblebee_convert_and_rename_to_webp')) {
      return new WP_Error('bb_mockup_convert', 'mockup image could not be converted to WebP.');
    }

    $converted_id = absint(bumblebee_convert_and_rename_to_webp($attachment_id, $company_name, $product_title, [
      'allow_original_fallback' => false,
      'delete_original'         => !$preserve_original,
    ]));
    if ($converted_id <= 0 || $converted_id === $attachment_id) {
      return new WP_Error('bb_mockup_convert', 'mockup image could not be converted to WebP.');
    }

    $converted_check = bumblebee_validate_mockup_attachment($converted_id);
    if (is_wp_error($converted_check) || (($converted_check['ext'] ?? '') !== 'webp')) {
      return new WP_Error('bb_mockup_convert', 'mockup image conversion failed.');
    }

    $image_id = $converted_id;
    $converted = true;
  }

  return [
    'image_id'  => $image_id,
    'converted' => $converted,
  ];
}

function bumblebee_prepare_mockup_colors(array $colors, string $company_name, string $product_title, array $preserve_ids = []) {
  $out = [];
  $converted_map = [];

  foreach ($colors as $i => $row) {
    $name = (is_array($row) && isset($row['name'])) ? (string) $row['name'] : '';
    $image_id = (is_array($row) && isset($row['image_id'])) ? absint($row['image_id']) : 0;

    if ($image_id > 0 && isset($converted_map[$image_id])) {
      $image_id = (int) $converted_map[$image_id];
    }

    if ($image_id > 0) {
      $source_id = $image_id;
      $preserve_original = in_array($source_id, $preserve_ids, true);
      $prepared = bumblebee_prepare_single_mockup_attachment($source_id, $company_name, $product_title, $preserve_original);
      if (is_wp_error($prepared)) {
        return new WP_Error($prepared->get_error_code(), sprintf('Color %d %s', $i + 1, $prepared->get_error_message()));
      }

      $prepared_id = isset($prepared['image_id']) ? absint($prepared['image_id']) : 0;
      if ($prepared_id <= 0) {
        return new WP_Error('bb_mockup_convert', sprintf('Color %d mockup image conversion failed.', $i + 1));
      }

      if ($prepared_id !== $source_id) {
        $converted_map[$source_id] = $prepared_id;
      }
      $image_id = $prepared_id;
    }

    $out[] = [
      'name'     => $name,
      'image_id' => $image_id,
    ];
  }

  return $out;
}

function bumblebee_parse_colors($raw): array {
  if ($raw === null) wp_send_json(['success'=>false,'message'=>'Color selection is required.']);

  $decoded = json_decode($raw, true);
  if (!is_array($decoded)) wp_send_json(['success'=>false,'message'=>'Colors are invalid.']);

  $selected = isset($decoded['selected']) ? (bool)$decoded['selected'] : false;
  if (!$selected) wp_send_json(['success'=>false,'message'=>'Color selection is required.']);

  $count = isset($decoded['count']) ? intval($decoded['count']) : 0;
  if ($count < 0) $count = 0;
  if ($count > 100) $count = 100;

  $colors_in = (isset($decoded['colors']) && is_array($decoded['colors'])) ? $decoded['colors'] : [];
  $colors = [];
  for ($i = 0; $i < $count; $i++) {
    $row  = (isset($colors_in[$i]) && is_array($colors_in[$i])) ? $colors_in[$i] : [];
    $name = isset($row['name']) ? sanitize_text_field( wp_unslash($row['name']) ) : '';
    if ($count > 0 && $name === '') {
      wp_send_json(['success'=>false,'message'=> sprintf('Color %d name is required.', $i+1)]);
    }
    $img = isset($row['image_id']) ? absint($row['image_id']) : 0;
    if ($count > 0 && $img <= 0) {
      wp_send_json(['success'=>false,'message'=> sprintf('Color %d mockup image is required.', $i+1)]);
    }
    if ($img > 0) {
      $mockup_check = bumblebee_validate_mockup_attachment($img);
      if (is_wp_error($mockup_check)) {
        wp_send_json(['success'=>false,'message'=> sprintf('Color %d %s', $i+1, $mockup_check->get_error_message())]);
      }
    }
    $colors[] = [
      'name'     => $name !== '' ? $name : sprintf('Color %d', $i+1),
      'image_id' => $img,
    ];
  }

  $mapped = bumblebee_color_options_from_entries($colors);

  return [
    'count'       => $count,
    'colors'      => $colors,
    'options'     => $mapped['options'],
    'image_map'   => $mapped['image_map'],
    'has_colors'  => $count > 0 && !empty($mapped['options']),
  ];
}

function bumblebee_parse_vendors($raw): array {
  if ($raw === null) wp_send_json(['success'=>false,'message'=>'Vendor selection is required.']);

  $decoded = json_decode($raw, true);
  if (!is_array($decoded)) wp_send_json(['success'=>false,'message'=>'Vendors are invalid.']);

  $selected = isset($decoded['selected']) ? (bool)$decoded['selected'] : false;
  if (!$selected) wp_send_json(['success'=>false,'message'=>'Vendor selection is required.']);

  $count = isset($decoded['count']) ? intval($decoded['count']) : 0;
  if ($count < 1) wp_send_json(['success'=>false,'message'=>'Select at least one vendor.']);
  if ($count > 5) $count = 5;

  $vendors_in = (isset($decoded['vendors']) && is_array($decoded['vendors'])) ? $decoded['vendors'] : [];
  $vendors = [];
  for ($i = 0; $i < $count; $i++) {
    $row  = (isset($vendors_in[$i]) && is_array($vendors_in[$i])) ? $vendors_in[$i] : [];
    $name = isset($row['name']) ? bumblebee_clean_text_entity_aware($row['name']) : '';
    $item = isset($row['item']) ? bumblebee_clean_text_entity_aware($row['item']) : '';
    if ($name === '') {
      wp_send_json(['success'=>false,'message'=> sprintf('Vendor %d name is required.', $i+1)]);
    }
    if ($item === '') {
      wp_send_json(['success'=>false,'message'=> sprintf('Vendor %d item number is required.', $i+1)]);
    }
    $vendors[] = ['name'=>$name, 'item'=>$item];
  }

  $flat = [];
  foreach ($vendors as $v) {
    $flat[] = sprintf('%s(%s)', $v['name'], $v['item']);
  }

  return [
    'count' => $count,
    'list'  => $vendors,
    'joined'=> implode(',', $flat),
  ];
}

function bumblebee_art_urls_from_post(): array {
  $art = [];
  foreach ($_POST as $k => $v) {
    if (!is_string($k)) continue;
    if (!preg_match('/^art_([a-z0-9_]+)_url$/i', $k, $m)) continue;
    $url = trim((string) wp_unslash($v));
    if ($url === '') continue;
    $art[] = [
      'slug' => strtolower($m[1]),
      'url'  => esc_url_raw($url),
    ];
  }
  return $art;
}

function bumblebee_art_ids_from_post(): array {
  $ids = [];
  foreach ($_POST as $k => $v) {
    if (!is_string($k)) continue;
    if (!preg_match('/^art_([a-z0-9_]+)_id$/i', $k)) continue;
    $id = absint($v);
    if ($id > 0) $ids[] = $id;
  }
  if (empty($ids)) return [];
  return array_values(array_unique($ids));
}

function bumblebee_art_ids_by_slug_from_post(): array {
  $out = [];
  foreach ($_POST as $k => $v) {
    if (!is_string($k)) continue;
    if (!preg_match('/^art_([a-z0-9_]+)_id$/i', $k, $m)) continue;
    $slug = strtolower($m[1]);
    $id = absint($v);
    if ($slug !== '' && $id > 0) $out[$slug] = $id;
  }
  return $out;
}

function bumblebee_is_png_art_attachment(int $attachment_id): bool {
  if ($attachment_id <= 0) return false;

  $mime = strtolower((string) get_post_mime_type($attachment_id));
  if ($mime === 'image/png') return true;
  if ($mime !== '' && strpos($mime, 'image/') === 0 && $mime !== 'image/png') return false;

  if (function_exists('bumblebee_attachment_extension_from_id')) {
    $ext = bumblebee_attachment_extension_from_id($attachment_id);
    if ($ext === 'png') return true;
    if ($ext !== '' && $ext !== 'png') return false;
  }

  // Unknown metadata: do not hard-fail here; downstream save path resolves against attachment ID.
  return true;
}

function bumblebee_art_label_from_slug(string $slug): string {
  $slug = strtolower($slug);
  if (function_exists('bumblebee_locations_map')) {
    $map = bumblebee_locations_map();
    if (is_array($map) && isset($map[$slug])) return $map[$slug];
  }
  $fallback = ucwords(str_replace('_', ' ', $slug));
  return $fallback !== '' ? $fallback : 'Original Art';
}

function bumblebee_extension_from_url(string $url): string {
  $path = parse_url($url, PHP_URL_PATH);
  return strtolower(pathinfo($path ?: $url, PATHINFO_EXTENSION));
}

function bumblebee_invalid_art_urls(array $art_urls, array $art_ids_by_slug = []): array {
  $allowed = ['png'];
  $bad = [];
  foreach ($art_urls as $art) {
    if (!is_array($art) || empty($art['url'])) continue;
    $url  = isset($art['url']) ? (string) $art['url'] : '';
    $slug = isset($art['slug']) ? (string) $art['slug'] : '';
    $att_id = isset($art_ids_by_slug[$slug]) ? absint($art_ids_by_slug[$slug]) : 0;
    if ($att_id > 0) {
      if (!bumblebee_is_png_art_attachment($att_id)) {
        $bad[] = bumblebee_art_label_from_slug($slug);
      }
      continue;
    }

    $path = parse_url($url, PHP_URL_PATH);
    $ext  = strtolower(pathinfo($path ?: $url, PATHINFO_EXTENSION));
    if ($ext === '' || !in_array($ext, $allowed, true)) {
      $bad[] = bumblebee_art_label_from_slug($slug);
    }
  }
  return array_values(array_unique($bad));
}

function bumblebee_parse_create_request(): array {
  if(!current_user_can('manage_woocommerce')) wp_send_json(['success'=>false,'message'=>'Unauthorized']);
  if(!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'],'bb_create_product')) wp_send_json(['success'=>false,'message'=>'Bad nonce']);
  if(!class_exists('WC_Product_Variable')) wp_send_json(['success'=>false,'message'=>'WooCommerce required']);

  $title  = sanitize_text_field( wp_unslash(bumblebee_required_field('title','Title')) );
  $price  = wc_clean( wp_unslash(bumblebee_required_field('price','Price')) );
  if ( !is_numeric($price) || floatval($price) <= 0 ) {
    wp_send_json(['success'=>false,'message'=>'Price must be greater than 0']);
  }

  $scrubs_choice = function_exists('bumblebee_scrubs_choice_from_request')
    ? bumblebee_scrubs_choice_from_request()
    : 'no';
  $scrub_attributes = ($scrubs_choice === 'yes' && function_exists('bumblebee_parse_scrub_attributes_from_request'))
    ? bumblebee_parse_scrub_attributes_from_request()
    : [];

  $tax        = sanitize_text_field( wp_unslash( isset($_POST['tax_status']) ? $_POST['tax_status'] : 'taxable' ) );
  $vector_id  = isset($_POST['vector_id']) ? absint($_POST['vector_id']) : 0;
  $vector_url = esc_url_raw( wp_unslash( isset($_POST['vector_url']) ? $_POST['vector_url'] : '' ) );

  $color_data = bumblebee_parse_colors( isset($_POST['color_data']) ? wp_unslash($_POST['color_data']) : null );
  $sizes  = sanitize_text_field( wp_unslash(bumblebee_required_field('sizes','Sizes')) );
  $vendor_data = bumblebee_parse_vendors( isset($_POST['vendor_data']) ? wp_unslash($_POST['vendor_data']) : null );
  $prod   = sanitize_text_field( wp_unslash(bumblebee_required_field('production','Production')) );
  $is_fulfill = (strcasecmp($prod, 'Fulfill') === 0);
  $print_raw = $is_fulfill
    ? ''
    : sanitize_text_field( wp_unslash(bumblebee_required_field('print_location','Print Location')) );
  $special = isset($_POST['special_instructions']) ? sanitize_textarea_field( wp_unslash($_POST['special_instructions']) ) : '';
  $art_urls = bumblebee_art_urls_from_post();
  $art_ids = bumblebee_art_ids_from_post();
  $art_ids_by_slug = bumblebee_art_ids_by_slug_from_post();

  $invalid_art = bumblebee_invalid_art_urls($art_urls, $art_ids_by_slug);
  if (!empty($invalid_art)) {
    $msg = 'Original Art must be a PNG. Please update: ' . implode(', ', $invalid_art) . '.';
    wp_send_json(['success'=>false,'message'=>$msg]);
  }

  $company_name = get_bloginfo('name');
  $prepared_colors = bumblebee_prepare_mockup_colors($color_data['colors'], $company_name, $title, $art_ids);
  if (is_wp_error($prepared_colors)) {
    wp_send_json(['success'=>false,'message'=>$prepared_colors->get_error_message()]);
  }
  $color_data['colors'] = $prepared_colors;
  $mapped_colors = bumblebee_color_options_from_entries($color_data['colors']);
  $color_data['options'] = $mapped_colors['options'];
  $color_data['image_map'] = $mapped_colors['image_map'];
  $color_data['has_colors'] = $color_data['count'] > 0 && !empty($mapped_colors['options']);

  $image_id = 0;
  if (!empty($color_data['colors'][0]['image_id'])) {
    $image_id = absint($color_data['colors'][0]['image_id']);
  }

  $to_opts = function($csv){
    $out=[]; foreach(array_map('trim', explode(',', (string)$csv)) as $v){ if($v!=='') $out[]=$v; }
    return array_values(array_unique($out));
  };

  return [
    'title'        => $title,
    'price'        => $price,
    'scrubs'       => $scrubs_choice,
    'scrub_attributes' => $scrub_attributes,
    'tax_status'   => $tax,
    'image_id'     => $image_id,
    'vector_id'    => $vector_id,
    'vector_url'   => $vector_url,
    'sizes'        => $sizes,
    'vendor_code'  => $vendor_data['joined'],
    'vendor_list'  => $vendor_data['list'],
    'production'   => $prod,
    'print_raw'    => $print_raw,
    'special_instructions' => $special,
    'color_count'  => $color_data['count'],
    'color_entries'=> $color_data['colors'],
    'color_opts'   => $color_data['options'],
    'color_image_map' => $color_data['image_map'],
    'has_colors'   => $color_data['has_colors'],
    'size_opts'    => $to_opts($sizes),
    'locations'    => $to_opts($print_raw),
    'company_name' => $company_name,
    'site_slug'    => function_exists('bumblebee_site_slug_from_subdomain') ? bumblebee_site_slug_from_subdomain() : 'site',
    'image_url'    => isset($_POST['image_url']) ? esc_url_raw(wp_unslash($_POST['image_url'])) : '',
  ];
}
