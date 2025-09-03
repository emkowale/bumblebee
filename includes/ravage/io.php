<?php
/*
 * Ravage IO helpers: resolve base path, attach media, download temp
 * Version: 1.3.1-mod
 */
if (!defined('ABSPATH')) exit;

/** Resolve base garment path (local or remote) */
function ravage_resolve_base_path(int $attachmentId): ?string {
  $path = get_attached_file($attachmentId);
  if ($path && file_exists($path)) return $path;
  $url = wp_get_attachment_url($attachmentId);
  return $url ? ravage_download_temp($url) : null;
}

/** Attach sideloaded file into Media Library */
function ravage_attach_media(string $path, string $filename, string $metaText, string $mimeGuess=null): array {
  $file = [
    'name'     => $filename,
    'type'     => $mimeGuess ?: (substr($filename,-5)==='.webp'?'image/webp':'image/png'),
    'tmp_name' => $path,
    'size'     => @filesize($path),
    'error'    => 0,
  ];
  $up = wp_handle_sideload($file, ['test_form'=>false]);
  if (isset($up['error'])) return [];

  $att = [
    'post_mime_type' => $up['type'],
    'post_title'     => $metaText,
    'post_content'   => $metaText,
    'post_excerpt'   => $metaText,
    'post_status'    => 'inherit',
  ];
  $id = wp_insert_attachment($att, $up['file']);
  if (!$id) return [];

  update_post_meta($id,'_wp_attachment_image_alt',$metaText);
  require_once ABSPATH.'wp-admin/includes/image.php';
  $meta = wp_generate_attachment_metadata($id,$up['file']);
  wp_update_attachment_metadata($id,$meta);

  return [
    'attachment_id' => $id,
    'filename'      => basename($up['file']),
    'path'          => $up['file'],
    'mime'          => $up['type'],
  ];
}

/** Download a remote image into a temp file */
function ravage_download_temp(string $url): ?string {
  $tmp = wp_tempnam('ravage');
  if (!$tmp) return null;
  $r = wp_remote_get($url,['timeout'=>15]);
  if (is_wp_error($r)) return null;
  $b = wp_remote_retrieve_body($r);
  if ($b==='') return null;
  file_put_contents($tmp,$b);
  return $tmp;
}
