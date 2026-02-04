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

    $url = isset($_POST[$url_key]) ? esc_url_raw( wp_unslash($_POST[$url_key]) ) : '';
    $aid = isset($_POST[$id_key])  ? absint($_POST[$id_key]) : 0;

    if ($url) update_post_meta($product_id, 'Original Art ' . $loc, $url);
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

    $human = ucwords(str_replace('_', ' ', $slug));
    update_post_meta($product_id, "Original Art {$human}", esc_url_raw($url));

    $id_key = "art_{$slug}_id";
    if (isset($_POST[$id_key])) {
      $att_id = absint($_POST[$id_key]);
      if ($att_id > 0) {
        wp_update_post([
          'ID'          => $att_id,
          'post_parent' => $product_id,
        ]);
      }
    }
  }
}
