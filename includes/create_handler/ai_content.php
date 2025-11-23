<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function bumblebee_maybe_generate_ai_content($product_id, string $vendor_code, string $image_url){
  try {
    $__bb_root = defined('BUMBLEBEE_PATH') ? BUMBLEBEE_PATH : (dirname(__DIR__) . '/');
    if ( file_exists($__bb_root.'includes/ai.php') ) {
      require_once $__bb_root.'includes/ai.php';

      $parent_title = get_the_title($product_id);
      $img_for_ai   = $image_url ?: ( ($tid = get_post_thumbnail_id($product_id)) ? wp_get_attachment_url($tid) : '' );

      $ai = BB_AI_Content::generate([
        'title'       => (string)$parent_title,
        'image_url'   => (string)$img_for_ai,
        'vendor_code' => (string)$vendor_code,
      ]);

      if (!empty($ai['ok']) && is_array($ai['data'])) {
        $desc  = $ai['data']['description'] ?? '';
        $short = $ai['data']['short_description'] ?? '';
        $tags  = $ai['data']['tags'] ?? [];

        if ($desc !== '')  wp_update_post(['ID'=>$product_id, 'post_content'=>$desc]);
        if ($short !== '') wp_update_post(['ID'=>$product_id, 'post_excerpt'=>$short]);
        if (is_array($tags) && !empty($tags)) wp_set_post_terms($product_id, $tags, 'product_tag', false);
      }
    }
  } catch (\Throwable $e) {
    // swallow; product creation must succeed
  }
}
