<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function bumblebee_build_product(array $req): array {
  $product = new WC_Product_Variable();
  $product->set_name( trim($req['company_name'] . ' ' . $req['title']) );
  $product->set_status('publish');
  $product->set_catalog_visibility('visible');
  $product->set_tax_status($req['tax_status']);

  $featured_id = 0;
  if (function_exists('bumblebee_convert_and_rename_to_webp')) {
    if ($req['image_id']) {
      $new = bumblebee_convert_and_rename_to_webp($req['image_id'], $req['company_name'], $req['title']);
      $featured_id = $new ?: $req['image_id'];
      $product->set_image_id($featured_id);
    }
    if ($req['vector_id']) {
      bumblebee_convert_and_rename_to_webp($req['vector_id'], $req['company_name'], $req['title']);
      if ($req['vector_url'] === '') $req['vector_url'] = wp_get_attachment_url($req['vector_id']);
    }
  } else {
    if ($req['image_id']) {
      $featured_id = (int) $req['image_id'];
      set_post_thumbnail($product->get_id(), $req['image_id']);
    }
    if ($req['vector_id'] && $req['vector_url'] === '') $req['vector_url'] = wp_get_attachment_url($req['vector_id']);
  }

  $product->update_meta_data('Company Name', $req['company_name']);
  $product->update_meta_data('Production', $req['production']);
  $product->update_meta_data('Print Location', implode(', ', $req['locations']));
  $product->update_meta_data('Site Slug', $req['site_slug']);
  $product->update_meta_data('Vendor Code', $req['vendor_code']);
  if (!empty($req['special_instructions'])) {
    $product->update_meta_data('Special Instructions', $req['special_instructions']);
  }

  $image_url = $featured_id ? wp_get_attachment_url($featured_id) : ($req['image_url'] ?? '');
  if ($image_url) { $product->update_meta_data('Product Image URL', $image_url); }

  $has_colors = !empty($req['has_colors']);
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
  $product->set_attributes($attributes);

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

  foreach($colors_for_variations as $c){
    foreach($req['size_opts'] as $s){
      $v = new WC_Product_Variation();
      $v->set_parent_id($product_id);

      $attrs = [];
      if($has_colors) $attrs['color']=$c;
      if(!empty($req['size_opts'])) $attrs['size']=$s;
      $v->set_attributes($attrs);

      $v->set_regular_price($req['price']);
      $v->set_price($req['price']);

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
