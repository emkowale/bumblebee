<?php
if (!defined('ABSPATH')) exit;

function bb_ai_is_size_or_color(string $s): bool {
  static $sizes = ['xs','s','m','l','xl','2xl','xxl','3xl','4xl','5xl','youth','toddler','infant','small','medium','large'];
  static $colors= ['black','white','red','blue','navy','royal','green','forest','olive','charcoal','heather','maroon','gold','yellow','orange','purple','pink','sand','khaki','grey','gray','cream','natural'];
  if (in_array($s, $sizes, true) || in_array($s, $colors, true)) return true;
  if (preg_match('/heather$/', $s)) return true;
  return false;
}

function bb_ai_sanitize(array $data, string $vendor_code): array {
  $desc  = isset($data['description']) ? wp_kses_post(trim((string)$data['description'])) : '';
  $short = isset($data['short_description']) ? wp_kses_post(trim((string)$data['short_description'])) : '';
  $tags  = isset($data['tags']) && is_array($data['tags']) ? $data['tags'] : [];

  $ban = array_filter(array_map('mb_strtolower', preg_split('/[^a-z0-9]+/i', $vendor_code))) ?: [];
  $ban = array_merge($ban, ['bear traxs','bear-traxs','beartraxs','bear tracks','beartracks']);
  $filter = function($s) use ($ban){
    $s = (string)$s;
    foreach ($ban as $term) { if ($term==='') continue; $s = preg_replace('/\b'.preg_quote($term,'/').'\b/i','',$s); }
    $s = preg_replace('/\s{2,}/',' ',$s);
    return trim($s);
  };
  $desc  = $filter($desc);
  $short = $filter($short);

  $out = [];
  foreach ($tags as $t) {
    $t = mb_strtolower($filter(sanitize_text_field((string)$t)));
    if ($t==='' || bb_ai_is_size_or_color($t)) continue;
    $out[$t] = true;
  }
  $tags = array_slice(array_keys($out), 0, 18);
  if (!$tags) $tags = ['graphic','logo','everyday wear','soft feel','versatile','casual','comfort','easy care','streetwear','layering','durable','daily'];

  return ['description'=>$desc,'short_description'=>$short,'tags'=>$tags];
}
