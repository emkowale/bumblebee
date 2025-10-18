<?php
if ( ! defined( 'ABSPATH' ) ) exit;
add_action('wp_ajax_bumblebee_create_product', function(){
  if(!current_user_can('manage_woocommerce')) wp_send_json(['success'=>false,'message'=>'Unauthorized']);
  if(!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'],'bb_create_product')) wp_send_json(['success'=>false,'message'=>'Bad nonce']);
  if(!class_exists('WC_Product_Variable')) wp_send_json(['success'=>false,'message'=>'WooCommerce required']);

  $required = function($key,$label){ if(!isset($_POST[$key]) || trim(wp_unslash($_POST[$key]))===''){ wp_send_json(['success'=>false,'message'=> $label.' is required']); } return $_POST[$key]; };
  $title = sanitize_text_field( wp_unslash($required('title','Title')) );
  $price = wc_clean( wp_unslash($required('price','Price')) );
  if ( !is_numeric($price) || floatval($price) <= 0 ) wp_send_json(['success'=>false,'message'=>'Price must be greater than 0']);
  $tax   = sanitize_text_field( wp_unslash( isset($_POST['tax_status']) ? $_POST['tax_status'] : 'taxable' ) );
  $image_id = absint( $required('image_id','Product Image') );
  $vector_id= absint( $required('vector_id','Original Art') );
  $vector_url= esc_url_raw( wp_unslash( isset($_POST['vector_url'])?$_POST['vector_url']:'' ) );
  $colors = sanitize_text_field( wp_unslash($required('colors','Colors')) );
  $sizes  = sanitize_text_field( wp_unslash($required('sizes','Sizes')) );
  $vendor = sanitize_text_field( wp_unslash($required('vendor_code','Vendor Code')) );
  $prod   = sanitize_text_field( wp_unslash($required('production','Production')) );
  $print  = sanitize_text_field( wp_unslash($required('print_location','Print Location')) );

  $to_opts=function($csv){ $out=[]; foreach(array_map('trim', explode(',', (string)$csv)) as $v){ if($v!=='') $out[]=$v; } return array_values(array_unique($out)); };
  $color_opts=$to_opts($colors); $size_opts=$to_opts($sizes);

  $product = new WC_Product_Variable(); $product->set_name($title); $product->set_status('publish'); $product->set_catalog_visibility('visible'); $product->set_tax_status($tax);

  $company_name = get_bloginfo('name'); $site_slug = function_exists('bumblebee_site_slug_from_subdomain')?bumblebee_site_slug_from_subdomain():'site';

  if(function_exists('bumblebee_convert_and_rename_to_webp')){
    if($image_id){ $new = bumblebee_convert_and_rename_to_webp($image_id,$company_name,$title); if($new) $product->set_image_id($new); }
    if($vector_id){ bumblebee_convert_and_rename_to_webp($vector_id,$company_name,$title); if($vector_url==='') $vector_url = wp_get_attachment_url($vector_id); }
  }

  $product->update_meta_data('Company Name',$company_name);
  $product->update_meta_data('Production',$prod);
  $product->update_meta_data('Print Location',$print);
  $product->update_meta_data('Site Slug',$site_slug);
  if($vector_url) $product->update_meta_data('Original Art',$vector_url);
  $product->update_meta_data('Vendor Code',$vendor);

  $attributes=[];
  if(!empty($color_opts)){ $a=new WC_Product_Attribute(); $a->set_name('Color'); $a->set_options($color_opts); $a->set_visible(true); $a->set_variation(true); $attributes['color']=$a; }
  if(!empty($size_opts)){ $a=new WC_Product_Attribute(); $a->set_name('Size'); $a->set_options($size_opts); $a->set_visible(true); $a->set_variation(true); $attributes['size']=$a; }
  $product->set_attributes($attributes);
  $product_id = $product->save();

  $taxonomy='product_brand';
  if(taxonomy_exists($taxonomy)){
    $brand_name = $company_name.' Merch';
    $term = term_exists($brand_name,$taxonomy);
    if(!$term){
      $slug = sanitize_title($brand_name);
      $maybe = get_term_by('slug',$slug,$taxonomy);
      if($maybe){ $term = $maybe->term_id; }
    }
    if(!$term){ $term = wp_insert_term($brand_name,$taxonomy, ['slug'=>sanitize_title($brand_name)] ); }
    if(!is_wp_error($term)){
      $term_id = is_array($term)?intval($term['term_id']):intval($term);
      wp_update_term($term_id,$taxonomy,['name'=>$brand_name]);
      wp_set_object_terms($product_id,$term_id,$taxonomy,false);
    }
  }

  foreach($color_opts as $c){ foreach($size_opts as $s){
    $v=new WC_Product_Variation(); $v->set_parent_id($product_id); $v->set_attributes(['color'=>$c,'size'=>$s]);
    $v->set_regular_price($price); $v->set_price($price);
    $vid=$v->save(); $sku=sprintf('%s-%d-%d',$site_slug,$product_id,$vid); $v->set_sku($sku); $v->save();
  } }
  WC_Product_Variable::sync($product_id);
  $edit_url = get_edit_post_link($product_id,''); wp_send_json(['success'=>true,'edit_url'=>$edit_url]);
});