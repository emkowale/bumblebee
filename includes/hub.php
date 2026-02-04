<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function bumblebee_hub_creds(): array {
  return [
    'url'    => rtrim((string) get_option(BEE_OPT_HUB_URL, 'https://thebeartraxs.com/wp-json/wc/v3'), '/'),
    'key'    => (string) get_option(BEE_OPT_HUB_KEY, ''),
    'secret' => (string) get_option(BEE_OPT_HUB_SECRET, ''),
  ];
}

function bumblebee_hub_url(string $path = ''): string {
  $creds = bumblebee_hub_creds();
  $base  = $creds['url'] ?: 'https://thebeartraxs.com/wp-json/wc/v3';
  $path  = ltrim($path, '/');
  return rtrim($base, '/') . '/' . $path;
}

function bumblebee_hub_request(string $method, string $path, $body = null, array $query = []) {
  $creds = bumblebee_hub_creds();
  if ($creds['key'] === '' || $creds['secret'] === '') {
    return new WP_Error('bb_hub_missing_creds', 'Hub consumer key/secret are not set.');
  }

  $url = bumblebee_hub_url($path);
  $url = add_query_arg(array_merge([
    'consumer_key'    => $creds['key'],
    'consumer_secret' => $creds['secret'],
  ], $query), $url);

  $args = [
    'method'      => strtoupper($method),
    'timeout'     => 20,
    'headers'     => [ 'Accept' => 'application/json' ],
    'data_format' => 'body',
  ];

  if ($body !== null) {
    $args['headers']['Content-Type'] = 'application/json';
    $args['body'] = wp_json_encode($body);
  }

  $res = wp_remote_request($url, $args);
  if (is_wp_error($res)) return $res;

  $code = wp_remote_retrieve_response_code($res);
  $raw  = wp_remote_retrieve_body($res);
  $json = json_decode($raw, true);

  if ($code < 200 || $code >= 300) {
    $msg = 'Hub request failed';
    if (is_array($json) && isset($json['message'])) $msg = $json['message'];
    return new WP_Error('bb_hub_http_'.$code, $msg, [
      'status' => $code,
      'body'   => $raw,
    ]);
  }

  return $json;
}

function bumblebee_hub_find_attribute_by_slug(string $slug) {
  $slug = trim($slug);
  if ($slug === '') return new WP_Error('bb_hub_attr_slug', 'Missing attribute slug.');
  $candidates = array_values(array_unique([$slug, str_replace('_','-',$slug), str_replace('-','_',$slug)]));

  // Try search filter first
  $max_pages = 10;
  foreach ($candidates as $cand) {
    for ($page=1; $page<=$max_pages; $page++) {
      $attrs = bumblebee_hub_request('GET', 'products/attributes', null, ['per_page'=>100, 'page'=>$page, 'search'=>$cand]);
      if (is_wp_error($attrs)) return $attrs;
      if (!is_array($attrs) || empty($attrs)) break;
      foreach ($attrs as $a) {
        if (isset($a['slug']) && in_array($a['slug'], $candidates, true)) {
          return isset($a['id']) ? (int) $a['id'] : new WP_Error('bb_hub_attr_invalid', 'Vendor attribute missing ID.');
        }
      }
      if (count($attrs) < 100) break;
    }
  }

  // Fallback: full scan
  $max_pages = 50;
  for ($page=1; $page<=$max_pages; $page++) {
    $attrs = bumblebee_hub_request('GET', 'products/attributes', null, ['per_page'=>100, 'page'=>$page]);
    if (is_wp_error($attrs)) return $attrs;
    if (!is_array($attrs) || empty($attrs)) break;
    foreach ($attrs as $a) {
      if (isset($a['slug']) && in_array($a['slug'], $candidates, true)) {
        return isset($a['id']) ? (int) $a['id'] : new WP_Error('bb_hub_attr_invalid', 'Vendor attribute missing ID.');
      }
      if (isset($a['name']) && stripos((string)$a['name'], 'vendor') !== false) {
        return isset($a['id']) ? (int) $a['id'] : new WP_Error('bb_hub_attr_invalid', 'Vendor attribute missing ID.');
      }
    }
    if (count($attrs) < 100) break;
  }

  return null;
}

function bumblebee_hub_vendor_attribute_id() {
  static $cached = null;
  if ($cached !== null) return $cached;

  $slug = 'bb_vendor';
  $found = bumblebee_hub_find_attribute_by_slug($slug);
  if (is_wp_error($found)) return $found;
  if (is_int($found) && $found > 0) { $cached = $found; return $cached; }

  $created = bumblebee_hub_request('POST', 'products/attributes', [
    'name' => 'Bumblebee Vendors',
    'slug' => $slug,
    'type' => 'select',
  ]);
  if (is_wp_error($created)) {
    $body_data = $created->get_error_data();
    $resource_id = 0;
    if (is_array($body_data) && isset($body_data['body'])) {
      $decoded = json_decode((string)$body_data['body'], true);
      if (isset($decoded['data']['resource_id'])) {
        $resource_id = (int) $decoded['data']['resource_id'];
      }
    }
    if ($resource_id > 0) { $cached = $resource_id; return $cached; }
    if ($created->get_error_message() && stripos($created->get_error_message(), 'slug') !== false) {
      // Slug already exists: find and return existing ID.
      $existing = bumblebee_hub_find_attribute_by_slug($slug);
      if (is_wp_error($existing)) return $existing;
      if (is_int($existing) && $existing > 0) { $cached = $existing; return $cached; }
    }
    return $created;
  }
  if (isset($created['id'])) { $cached = (int) $created['id']; return $cached; }
  return new WP_Error('bb_hub_attr_missing', 'Unable to create vendor attribute.');
}

function bumblebee_hub_parse_desc_code(string $desc): array {
  $code = '';
  $out_desc = $desc;
  if (strpos($desc, 'bb_code::') === 0) {
    $parts = explode("\n", $desc, 2);
    $first = isset($parts[0]) ? $parts[0] : '';
    $code = trim(str_replace('bb_code::', '', $first));
    $out_desc = isset($parts[1]) ? $parts[1] : '';
  }
  return [$code, $out_desc];
}

function bumblebee_hub_format_desc(string $code, string $desc): string {
  return 'bb_code::' . $code . "\n" . $desc;
}

function bumblebee_hub_get_vendors() {
  $attr_id = bumblebee_hub_vendor_attribute_id();
  if (is_wp_error($attr_id)) return $attr_id;
  $terms = bumblebee_hub_request('GET', 'products/attributes/'.$attr_id.'/terms', null, [
    'per_page'=>100,
    '_cb' => time(),
  ]);
  if (is_wp_error($terms)) return $terms;
  $out = [];
  if (is_array($terms)) {
    foreach ($terms as $t) {
      $meta_code = '';
      if (!empty($t['meta_data']) && is_array($t['meta_data'])) {
        foreach ($t['meta_data'] as $m) {
          if (isset($m['key']) && $m['key'] === 'bb_vendor_code') {
            $meta_code = isset($m['value']) ? (string) $m['value'] : '';
            break;
          }
        }
      }
      list($desc_code, $desc_clean) = bumblebee_hub_parse_desc_code(isset($t['description']) ? (string)$t['description'] : '');
      $code_val = $meta_code !== '' ? $meta_code : ($desc_code !== '' ? $desc_code : (isset($t['slug']) ? $t['slug'] : ''));
      $desc_val = $desc_code !== '' ? $desc_clean : (isset($t['description']) ? $t['description'] : '');
      $out[] = [
        'id'          => isset($t['id']) ? (int) $t['id'] : 0,
        'name'        => isset($t['name']) ? $t['name'] : '',
        'code'        => $code_val,
        'description' => $desc_val,
      ];
    }
  }
  return $out;
}

function bumblebee_hub_save_vendor(array $vendor) {
  $attr_id = bumblebee_hub_vendor_attribute_id();
  if (is_wp_error($attr_id)) return $attr_id;

  $code_raw = isset($vendor['code']) ? (string) $vendor['code'] : '';
  $name_raw = isset($vendor['name']) ? (string) $vendor['name'] : '';
  $slug_val = $code_raw !== '' ? $code_raw : $name_raw;
  if ($slug_val === '') $slug_val = uniqid('vendor_', true);
  $desc_raw = isset($vendor['description']) ? (string) $vendor['description'] : '';
  $desc_formatted = bumblebee_hub_format_desc($code_raw, $desc_raw);

  $payload = [
    'name'        => $name_raw,
    'slug'        => sanitize_title_with_dashes($slug_val),
    'description' => $desc_formatted,
    'meta_data'   => [
      ['key'=>'bb_vendor_code','value'=>$code_raw],
    ],
  ];
  $payload = array_filter($payload, function($v){ return $v !== null; });
  $id = isset($vendor['id']) ? absint($vendor['id']) : 0;
  // If updating, fetch current term to detect changes
  if ($id > 0) {
    $current = bumblebee_hub_request('GET', 'products/attributes/'.$attr_id.'/terms/'.$id, null, ['context'=>'edit','_cb'=>time()]);
    if (is_wp_error($current)) {
      // If not found, treat as create
      $id = 0;
    } else {
      $same = true;
      if (isset($current['name']) && $current['name'] !== $payload['name']) $same = false;
      if (isset($current['slug']) && $current['slug'] !== $payload['slug']) $same = false;
      if (isset($current['description']) && $current['description'] !== $payload['description']) $same = false;
      if (!$same) {
        // delete + recreate with new data
        $del = bumblebee_hub_request('DELETE', 'products/attributes/'.$attr_id.'/terms/'.$id, null, ['force'=>true]);
        if (is_wp_error($del)) return $del;
        $id = 0;
      }
    }
  }

  $path = 'products/attributes/'.$attr_id.'/terms' . ($id > 0 ? '/'.$id : '');
  $method = $id > 0 ? 'PUT' : 'POST';
  $res = bumblebee_hub_request($method, $path, $payload);
  if (is_wp_error($res)) {
    $body_data = $res->get_error_data();
    $resource_id = 0;
    $status_code = 0;
    if (is_array($body_data)) {
      if (isset($body_data['status'])) $status_code = (int) $body_data['status'];
      if (isset($body_data['body'])) {
        $decoded = json_decode((string)$body_data['body'], true);
        if (isset($decoded['data']['resource_id'])) {
          $resource_id = (int) $decoded['data']['resource_id'];
        }
      }
    }
    // Slug collision on create or update: if resource_id is provided, retry as an update.
    if ($resource_id > 0) {
      $res = bumblebee_hub_request('PUT', 'products/attributes/'.$attr_id.'/terms/'.$resource_id, $payload);
      if (!is_wp_error($res)) return $res;
    }
    // If still failing, attach status code to message for clarity.
    $msg = $res->get_error_message();
    if ($status_code > 0) $msg = 'HTTP '.$status_code.' - '.$msg;
    return new WP_Error($res->get_error_code(), $msg, $res->get_error_data());
  }
  // If hub echoes back different fields, force replace
  $mismatch = false;
  if (is_array($res)) {
    if (isset($res['name']) && $res['name'] !== $payload['name']) $mismatch = true;
    if (isset($res['slug']) && $res['slug'] !== $payload['slug']) $mismatch = true;
    if (isset($res['description']) && $res['description'] !== $payload['description']) $mismatch = true;
  }
  if ($mismatch) {
    $target_id = $id > 0 ? $id : (isset($res['id']) ? (int)$res['id'] : 0);
    if ($target_id > 0) {
      $del = bumblebee_hub_request('DELETE', 'products/attributes/'.$attr_id.'/terms/'.$target_id, null, ['force'=>true]);
      if (is_wp_error($del)) return $del;
    }
    $res = bumblebee_hub_request('POST', 'products/attributes/'.$attr_id.'/terms', $payload);
  }
  return $res;
}

function bumblebee_hub_delete_vendor(int $id) {
  if ($id <= 0) return new WP_Error('bb_hub_missing_id', 'Vendor ID required.');
  $attr_id = bumblebee_hub_vendor_attribute_id();
  if (is_wp_error($attr_id)) return $attr_id;
  return bumblebee_hub_request('DELETE', 'products/attributes/'.$attr_id.'/terms/' . $id, null, ['force'=>true]);
}
