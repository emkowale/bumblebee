<?php
if (!defined('ABSPATH')) exit;
require_once __DIR__.'/helpers.php';
require_once __DIR__.'/vendors.php';
require_once __DIR__.'/ai_client.php';
require_once __DIR__.'/ai_copy.php';

add_action('wp_ajax_bee_create_product','bee_action_create_product');
add_action('wp_ajax_bee_test_ai','bee_action_test_ai');

function bee_action_create_product(){
  check_ajax_referer('bee_nonce','nonce'); if(!current_user_can('manage_woocommerce')) wp_send_json_error('no-permission');
  $styles=(array)get_option('bumblebee_styles',[]); if(!bee_ai_key()||count($styles)<1) wp_send_json_error('ai-settings-required');

  $company=bee_site_title(); $home=home_url('/'); $slug=bee_calc_site_slug($home);
  $price=floatval($_POST['price']??0); $tax=(($_POST['taxable']??'yes')==='yes');
  $img_url=esc_url_raw($_POST['image_url']??''); $img_id=absint($_POST['image_id']??0);
  $art=esc_url_raw($_POST['art_url']??''); $colors=bee_csv($_POST['colors']??''); $sizes=bee_csv($_POST['sizes']??'');
  $prints=bee_csv($_POST['prints']??''); $quality=sanitize_text_field($_POST['quality']??''); $override=esc_url_raw($_POST['vendor_url']??'');
  if(!$price||!$img_url||!$art||!$colors||!$sizes||!$prints||!$quality) wp_send_json_error('missing-required-fields');

  $q=bee_parse_quality($quality); if(!$q['vendor']||!$q['style']) wp_send_json_error('quality-format-invalid');
  $vf=bee_fetch_vendor($q['vendor'],$q['style'],$override);
  if(!$vf['text']) wp_send_json_error(['code'=>'vendor-page-unavailable','guess_url'=>$vf['url']]);
  $siteText=bee_fetch_site_text($home);

  global $wpdb; $hint=(int)$wpdb->get_var($wpdb->prepare(
    "SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA=%s AND TABLE_NAME=%s",$wpdb->dbname,$wpdb->prefix.'posts'
  ));

  $copy=bee_ai_build_copy_from_vendor_site($company,$slug,$q['vendor'],$q['style'],$vf['text'],$siteText,$styles);
  if(is_wp_error($copy)) wp_send_json_error('ai: '.$copy->get_error_message());

  // Build title: "Site Title – [Women’s/Men’s/Unisex ]ItemType[ – Color (if exactly one)]"
  $type=bee_infer_type_from_text($vf['text']);
  if($type==='Product' && preg_match('/\b(xs|s|m|l|xl|2xl|3xl|4xl)\b/i',implode(',',$sizes))) $type='Apparel';
  $gender=bee_detect_gender($vf['text']); $phrase=trim(($gender?($gender.' '):'').$type);
  $colorSuffix=(count($colors)===1)?(' – '.trim($colors[0])):'';
  $finalTitle=bee_unique_title($company.' – '.$phrase.$colorSuffix);

  $pid=wp_insert_post(['post_title'=>$finalTitle,'post_content'=>$copy['long_html'],'post_excerpt'=>$copy['short_html'],'post_status'=>'publish','post_type'=>'product'],true);
  if(is_wp_error($pid)) wp_send_json_error('parent-create-failed');

  wp_set_object_terms($pid,'variable','product_type');
  update_post_meta($pid,'_tax_status',$tax?'taxable':'none');
  if($img_id) set_post_thumbnail($pid,$img_id); elseif($img_url && ($sid=bee_sideload($img_url,$pid))) set_post_thumbnail($pid,$sid);
  if($art) update_post_meta($pid,'original-art',$art);
  update_post_meta($pid,'_sku',$slug.'-'.($hint?:$pid));

  /* Brand: ALWAYS {site_slug} Merch on a proper Brand taxonomy */
  $brand = bee_brand_for_site($home);
  bee_assign_brand($pid, $brand);   // uses helper to ensure taxonomy + term

  /* Category: leave your existing category logic as-is */
  bee_assign_default_cat($pid);

  /* Attributes */
  $attr=[
    'color'=>['name'=>'Color','value'=>implode(' | ',$colors),'position'=>0,'is_visible'=>1,'is_variation'=>1,'is_taxonomy'=>0],
    'size' =>['name'=>'Size','value'=>implode(' | ',$sizes),'position'=>1,'is_visible'=>1,'is_variation'=>1,'is_taxonomy'=>0],
    'print_location'=>['name'=>'Print Location','value'=>implode(' | ',$prints),'position'=>2,'is_visible'=>1,'is_variation'=>0,'is_taxonomy'=>0],
    'quality'=>['name'=>'Quality','value'=>$quality,'position'=>3,'is_visible'=>0,'is_variation'=>0,'is_taxonomy'=>0],
  ];
  update_post_meta($pid,'_product_attributes',$attr);

  /* Variations + SKUs */
  $count=0; foreach($colors as $c){ foreach($sizes as $s){
    $v=new WC_Product_Variation(); $v->set_parent_id($pid);
    $v->set_attributes(['attribute_'.sanitize_title('Color')=>sanitize_title($c),'attribute_'.sanitize_title('Size')=>sanitize_title($s)]);
    $v->set_regular_price($price); $v->set_tax_status($tax?'taxable':'none'); $v->save();
    $vid=$v->get_id(); if($vid){ $v->set_sku($slug.'-'.($hint?:$pid).'-'.$vid); $v->save(); $count++; }
  }}
  wp_set_object_terms($pid,$copy['tags'],'product_tag',false);

  wp_send_json_success(['product_id'=>$pid,'variation_count'=>$count,'edit_url'=>admin_url('post.php?post='.$pid.'&action=edit')]);
}

function bee_action_test_ai(){
  check_ajax_referer('bee_nonce','nonce');
  if(!current_user_can('manage_woocommerce')) wp_send_json_error('no-permission');
  $ok=bee_ai_test_ping(); if(is_wp_error($ok)) wp_send_json_error('ai: '.$ok->get_error_message());
  wp_send_json_success('OpenAI OK');
}

/* Utilities kept local to keep files small */
function bee_csv($s){$a=array_filter(array_map('trim',preg_split('/\s*,\s*/',(string)$s)));return array_values(array_unique($a));}
function bee_sideload($u,$p){require_once ABSPATH.'wp-admin/includes/file.php';require_once ABSPATH.'wp-admin/includes/media.php';require_once ABSPATH.'wp-admin/includes/image.php';$t=download_url($u);if(is_wp_error($t))return 0;$f=['name'=>basename(parse_url($u,PHP_URL_PATH)),'tmp_name'=>$t];$id=media_handle_sideload($f,$p);if(is_wp_error($id)){@unlink($f['tmp_name']);return 0;}return $id;}
function bee_unique_title($b){global $wpdb;$t=$b;$i=1;while($wpdb->get_var($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_type='product' AND post_status!='trash' AND post_title=%s LIMIT 1",$t))){$i++;$t=$b.' #'.$i;if($i>99)break;}return $t;}
function bee_assign_default_cat($pid){$c=get_terms(['taxonomy'=>'product_cat','hide_empty'=>false]);if(is_array($c)&&count($c)===1){wp_set_object_terms($pid,$c[0]->term_id,'product_cat');return;}$u=get_term_by('slug','uncategorized','product_cat');if($u)wp_set_object_terms($pid,$u->term_id,'product_cat');}

/* Gender detector for title phrasing */
function bee_detect_gender($t){$s=strtolower($t); if(preg_match('/\b(women|ladies|women\'?s|misses)\b/',$s)) return "Women’s"; if(preg_match('/\b(men|mens|men\'?s)\b/',$s)) return "Men’s"; if(str_contains($s,'unisex')) return 'Unisex'; return '';}
