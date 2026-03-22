<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function bumblebee_slugify_label($s){
  $s = strtolower((string)$s);
  $s = preg_replace('/[^a-z0-9]+/','_',$s);
  return trim($s,'_');
}

function bumblebee_attach_art_meta($product_id, array $loc_opts){
  foreach ($loc_opts as $loc) {
    $slug = bumblebee_slugify_label($loc);
    $url_key = 'art_' . $slug . '_url';
    $id_key  = 'art_' . $slug . '_id';
    $meta_key = 'Original Art ' . $loc;
    $meta_id_key = 'Original Art ID ' . $loc;

    $url = isset($_POST[$url_key]) ? esc_url_raw( wp_unslash($_POST[$url_key]) ) : '';
    $aid = isset($_POST[$id_key])  ? absint($_POST[$id_key]) : 0;
    $resolved_url = $url;

    if ($aid > 0 && function_exists('bumblebee_attachment_extension_from_id')) {
      $ext = bumblebee_attachment_extension_from_id($aid);
      if ($ext !== '' && $ext !== 'png') {
        $resolved_url = '';
      } else {
        $aid_url = function_exists('bumblebee_original_attachment_url') ? bumblebee_original_attachment_url($aid) : '';
        if ($aid_url) $resolved_url = esc_url_raw($aid_url);
      }
    }

    if ($resolved_url) update_post_meta($product_id, $meta_key, $resolved_url);
    else delete_post_meta($product_id, $meta_key);

    if ($aid > 0 && $resolved_url !== '') update_post_meta($product_id, $meta_id_key, $aid);
    else delete_post_meta($product_id, $meta_id_key);

    if ($aid > 0) {
      wp_update_post([
        'ID'          => $aid,
        'post_parent' => (int) $product_id,
      ]);
    }
  }

  foreach ($_POST as $k => $v) {
    if (!is_string($k)) continue;
    if (!preg_match('/^art_([a-z0-9_]+)_url$/i', $k, $m)) continue;
    $slug = strtolower($m[1]);
    $url  = trim((string) wp_unslash($v));
    if ($url === '') continue;
    $resolved_url = esc_url_raw($url);

    $human = ucwords(str_replace('_', ' ', $slug));
    $meta_key = "Original Art {$human}";
    $meta_id_key = "Original Art ID {$human}";

    $id_key = "art_{$slug}_id";
    $att_id = 0;
    if (isset($_POST[$id_key])) {
      $att_id = absint($_POST[$id_key]);
      if ($att_id > 0) {
        if (function_exists('bumblebee_attachment_extension_from_id')) {
          $ext = bumblebee_attachment_extension_from_id($att_id);
          if ($ext !== '' && $ext !== 'png') {
            $resolved_url = '';
          } else {
            $att_url = function_exists('bumblebee_original_attachment_url') ? bumblebee_original_attachment_url($att_id) : '';
            if ($att_url) $resolved_url = esc_url_raw($att_url);
          }
        }
        wp_update_post([
          'ID'          => $att_id,
          'post_parent' => $product_id,
        ]);
      }
    }
    if ($resolved_url !== '') {
      update_post_meta($product_id, $meta_key, $resolved_url);
      if ($att_id > 0) update_post_meta($product_id, $meta_id_key, $att_id);
    } else {
      delete_post_meta($product_id, $meta_key);
      delete_post_meta($product_id, $meta_id_key);
    }
  }
}
