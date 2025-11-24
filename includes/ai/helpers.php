<?php
if (!defined('ABSPATH')) exit;

function bb_ai_keys(): array {
  return array_values(array_filter([
    get_option(BEE_OPT_OPENAI_KEY_PRIMARY, ''),
    get_option(BEE_OPT_OPENAI_KEY_SECONDARY, ''),
  ]));
}

function bb_ai_build_payload(string $title, string $image_url, string $vendor_code, array $styles): array {
  $style_line = 'Preferred styles: '.implode(', ', array_map('trim', $styles));
  $system = <<<SYS
You write storefront-ready WooCommerce content using a product title, an optional product image, and a vendor code only to infer generic category (tee/hoodie/hat/etc.).
HARD RULES:
- Do NOT include vendor or brand names or vendor website text.
- Do NOT include specific size or specific color information.
- Do NOT mention "Bear Traxs" in any form.
Return STRICT JSON with keys:
  "description" (120–180 words; 2–3 compact paragraphs),
  "short_description" (40–60 words; 1 short paragraph),
  "tags" (12–18 concise, lowercase tags; no sizes, colors, brands, duplicates).
Tone: practical, benefit-led, evergreen. {$style_line}
SYS;

  $userParts = [
    ['type'=>'text','text'=> "Context JSON:\n".wp_json_encode([
      'title'       => $title,
      'vendor_code' => $vendor_code,
      'note'        => 'Use vendor_code only to guess category; never output a brand.',
    ], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)]
  ];
  if ($image_url !== '') {
    $userParts[] = ['type'=>'image_url','image_url'=>['url'=>$image_url]];
  }

  return [
    'model' => 'gpt-4o-mini',
    'response_format' => ['type'=>'json_object'],
    'messages' => [
      ['role'=>'system','content'=>$system],
      ['role'=>'user','content'=>$userParts],
    ],
    'max_tokens'  => 900,
    'temperature' => 0.6,
  ];
}

function bb_ai_extract_json($content) {
  if (is_array($content)) return $content;
  if (is_string($content)) {
    $d = json_decode($content, true);
    return is_array($d) ? $d : null;
  }
  return null;
}

function bb_ai_extract_json_from_fence(string $s) {
  if (preg_match('/```json\s*(\{.*?\})\s*```/is', $s, $m)) {
    $d = json_decode($m[1], true);
    return is_array($d) ? $d : null;
  }
  return null;
}

function bb_ai_post_json(string $url, array $body, array $headers): array {
  $res = wp_remote_post($url, [
    'timeout' => 25,
    'headers' => $headers,
    'body'    => wp_json_encode($body, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),
  ]);
  if (is_wp_error($res)) return ['ok'=>false,'error'=>$res->get_error_message(),'code'=>0];
  $code = wp_remote_retrieve_response_code($res);
  $raw  = wp_remote_retrieve_body($res);
  $json = json_decode($raw, true);
  return ($code>=200 && $code<300 && is_array($json))
    ? ['ok'=>true,'json'=>$json,'code'=>$code]
    : ['ok'=>false,'error'=>'HTTP '.$code.': '.substr($raw,0,300),'code'=>$code];
}
