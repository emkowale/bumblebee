<?php
/* OpenAI JSON-mode client for Bumblebee (Soundwave-style constant support) */
if (!defined('ABSPATH')) exit;

/**
 * Where the key comes from (in order):
 *  1) BEE_AI_KEY (defined in includes/util/config.php or MU-plugin/wp-config.php)
 *  2) OPENAI_API_KEY environment variable
 *  3) WP option 'bumblebee_ai_key' (legacy)
 *
 * NOTE: We intentionally DO NOT read includes/secret_key.php anymore to avoid
 *       committing/flagging that file in GitHub.
 */
function bee_ai_key(){
  if (defined('BEE_AI_KEY') && BEE_AI_KEY) return trim((string) BEE_AI_KEY);
  $env = getenv('OPENAI_API_KEY'); if ($env) return trim($env);
  return (string) get_option('bumblebee_ai_key', '');
}

/**
 * Strict JSON-mode chat call.
 * Ensures messages include the word "json" as required by OpenAI when using response_format=json_object.
 */
function bee_ai_chat_json($messages, $opts = []){
  $key = bee_ai_key(); if(!$key) return new WP_Error('no-key','No OpenAI API key');

  // Inject a system rule that explicitly mentions "json"
  array_unshift($messages, [
    'role' => 'system',
    'content' => 'You must respond with valid json only. Output a single json object and nothing else.'
  ]);

  $body = [
    'model'          => $opts['model'] ?? 'gpt-4o-mini',
    'temperature'    => isset($opts['temperature']) ? (float)$opts['temperature'] : 0.55,
    'max_tokens'     => isset($opts['max_tokens']) ? (int)$opts['max_tokens'] : 700,
    'response_format'=> ['type' => 'json_object'],
    'messages'       => $messages,
  ];

  $r = wp_remote_post('https://api.openai.com/v1/chat/completions', [
    'headers' => [
      'Authorization' => 'Bearer '.$key,
      'Content-Type'  => 'application/json'
    ],
    'body'    => wp_json_encode($body),
    'timeout' => 25,
  ]);
  if (is_wp_error($r)) return new WP_Error('http', $r->get_error_message());

  $code = (int) wp_remote_retrieve_response_code($r);
  $raw  = (string) wp_remote_retrieve_body($r);
  if ($code !== 200) return new WP_Error('http', 'status '.$code.': '.substr($raw,0,240));

  $data    = json_decode($raw, true);
  $content = $data['choices'][0]['message']['content'] ?? '';
  $json    = json_decode((string)$content, true);
  if (!is_array($json)) return new WP_Error('invalid-json','AI returned non-JSON');

  return $json;
}

/* Settings ping */
function bee_ai_test_ping(){
  $res = bee_ai_chat_json([
    ['role'=>'user','content'=>'Return this as json: {"ok": true}']
  ], ['max_tokens'=>10,'temperature'=>0]);
  return is_wp_error($res) ? $res : (!empty($res['ok']) ? true : new WP_Error('invalid','Did not return {"ok":true}'));
}
