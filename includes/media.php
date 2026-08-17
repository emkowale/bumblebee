<?php
if ( ! defined( 'ABSPATH' ) ) exit;

require_once __DIR__.'/media/convert.php';

function bumblebee_upload_relative_url(string $relative_path): string {
  $relative_path = ltrim(str_replace('\\', '/', trim($relative_path)), '/');
  if ($relative_path === '') return '';

  $uploads = wp_get_upload_dir();
  $baseurl = isset($uploads['baseurl']) ? (string) $uploads['baseurl'] : '';
  if ($baseurl === '') return '';

  $segments = array_map('rawurlencode', explode('/', $relative_path));
  return trailingslashit($baseurl) . implode('/', $segments);
}

function bumblebee_original_attachment_url(int $attachment_id): string {
  if ($attachment_id <= 0) return '';

  $relative = (string) get_post_meta($attachment_id, '_wp_attached_file', true);
  if ($relative !== '') {
    $url = bumblebee_upload_relative_url($relative);
    if ($url !== '') return $url;
  }

  $file = get_attached_file($attachment_id, true);
  if (!$file) return '';

  $uploads = wp_get_upload_dir();
  $basedir = isset($uploads['basedir']) ? wp_normalize_path((string) $uploads['basedir']) : '';
  $file = wp_normalize_path($file);

  if ($basedir !== '' && strpos($file, trailingslashit($basedir)) === 0) {
    $relative = ltrim(substr($file, strlen(trailingslashit($basedir))), '/');
    return bumblebee_upload_relative_url($relative);
  }

  return '';
}

function bumblebee_is_original_art_upload_request(): bool {
  $flag = isset($_REQUEST['bumblebee_original_art_upload']) ? wp_unslash($_REQUEST['bumblebee_original_art_upload']) : '';
  return (string) $flag === '1';
}

function bumblebee_original_art_upload_backup(): array {
  return isset($GLOBALS['bumblebee_original_art_upload_backup']) && is_array($GLOBALS['bumblebee_original_art_upload_backup'])
    ? $GLOBALS['bumblebee_original_art_upload_backup']
    : [];
}

add_filter('wp_handle_upload_prefilter', function($file) {
  if (!bumblebee_is_original_art_upload_request()) return $file;
  if (!is_array($file)) return $file;

  $name = isset($file['name']) ? (string) $file['name'] : '';
  $tmp_name = isset($file['tmp_name']) ? (string) $file['tmp_name'] : '';
  $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

  if ($ext !== 'png' || $tmp_name === '' || !file_exists($tmp_name)) return $file;

  $backup_path = wp_tempnam('bumblebee-oa-upload.png');
  if (!$backup_path) return $file;
  if (!@copy($tmp_name, $backup_path)) return $file;

  $GLOBALS['bumblebee_original_art_upload_backup'] = [
    'path' => $backup_path,
    'name' => $name,
    'tmp_name' => $tmp_name,
    'type' => isset($file['type']) ? (string) $file['type'] : '',
    'ext' => $ext,
  ];

  return $file;
}, 1);

add_filter('wp_handle_upload', function($upload) {
  if (!bumblebee_is_original_art_upload_request()) return $upload;
  if (!is_array($upload)) return $upload;

  $backup = bumblebee_original_art_upload_backup();
  if (empty($backup['path']) || !file_exists((string) $backup['path'])) return $upload;

  $target_file = isset($upload['file']) ? (string) $upload['file'] : '';
  $target_url = isset($upload['url']) ? (string) $upload['url'] : '';
  if ($target_file === '') return $upload;

  $target_dir = pathinfo($target_file, PATHINFO_DIRNAME);
  $target_base = pathinfo($target_file, PATHINFO_FILENAME);
  if ($target_dir === '' || $target_base === '') return $upload;

  $restored_name = wp_unique_filename($target_dir, $target_base . '.png');
  $restored_file = trailingslashit($target_dir) . $restored_name;

  if (!@copy((string) $backup['path'], $restored_file)) {
    @unlink((string) $backup['path']);
    unset($GLOBALS['bumblebee_original_art_upload_backup']);
    return $upload;
  }

  if ($target_file !== $restored_file && file_exists($target_file)) {
    wp_delete_file($target_file);
  }

  @unlink((string) $backup['path']);
  unset($GLOBALS['bumblebee_original_art_upload_backup']);

  $upload['file'] = $restored_file;
  $upload['type'] = 'image/png';
  if ($target_url !== '') {
    $upload['url'] = trailingslashit(dirname($target_url)) . basename($restored_file);
  }

  return $upload;
}, 999);
