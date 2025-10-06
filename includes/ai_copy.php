<?php
/* Site-tailored ecommerce copy (strict json), guarantees site title usage */
if (!defined('ABSPATH')) exit;
require_once __DIR__.'/ai_client.php';

function bee_scrub_branding($s,$vendor,$style){
  $bans=['The Bear Traxs','Bear Traxs','BearTraxs','Bear Trax','BearTrax',$vendor,$style];
  foreach($bans as $b){ if($b) $s=preg_replace('/\b'.preg_quote($b,'/').'\b/i','',$s); }
  return trim(preg_replace('/\s{2,}/',' ',$s));
}

/* Ensure WordPress Site Title appears in copy somewhere + in tags */
function bee_inject_site_title(&$title,&$short,&$long,&$tags,$siteTitle){
  $st = trim($siteTitle); if(!$st) return;
  $present = (stripos($title,$st)!==false) || (stripos($short,$st)!==false) || (stripos($long,$st)!==false);
  if(!$present){ $short .= ($short ? ' ' : '') . "<span>🎯 Support {$st}</span>"; }
  $slug = sanitize_title($st);
  if($slug){ $tags = array_values(array_unique(array_merge((array)$tags, [$st, $slug]))); }
}

function bee_ai_build_copy_from_vendor_site($company,$slug,$vendor,$style,$vendorText,$siteText,$styles){
  $style_list = implode(', ', (array)$styles);
  $sys  = 'You are an ecommerce copywriter. Respond with valid json only.';
  $user = implode(' ', [
    'Return json with keys: title, short_html, long_html, tags (exactly 20 strings).',
    'Use ONLY factual details from vendor_text. Use site_text to tailor audience/mission & CTAs (do not invent specs).',
    'Do NOT include vendor names, brand names, domains, or item/style codes.',
    'Weave the site title into the copy naturally when helpful: "'.$company.'".',
    'HTML should use tasteful emoji icons (e.g., ✅ ⚡ 🎯 🧵 💪 🔥). Title must be unique-sounding; tags generic & customer-safe.',
    'Blend styles: '.$style_list.'.',
    "vendor_text:\n".mb_substr((string)$vendorText,0,6000),
    "\nsite_text:\n".mb_substr((string)$siteText,0,2000)
  ]);

  $json = bee_ai_chat_json([
    ['role'=>'system','content'=>$sys],
    ['role'=>'user','content'=>$user],
  ], ['max_tokens'=>780,'temperature'=>0.6]);

  if (is_wp_error($json)) return $json;

  $title = bee_scrub_branding((string)($json['title']??''),      $vendor,$style);
  $short = bee_scrub_branding((string)($json['short_html']??''), $vendor,$style);
  $long  = bee_scrub_branding((string)($json['long_html']??''),  $vendor,$style);
  $tags  = array_values(array_unique(array_map('sanitize_text_field',(array)($json['tags']??[]))));

  if (!$title || !$short || !$long || count($tags) < 20)
    return new WP_Error('invalid-json','Missing keys or less than 20 tags');

  /* Guarantee the WordPress Site Title appears somewhere + becomes a tag */
  bee_inject_site_title($title,$short,$long,$tags,$company);

  return ['title'=>$title,'short_html'=>$short,'long_html'=>$long,'tags'=>array_slice($tags,0,20)];
}
