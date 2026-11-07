<?php
if ( ! defined( 'ABSPATH' ) ) exit;
function bumblebee_find_orphan_attachment_ids($limit=500){
  global $wpdb;
  $ids = $wpdb->get_col($wpdb->prepare(
    "SELECT ID FROM {$wpdb->posts} p
     WHERE p.post_type='attachment' AND p.post_parent=0
       AND NOT EXISTS (SELECT 1 FROM {$wpdb->postmeta} pm WHERE pm.meta_key='_thumbnail_id' AND pm.meta_value=p.ID)
     ORDER BY p.ID DESC LIMIT %d", intval($limit)
  ));
  return array_map('intval', $ids);
}
add_action('wp_ajax_bumblebee_orphan_sweep_preview', function(){
  if(!current_user_can('manage_woocommerce')) wp_send_json_error('forbidden');
  check_ajax_referer('bb_orphan_sweep','nonce');
  $ids = bumblebee_find_orphan_attachment_ids();
  wp_send_json_success(['count'=>count($ids),'ids'=>$ids]);
});
add_action('wp_ajax_bumblebee_orphan_sweep_delete', function(){
  if(!current_user_can('manage_woocommerce')) wp_send_json_error('forbidden');
  check_ajax_referer('bb_orphan_sweep','nonce');
  $ids = isset($_POST['ids']) ? array_map('intval',(array)$_POST['ids']) : [];
  $deleted=0; foreach($ids as $id){ if(wp_delete_attachment($id,true)) $deleted++; }
  wp_send_json_success(['deleted'=>$deleted]);
});