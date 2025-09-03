<?php
/*
 * File: includes/actions.php
 * Purpose: Backend actions for Bumblebee (UI files untouched)
 * Mode: Single-variation debug — FIRST Color × FIRST Size × FIRST Print for the selected Quality.
 * Version: 1.3.1
 */

if (!defined('ABSPATH')) exit;
require_once __DIR__ . '/ravage/core.php';

/* Bind our handler (ensure single binding) */
add_action('init', function(){
  if (function_exists('remove_all_actions')) {
    remove_all_actions('wp_ajax_bee_generate_variations');
  }
  add_action('wp_ajax_bee_generate_variations', 'bee_action_generate_variations');
});

/* === Action: Generate ONE variation + mockup for selected quality === */
function bee_action_generate_variations(){
  check_ajax_referer('bee_nonce','nonce');
  if(!current_user_can('edit_products')) wp_send_json_error('no-permission');

  $pid = absint($_POST['product_id']??0);
  $color_key  = sanitize_text_field($_POST['color_key']??'');
  $print_key  = sanitize_text_field($_POST['print_key']??'');
  $quality_key   = sanitize_text_field($_POST['quality_key']??'');
  $quality_value = sanitize_text_field($_POST['quality_value']??'');
  $art_url   = esc_url_raw($_POST['art_url']??'');
  $bases     = json_decode(stripslashes($_POST['bases']??'{}'), true) ?: [];

  if(!$pid) wp_send_json_error('no-product');

  // Persist Original Art URL (exact key requested)
  if ($art_url) update_post_meta($pid, 'orginal-art', $art_url);

  $product = wc_get_product($pid);
  if (!$product || $product->get_type()!=='variable') wp_send_json_error('must-be-variable');

  $attrs = get_post_meta($pid,'_product_attributes',true);
  if (!is_array($attrs)) $attrs = [];

  /* --- Determine the 4 attributes: color, size, print, quality --- */
  if (!$color_key || empty($attrs[$color_key]))  $color_key  = bee_find_attr_by_hints($attrs, ['color','colour','shade','hue']);
  if (!$print_key || empty($attrs[$print_key]))  $print_key  = bee_find_attr_by_hints($attrs, ['print','location','placement','front','back','sleeve','chest']);
  if (!$quality_key || empty($attrs[$quality_key])) $quality_key = bee_find_attr_by_hints($attrs, ['quality']);

  $size_key = bee_find_attr_by_hints($attrs, ['size','sizes']);

  // Validate presence
  foreach (['color_key'=>$color_key, 'print_key'=>$print_key, 'quality_key'=>$quality_key, 'size_key'=>$size_key] as $label=>$key){
    if (!$key || empty($attrs[$key])) wp_send_json_error("missing-$label");
  }

  // Tax flags
  $is_tax = [
    'color'   => !empty($attrs[$color_key]['is_taxonomy']),
    'print'   => !empty($attrs[$print_key]['is_taxonomy']),
    'quality' => !empty($attrs[$quality_key]['is_taxonomy']),
    'size'    => !empty($attrs[$size_key]['is_taxonomy']),
  ];

  // First values (taxonomy => slug; custom => raw label)
  $first_color  = bee_first_attr_value($pid, $color_key,   $is_tax['color']);   // ['value','slug','label']
  $first_print  = bee_first_attr_value($pid, $print_key,   $is_tax['print']);
  $first_size   = bee_first_attr_value($pid, $size_key,    $is_tax['size']);
  $chosen_qual  = bee_resolve_quality_value($pid, $quality_key, $is_tax['quality'], $quality_value);

  if (!$first_color || !$first_print || !$first_size || !$chosen_qual) {
    wp_send_json_error('no-terms');
  }

  // Build Woo attribute keys
  $ck = 'attribute_' . sanitize_title($color_key);
  $pk = 'attribute_' . sanitize_title($print_key);
  $sk = 'attribute_' . sanitize_title($size_key);
  $qk = 'attribute_' . sanitize_title($quality_key);

  // Find existing variation that matches ALL four; else create
  $target_vid = bee_find_matching_variation($product, [
    $ck => $first_color['value'],
    $pk => $first_print['value'],
    $sk => $first_size['value'],
    $qk => $chosen_qual['value'],
  ]);

  if (!$target_vid){
    $v = new WC_Product_Variation();
    $v->set_parent_id($pid);
    $v->set_attributes([
      $ck => $first_color['value'],
      $pk => $first_print['value'],
      $sk => $first_size['value'],
      $qk => $chosen_qual['value'],
    ]);
    $v->save();
    $target_vid = $v->get_id();
    if(!$target_vid) wp_send_json_error('variation-create-failed');
  } else {
    // Ensure attributes are fully populated on existing variation
    $v = wc_get_product($target_vid);
    if ($v) {
      $v->set_attributes([
        $ck => $first_color['value'],
        $pk => $first_print['value'],
        $sk => $first_size['value'],
        $qk => $chosen_qual['value'],
      ]);
      $v->save();
    }
  }

  /* --- Choose base image for this print location --- */
  $front_id = absint($bases['front']??0); if(!$front_id) $front_id = get_post_thumbnail_id($pid);
  $back_id  = absint($bases['back']??0);
  $base_specific = absint($bases[$first_print['slug']] ?? 0);
  $use_base = $base_specific ?: ($first_print['slug']==='back' ? $back_id : $front_id);
  if(!$use_base && $back_id) $use_base = $back_id;
  if(!$use_base) $use_base = $front_id;

  /* --- Build mockup --- */
  $art_id = $art_url ? attachment_url_to_postid($art_url) : 0;
  $hex    = bee_guess_hex($first_color['slug']); // infer from color slug

  $args = [
    'base_front_id'=>$use_base,'base_back_id'=>0,
    'print_location'=>($first_print['slug']==='back'?'back':'front'),
    'artwork_id'=>$art_id,'target_hex'=>$hex,
    'scale_pct'=>100,'offset_x'=>0,'offset_y'=>0,'rotation_deg'=>0,'canvas_px'=>500,
    'mask_opts'=>['fuzz_pct'=>10,'feather_px'=>1],
    'filename_tokens'=>[
      'productName'=>get_the_title($pid),
      'color'=>$first_color['slug'],
      'printLocation'=>$first_print['slug'],
      'quality'=>$chosen_qual['slug'],
      'wpUrl'=>home_url('/')
    ]
  ];
  $res = ravage_generate($args);
  if (!empty($res['attachment_id'])){
    update_post_meta($target_vid,'_thumbnail_id', intval($res['attachment_id']));
  }

  wp_send_json_success([
    'variation_id'=>$target_vid,
    'attrs'=>[
      'color'=>$first_color,'size'=>$first_size,'print'=>$first_print,'quality'=>$chosen_qual
    ],
    'with_image'=> !empty($res['attachment_id'])
  ]);
}

/* ================= Helpers (backend only) ================= */

function bee_find_attr_by_hints(array $attrs, array $hints){
  $best = null; $scoreBest = -1;
  foreach ($attrs as $key=>$a){
    if (empty($a['is_variation'])) continue;
    $name = strtolower($a['name'] ?? $key);
    $s=0; foreach($hints as $h){ if (strpos($name, strtolower($h))!==false) $s+=2; }
    if ($s>$scoreBest){ $best=$key; $scoreBest=$s; }
  }
  return $best;
}

/**
 * Returns ['value' => (slug or plain label), 'slug' => slug, 'label' => label]
 * - taxonomy: value = slug
 * - custom:   value = plain label
 */
function bee_first_attr_value($pid, $key, $is_tax){
  if($is_tax){
    $terms = wp_get_post_terms($pid, $key, ['fields'=>'all']);
    if(!$terms || is_wp_error($terms)) return null;
    $t = reset($terms);
    return ['value'=>$t->slug, 'slug'=>sanitize_title($t->slug), 'label'=>$t->name];
  } else {
    $attrs=get_post_meta($pid,'_product_attributes',true);
    $val=$attrs[$key]['value']??'';
    $vals = function_exists('wc_get_text_attributes') ? wc_get_text_attributes($val) : preg_split('/\s*\|\s*/',$val);
    $vals = array_values(array_filter((array)$vals));
    if (!$vals) return null;
    $label = trim($vals[0]);
    return ['value'=>$label, 'slug'=>sanitize_title($label), 'label'=>$label];
  }
}

/**
 * Resolve quality to ['value','slug','label'] from provided selection
 */
function bee_resolve_quality_value($pid, $key, $is_tax, $selected){
  $selected = sanitize_title($selected);
  if($is_tax){
    $terms = wp_get_post_terms($pid, $key, ['fields'=>'all']);
    if(!$terms || is_wp_error($terms)) return null;
    foreach ($terms as $t){
      if (sanitize_title($t->slug) === $selected) {
        return ['value'=>$t->slug, 'slug'=>sanitize_title($t->slug), 'label'=>$t->name];
      }
    }
    // fallback to first term
    $t = reset($terms);
    return ['value'=>$t->slug, 'slug'=>sanitize_title($t->slug), 'label'=>$t->name];
  } else {
    $attrs=get_post_meta($pid,'_product_attributes',true);
    $val=$attrs[$key]['value']??'';
    $vals = function_exists('wc_get_text_attributes') ? wc_get_text_attributes($val) : preg_split('/\s*\|\s*/',$val);
    $vals = array_values(array_filter((array)$vals));
    if (!$vals) return null;
    foreach ($vals as $label){
      if (sanitize_title($label) === $selected) {
        $label = trim($label);
        return ['value'=>$label,'slug'=>sanitize_title($label),'label'=>$label];
      }
    }
    // fallback to first
    $label = trim($vals[0]);
    return ['value'=>$label,'slug'=>sanitize_title($label),'label'=>$label];
  }
}

function bee_find_matching_variation(WC_Product_Variable $product, array $wanted){
  foreach($product->get_children() as $vid){
    $va = wc_get_product($vid); if(!$va) continue;
    $atts = $va->get_attributes();
    $ok = true;
    foreach ($wanted as $k=>$v){
      if (!isset($atts[$k]) || $atts[$k] !== $v){ $ok=false; break; }
    }
    if ($ok) return $vid;
  }
  return 0;
}

function bee_guess_hex($slug){
  if(preg_match('/^#?[0-9a-f]{6}$/i',$slug)) return (strpos($slug,'#')===0?$slug:'#'.$slug);
  $map=['white'=>'#ffffff','black'=>'#000000','red'=>'#c8102e','navy'=>'#003366','royal'=>'#0052cc','purple'=>'#4b2e83','gold'=>'#ffc20e','kelly'=>'#009e49','charcoal'=>'#36454f','heather'=>'#a7a8aa','aquatic'=>'#1fa2a6','aqua'=>'#00ffff','blue'=>'#0046ff','grey'=>'#808080','gray'=>'#808080'];
  foreach($map as $k=>$h){ if(stripos($slug,$k)!==false) return $h; } return '#888888';
}
