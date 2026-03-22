<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function bumblebee_size_markup_pct(string $size): float {
  $normalized = strtoupper(preg_replace('/\s+/', '', trim($size)));
  $markup_map = [
    '2XL' => 0.06,
    '3XL' => 0.15,
    '4XL' => 0.20,
    '5XL' => 0.22,
    '6XL' => 0.25,
  ];
  return isset($markup_map[$normalized]) ? (float) $markup_map[$normalized] : 0.0;
}

function bumblebee_variation_price_for_size(float $base_price, string $size): string {
  $markup_pct = bumblebee_size_markup_pct($size);
  $adjusted_price = $base_price * (1 + $markup_pct);
  return wc_format_decimal($adjusted_price, wc_get_price_decimals());
}

function bumblebee_build_product(array $req): array {
  $product = new WC_Product_Variable();
  $product->set_name( trim($req['company_name'] . ' ' . $req['title']) );
  $product->set_status('publish');
  $product->set_catalog_visibility('visible');
  $product->set_tax_status($req['tax_status']);

  // Mockup conversion is handled earlier during request validation.
  // Do not convert legacy vector/original-art uploads here.
  $featured_id = 0;
  if ($req['image_id']) {
    $featured_id = (int) $req['image_id'];
    $product->set_image_id($featured_id);
  }
  if ($req['vector_id'] && $req['vector_url'] === '') {
    $req['vector_url'] = wp_get_attachment_url($req['vector_id']);
  }

  $product->update_meta_data('Company Name', $req['company_name']);
  $product->update_meta_data('Production', $req['production']);
  if (!empty($req['locations'])) $product->update_meta_data('Print Location', implode(', ', $req['locations']));
  else $product->delete_meta_data('Print Location');
  $product->update_meta_data('Site Slug', $req['site_slug']);
  $product->update_meta_data('Vendor Code', $req['vendor_code']);
  if (!empty($req['scrubs']) && $req['scrubs'] === 'yes') {
    $product->update_meta_data('Scrubs', 'Yes');
  }
  if (!empty($req['special_instructions'])) {
    $product->update_meta_data('Special Instructions', $req['special_instructions']);
  }

  $image_url = $featured_id ? wp_get_attachment_url($featured_id) : ($req['image_url'] ?? '');

  $has_colors = !empty($req['has_colors']);
  $scrub_attributes = !empty($req['scrub_attributes']) && is_array($req['scrub_attributes'])
    ? $req['scrub_attributes']
    : [];
  $attributes=[];
  if($has_colors && !empty($req['color_opts'])){
    $a=new WC_Product_Attribute();
    $a->set_name('Color');
    $a->set_options($req['color_opts']);
    $a->set_visible(true);
    $a->set_variation(true);
    $attributes['color']=$a;
  }
  if(!empty($req['size_opts'])){
    $a=new WC_Product_Attribute();
    $a->set_name('Size');
    $a->set_options($req['size_opts']);
    $a->set_visible(true);
    $a->set_variation(true);
    $attributes['size']=$a;
  }
  foreach ($scrub_attributes as $field_key => $scrub_attribute) {
    $label = isset($scrub_attribute['label']) ? sanitize_text_field((string) $scrub_attribute['label']) : '';
    $options = isset($scrub_attribute['attributes']) && is_array($scrub_attribute['attributes'])
      ? array_values(array_filter(array_map('sanitize_text_field', $scrub_attribute['attributes'])))
      : [];
    if ($label === '' || empty($options)) continue;

    $a = new WC_Product_Attribute();
    $a->set_name($label);
    $a->set_options($options);
    $a->set_visible(true);
    $a->set_variation(false);
    $attributes[sanitize_key((string) $field_key)] = $a;
  }
  $product->set_attributes($attributes);

  $default_attributes = [];
  if ($has_colors && is_array($req['color_opts']) && count($req['color_opts']) === 1) {
    $default_attributes['color'] = (string) array_values($req['color_opts'])[0];
  }
  if (!empty($req['size_opts']) && is_array($req['size_opts']) && count($req['size_opts']) === 1) {
    $default_attributes['size'] = (string) array_values($req['size_opts'])[0];
  }
  if (!empty($default_attributes)) {
    $product->set_default_attributes($default_attributes);
  }

  $product_id = $product->save();
  if ($product_id) {
    $parent = wc_get_product($product_id);
    if ($parent && !$parent->get_sku()) {
      $parent->set_sku(sprintf('%s-%d', $req['site_slug'], $product_id));
      $parent->save();
    }
  }

  $colors_for_variations = $has_colors ? $req['color_opts'] : [null];
  $color_image_map = is_array($req['color_image_map']) ? $req['color_image_map'] : [];
  $base_price = (float) $req['price'];

  foreach($colors_for_variations as $c){
    foreach($req['size_opts'] as $s){
      $v = new WC_Product_Variation();
      $v->set_parent_id($product_id);

      $attrs = [];
      if($has_colors) $attrs['color']=$c;
      if(!empty($req['size_opts'])) $attrs['size']=$s;
      $v->set_attributes($attrs);

      $variation_price = bumblebee_variation_price_for_size($base_price, (string) $s);
      $v->set_regular_price($variation_price);
      $v->set_price($variation_price);

      $image_id = $has_colors ? (isset($color_image_map[$c]) ? (int)$color_image_map[$c] : 0) : 0;
      if(!$image_id) $image_id = $featured_id;
      if($image_id) $v->set_image_id($image_id);

      $vid=$v->save();
      $sku=sprintf('%s-%d-%d',$req['site_slug'],$product_id,$vid);
      $v->set_sku($sku);
      $v->save();
    }
  }
  WC_Product_Variable::sync($product_id);

  bumblebee_attach_art_meta($product_id, $req['locations']);
  return ['product_id'=>$product_id,'image_url'=>$image_url ?: ''];
}
