<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function bumblebee_required_field($key, $label){
  if(!isset($_POST[$key]) || trim(wp_unslash($_POST[$key]))===''){
    wp_send_json(['success'=>false,'message'=> $label.' is required']);
  }
  return $_POST[$key];
}

function bumblebee_parse_colors($raw): array {
  if ($raw === null) wp_send_json(['success'=>false,'message'=>'Color selection is required.']);

  $decoded = json_decode($raw, true);
  if (!is_array($decoded)) wp_send_json(['success'=>false,'message'=>'Colors are invalid.']);

  $selected = isset($decoded['selected']) ? (bool)$decoded['selected'] : false;
  if (!$selected) wp_send_json(['success'=>false,'message'=>'Color selection is required.']);

  $count = isset($decoded['count']) ? intval($decoded['count']) : 0;
  if ($count < 0) $count = 0;
  if ($count > 50) $count = 50;

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
      $url = wp_get_attachment_url($img);
      $ext = $url ? bumblebee_extension_from_url($url) : '';
      if ($ext !== 'webp') {
        wp_send_json(['success'=>false,'message'=> sprintf('Color %d mockup image must be a .webp file.', $i+1)]);
      }
    }
    $colors[] = [
      'name'     => $name !== '' ? $name : sprintf('Color %d', $i+1),
      'image_id' => $img,
    ];
  }

  $opts = [];
  $image_map = [];
  foreach ($colors as $c) {
    $name = $c['name'];
    if ($name === '') continue;
    if (!isset($image_map[$name])) {
      $opts[] = $name;
      $image_map[$name] = $c['image_id'];
    }
  }

  return [
    'count'       => $count,
    'colors'      => $colors,
    'options'     => $opts,
    'image_map'   => $image_map,
    'has_colors'  => $count > 0 && !empty($opts),
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
    $name = isset($row['name']) ? sanitize_text_field( wp_unslash($row['name']) ) : '';
    $item = isset($row['item']) ? sanitize_text_field( wp_unslash($row['item']) ) : '';
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

function bumblebee_invalid_art_urls(array $art_urls): array {
  $allowed = ['png','psd'];
  $bad = [];
  foreach ($art_urls as $art) {
    if (!is_array($art) || empty($art['url'])) continue;
    $url  = isset($art['url']) ? (string) $art['url'] : '';
    $slug = isset($art['slug']) ? (string) $art['slug'] : '';
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

  $tax        = sanitize_text_field( wp_unslash( isset($_POST['tax_status']) ? $_POST['tax_status'] : 'taxable' ) );
  $vector_id  = isset($_POST['vector_id']) ? absint($_POST['vector_id']) : 0;
  $vector_url = esc_url_raw( wp_unslash( isset($_POST['vector_url']) ? $_POST['vector_url'] : '' ) );

  $color_data = bumblebee_parse_colors( isset($_POST['color_data']) ? wp_unslash($_POST['color_data']) : null );
  $image_id = 0;
  if (!empty($color_data['colors'][0]['image_id'])) {
    $image_id = absint($color_data['colors'][0]['image_id']);
  }
  $sizes  = sanitize_text_field( wp_unslash(bumblebee_required_field('sizes','Sizes')) );
  $vendor_data = bumblebee_parse_vendors( isset($_POST['vendor_data']) ? wp_unslash($_POST['vendor_data']) : null );
  $prod   = sanitize_text_field( wp_unslash(bumblebee_required_field('production','Production')) );
  $print_raw = sanitize_text_field( wp_unslash(bumblebee_required_field('print_location','Print Location')) );
  $special = isset($_POST['special_instructions']) ? sanitize_textarea_field( wp_unslash($_POST['special_instructions']) ) : '';
  $art_urls = bumblebee_art_urls_from_post();
  $turbo_rip_url = isset($_POST['turbo_rip_url']) ? esc_url_raw( wp_unslash($_POST['turbo_rip_url']) ) : '';
  $turbo_rip_id = isset($_POST['turbo_rip_id']) ? absint($_POST['turbo_rip_id']) : 0;
  $embroidery_file_url = isset($_POST['embroidery_file_url']) ? esc_url_raw( wp_unslash($_POST['embroidery_file_url']) ) : '';
  $embroidery_file_id = isset($_POST['embroidery_file_id']) ? absint($_POST['embroidery_file_id']) : 0;

  $invalid_art = bumblebee_invalid_art_urls($art_urls);
  if (!empty($invalid_art)) {
    $msg = 'Original Art must be a PNG or PSD. Please update: ' . implode(', ', $invalid_art) . '.';
    wp_send_json(['success'=>false,'message'=>$msg]);
  }

  if ($turbo_rip_url !== '') {
    $ext = bumblebee_extension_from_url($turbo_rip_url);
    if ($ext !== 'trd') {
      wp_send_json(['success'=>false,'message'=>'Turbo RIP files must have a .trd extension.']);
    }
  }

  $to_opts = function($csv){
    $out=[]; foreach(array_map('trim', explode(',', (string)$csv)) as $v){ if($v!=='') $out[]=$v; }
    return array_values(array_unique($out));
  };

  return [
    'title'        => $title,
    'price'        => $price,
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
    'turbo_rip_url' => $turbo_rip_url,
    'turbo_rip_id'  => $turbo_rip_id,
    'embroidery_file_url' => $embroidery_file_url,
    'embroidery_file_id'  => $embroidery_file_id,
    'color_count'  => $color_data['count'],
    'color_entries'=> $color_data['colors'],
    'color_opts'   => $color_data['options'],
    'color_image_map' => $color_data['image_map'],
    'has_colors'   => $color_data['has_colors'],
    'size_opts'    => $to_opts($sizes),
    'locations'    => $to_opts($print_raw),
    'company_name' => get_bloginfo('name'),
    'site_slug'    => function_exists('bumblebee_site_slug_from_subdomain') ? bumblebee_site_slug_from_subdomain() : 'site',
    'image_url'    => isset($_POST['image_url']) ? esc_url_raw(wp_unslash($_POST['image_url'])) : '',
  ];
}
