<?php
if ( ! defined( 'ABSPATH' ) ) exit;
function bumblebee_convert_and_rename_to_webp( $attachment_id, $company_name, $product_title ){
  $file = get_attached_file($attachment_id); if(!$file || !file_exists($file)) return $attachment_id;
  $base = trim($company_name.' '.$product_title.' brought to you by The Bear Traxs thebeartraxs.com');
  $editor = wp_get_image_editor($file);
  if(is_wp_error($editor)){
    $new = trailingslashit(dirname($file)).sanitize_file_name($base).'.'.pathinfo($file,PATHINFO_EXTENSION);
    @rename($file,$new); update_attached_file($attachment_id,$new);
    wp_update_post(['ID'=>$attachment_id,'post_title'=>$base,'post_excerpt'=>$base,'post_content'=>$base]);
    update_post_meta($attachment_id,'_wp_attachment_image_alt',$base);
    return $attachment_id;
  }
  $saved = $editor->save(null,'image/webp'); if(is_wp_error($saved)) return $attachment_id;
  $new = trailingslashit(dirname($file)).sanitize_file_name($base).'.webp';
  if(!@rename($saved['path'],$new)) $new = $saved['path'];
  $type = wp_check_filetype(basename($new),null);
  $aid = wp_insert_attachment(['post_mime_type'=>$type['type'],'post_title'=>$base,'post_content'=>$base,'post_excerpt'=>$base,'post_status'=>'inherit'],$new);
  require_once ABSPATH.'wp-admin/includes/image.php';
  $meta = wp_generate_attachment_metadata($aid,$new); wp_update_attachment_metadata($aid,$meta);
  update_post_meta($aid,'_wp_attachment_image_alt',$base);
  return $aid ?: $attachment_id;
}