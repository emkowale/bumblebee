<?php
/*
 * File: includes/admin.php
 * Purpose: Admin UI only (enqueue scripts/styles and bootstrap product attribute data)
 * NOTE: No AJAX handlers or overlapping helpers here — backend logic lives in includes/actions.php
 * Version: 1.2.8-ui
 */

if (!defined('ABSPATH')) exit;

/* ===== Admin assets & data (UI only) ===== */
add_action('admin_enqueue_scripts', function($hook){
  if ($hook!=='post.php' && $hook!=='post-new.php') return;
  $screen = get_current_screen(); if (!$screen || $screen->post_type!=='product') return;

  $pid   = isset($_GET['post']) ? absint($_GET['post']) : 0;
  $attrs = get_post_meta($pid, '_product_attributes', true);
  $data  = [];

  if (is_array($attrs)) {
    foreach ($attrs as $key => $a) {
      $is_tax = !empty($a['is_taxonomy']);
      $is_var = !empty($a['is_variation']);
      $vals   = $is_tax ? wp_get_post_terms($pid, $key, ['fields' => 'all']) : bee_text_vals($a['value'] ?? '');
      $data[] = [
        'key'    => $key,
        'name'   => $a['name'] ?? $key,
        'is_tax' => $is_tax,
        'is_var' => $is_var,
        'values' => bee_vals_pack($vals, $is_tax),
      ];
    }
  }

  wp_enqueue_media();
  wp_enqueue_script('bumblebee-admin', plugins_url('../assets/admin.js', __FILE__), ['jquery'], BUMBLEBEE_VERSION, true);
  wp_localize_script('bumblebee-admin', 'BEE', [
    'ajax'      => admin_url('admin-ajax.php'),
    'nonce'     => wp_create_nonce('bee_nonce'),
    'productId' => $pid,
    'attrs'     => $data,
    'v'         => BUMBLEBEE_VERSION,
  ]);
  wp_enqueue_style('bumblebee-admin', plugins_url('../assets/admin.css', __FILE__), [], BUMBLEBEE_VERSION);
});

/* ===== Small UI-side helpers only (no overlap with actions.php) ===== */
if (!function_exists('bee_text_vals')) {
  function bee_text_vals($v){
    if (!$v) return [];
    if (function_exists('wc_get_text_attributes')) return wc_get_text_attributes($v);
    return preg_split('/\s*\|\s*/', $v);
  }
}

if (!function_exists('bee_vals_pack')) {
  function bee_vals_pack($vals, $is_tax){
    $out = [];
    if ($is_tax) {
      foreach ((array)$vals as $t) { $out[] = ['slug' => $t->slug, 'label' => $t->name]; }
    } else {
      foreach ((array)$vals as $t) { $out[] = ['slug' => sanitize_title($t), 'label' => trim($t)]; }
    }
    return $out;
  }
}
