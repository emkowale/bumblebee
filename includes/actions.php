<?php
/*
 * File: includes/actions.php
 * Purpose: Backend actions for Bumblebee (keeps admin UI files untouched)
 * Mode: Single-variation debug — creates/uses FIRST Color × FIRST Print Location, generates mockup, assigns to variation.
 * Version: 1.2.8
 */

if (!defined('ABSPATH')) exit;
require_once __DIR__ . '/ravage/core.php';

/* Ensure our handler is the only one bound (admin.php may have registered earlier in dev) */
add_action('init', function(){
  if (function_exists('remove_all_actions')) {
    remove_all_actions('wp_ajax_bee_generate_variations');
  }
  add_action('wp_ajax_bee_generate_variations', 'bee_action_generate_variations');
});

/* === Action: Generate ONE variation + mockup (debug) === */
function bee_action_generate_variations(){
  check_ajax_referer('bee_nonce','nonce');
  if(!current_user_can('edit_products')) wp_send_json_error('no-permission');

  $pid = absint($_POST['product_id']??0);
  $color_key = sanitize_text_field($_POST['color_key']??'');
  $print_key = sanitize_text_field($_POST['print_key']??'');
  $art_url = esc_url_raw($_POST['art_url']??'');
  $bases = json_decode(stripslashes($_POST['bases']??'{}'), true) ?: [];
  if(!$pid || !$color_key || !$print_key) wp_send_json_error('missing-params');

  // Persist Original Art URL (exact key)
  if ($art_url) update_post_meta($pid, 'orginal-art', $art_url);

  $attrs = get_post_meta($pid,'_product_attributes',true);
  if (empty($attrs[$color_key]) || empty($attrs[$print_key])) wp_send_json_error('attributes-not-on-product');

  $colors = bee_attr_values($pid,$color_key,!empty($attrs[$color_key]['is_taxonomy']));
  $prints = bee_attr_values($pid,$print_key,!empty($attrs[$print_key]['is_taxonomy']));
  if (!$colors || !$prints) wp_send_json_error('no-terms');

  $first_color = reset($colors);
  $first_print = reset($prints);
  $product = wc_get_product($pid);
  if (!$product || $product->get_type()!=='variable') wp_send_json_error('must-be-variable');

  $ck='attribute_'.sanitize_title($color_key); $pk='attribute_'.sanitize_title($print_key);
  $target_vid = 0;
  foreach($product->get_children() as $vid){
    $va = wc_get_product($vid); if(!$va) continue; $atts = $va->get_attributes();
    if(($atts[$ck]??null)===$first_color && ($atts[$pk]??null)===$first_print){ $target_vid = $vid; break; }
  }
  if(!$target_vid){
    $v = new WC_Product_Variation(); $v->set_parent_id($pid);
    $v->set_attributes([$ck=>$first_color, $pk=>$first_print]); $v->save();
    $target_vid = $v->get_id(); if(!$target_vid) wp_send_json_error('variation-create-failed');
  }

  // Base selection: specific location → front → back → product image
  $front_id = absint($bases['front']??0); if(!$front_id) $front_id = get_post_thumbnail_id($pid);
  $back_id  = absint($bases['back']??0);
  $base_specific = absint($bases[$first_print]??0);
  $use_base = $base_specific ?: ($first_print==='back' ? $back_id : $front_id);
  if(!$use_base && $back_id) $use_base = $back_id;
  if(!$use_base) $use_base = $front_id;

  $art_id = $art_url ? attachment_url_to_postid($art_url) : 0;
  $hex = bee_guess_hex($first_color);

  $args = [
    'base_front_id'=>$use_base,'base_back_id'=>0,
    'print_location'=>($first_print==='back'?'back':'front'),
    'artwork_id'=>$art_id,'target_hex'=>$hex,
    'scale_pct'=>100,'offset_x'=>0,'offset_y'=>0,'rotation_deg'=>0,'canvas_px'=>500,
    'mask_opts'=>['fuzz_pct'=>10,'feather_px'=>1],
    'filename_tokens'=>[
      'productName'=>get_the_title($pid),
      'color'=>$first_color,'printLocation'=>$first_print,'quality'=>'','wpUrl'=>home_url('/')
    ]
  ];
  $res = ravage_generate($args);
  if (!empty($res['attachment_id'])) update_post_meta($target_vid,'_thumbnail_id', intval($res['attachment_id']));

  wp_send_json_success([
    'variation_id'=>$target_vid,
    'color'=>$first_color, 'print'=>$first_print,
    'with_image'=> !empty($res['attachment_id'])
  ]);
}

/* === Local helpers (duplicated small set; keeps admin.php untouched) === */
function bee_attr_values($pid,$key,$is_tax){
  if($is_tax){ return array_map('sanitize_title', wp_get_post_terms($pid,$key,['fields'=>'slugs'])); }
  $attrs=get_post_meta($pid,'_product_attributes',true); $val=$attrs[$key]['value']??'';
  $vals = (function_exists('wc_get_text_attributes')) ? wc_get_text_attributes($val) : preg_split('/\s*\|\s*/',$val);
  return array_values(array_unique(array_map('sanitize_title', array_filter((array)$vals))));
}
function bee_guess_hex($slug){
  if(preg_match('/^#?[0-9a-f]{6}$/i',$slug)) return (strpos($slug,'#')===0?$slug:'#'.$slug);
  $map=['white'=>'#ffffff','black'=>'#000000','red'=>'#c8102e','navy'=>'#003366','royal'=>'#0052cc','purple'=>'#4b2e83','gold'=>'#ffc20e','kelly'=>'#009e49','charcoal'=>'#36454f','heather'=>'#a7a8aa'];
  foreach($map as $k=>$h){ if(stripos($slug,$k)!==false) return $h; } return '#888888';
}
    