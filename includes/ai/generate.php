<?php
if (!defined('ABSPATH')) exit;

class BB_AI_Content {
  public static function generate(array $args): array {
    if ((bool) get_option(BEE_OPT_AI_DISABLED, false)) {
      return ['ok'=>false,'data'=>null,'error'=>'AI disabled in settings'];
    }

    $title       = trim((string)($args['title'] ?? ''));
    $image_url   = trim((string)($args['image_url'] ?? ''));
    $vendor_code = trim((string)($args['vendor_code'] ?? ''));
    if ($title === '') return ['ok'=>false,'data'=>null,'error'=>'Missing title'];

    $styles = array_values(array_filter((array)get_option(BEE_OPT_COPY_STYLES, [])));
    if (!$styles) $styles = ['Friendly','Concise'];

    $payload = bb_ai_build_payload($title, $image_url, $vendor_code, $styles);
    $keys    = bb_ai_keys();
    if (!$keys) return ['ok'=>false,'data'=>null,'error'=>'No API keys configured'];

    foreach ($keys as $i => $key) {
      $res = bb_ai_post_json('https://api.openai.com/v1/chat/completions', $payload, [
        'Authorization' => 'Bearer '.$key,
        'Content-Type'  => 'application/json',
      ]);

      if (!$res['ok']) {
        $code = isset($res['code']) ? (int)$res['code'] : 0;
        if ($code === 429) continue; // fail over on rate limit
        break;
      }

      $content = $res['json']['choices'][0]['message']['content'] ?? '';
      $data    = bb_ai_extract_json($content) ?: bb_ai_extract_json_from_fence((string)$content);
      if (!is_array($data)) break;

      $clean = bb_ai_sanitize($data, $vendor_code);
      return ['ok'=>true,'data'=>$clean,'provider'=>$i===0?'primary':'secondary','error'=>null];
    }
    return ['ok'=>false,'data'=>null,'error'=>'AI request failed (keys exhausted or invalid response)','provider'=>null];
  }
}
