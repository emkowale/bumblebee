<?php
if (!defined('ABSPATH')) exit;

/* Site basics */
function bee_site_title(){ return (string) get_bloginfo('name'); }

/* Slug from WordPress Address (URL)
 * - exampleslug.com                  => exampleslug
 * - exampleslug.thebeartraxs.com     => exampleslug
 * - thebeartraxs.com                 => fallback to site title slug (avoid platform name)
 */
function bee_calc_site_slug($home){
  $host = parse_url($home, PHP_URL_HOST); if(!$host) return sanitize_title(bee_site_title());
  $host = strtolower($host);
  if (substr($host, -18) === '.thebeartraxs.com') return sanitize_title(explode('.', $host)[0]);
  if ($host === 'thebeartraxs.com') return sanitize_title(bee_site_title());
  $parts = explode('.', $host);
  if (count($parts) >= 2) return sanitize_title($parts[count($parts)-2]);
  return sanitize_title(bee_site_title());
}

/* Brand = {site_slug} Merch (never the platform name) */
function bee_brand_for_site($home){
  $slug = bee_calc_site_slug($home);
  if ($slug === 'thebeartraxs' || $slug === 'beartraxs') $slug = sanitize_title(bee_site_title());
  return trim($slug).' Merch';
}

/* Ensure a brand taxonomy exists and is visible in admin.
   Prefer WooCommerce’s 'product_brand'; else fall back to 'brands'; else register our own 'product_brand'. */
function bee_ensure_brand_taxonomy(){
  if (taxonomy_exists('product_brand')) return 'product_brand';
  if (taxonomy_exists('brands'))       return 'brands';
  register_taxonomy('product_brand','product',[
    'hierarchical'=>true,
    'labels'=>['name'=>'Brands','singular_name'=>'Brand'],
    'show_ui'=>true,'show_admin_column'=>true,'query_var'=>true,'rewrite'=>['slug'=>'brand'],
    'show_in_quick_edit'=>true
  ]);
  return 'product_brand';
}

/* Create/assign the brand term */
function bee_assign_brand($pid,$brand_name){
  $tx = bee_ensure_brand_taxonomy();
  $term = get_term_by('name',$brand_name,$tx);
  if (!$term || is_wp_error($term)) {
    $res = wp_insert_term($brand_name,$tx);
    if (!is_wp_error($res)) $term = get_term($res['term_id'],$tx);
  }
  if ($term && !is_wp_error($term)) wp_set_object_terms($pid,(int)$term->term_id,$tx,false);
}

/* Copy style options (checkboxes in Settings) */
function bee_styles(){
  return [
    'instructional'=>'Instructional / Explanatory','conversational'=>'Conversational / Chatty',
    'formal'=>'Formal / Academic','persuasive'=>'Persuasive / Sales','narrative'=>'Narrative / Storytelling',
    'poetic'=>'Poetic / Creative','journalistic'=>'Journalistic / Reportage','technical'=>'Technical / Instruction Manual',
    'humorous'=>'Humorous / Satirical','comparative'=>'Comparative / Analytical','reflective'=>'Reflective / Personal / Opinion',
    'dialogue'=>'Dialogue / Script','bullet'=>'Bullet / List / Summary','hybrid'=>'Hybrid / Mixed',
  ];
}
