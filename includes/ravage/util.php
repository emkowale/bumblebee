<?php
/*
 * Ravage utilities: hex, GD loader, normalize
 * Version: 1.3.1-mod
 */
if (!defined('ABSPATH')) exit;

/** Convert hex (#rrggbb or #rgb) to [r,g,b] */
function ravage_hex2rgb(string $hex): array {
  $h = ltrim($hex,'#');
  if (strlen($h)===3) $h=$h[0].$h[0].$h[1].$h[1].$h[2].$h[2];
  return [
    hexdec(substr($h,0,2)),
    hexdec(substr($h,2,2)),
    hexdec(substr($h,4,2)),
  ];
}

/** Normalize to #rrggbb */
function ravage_norm_hex(string $hex): string {
  $h = ltrim($hex,'#');
  if (strlen($h)===3) $h=$h[0].$h[0].$h[1].$h[1].$h[2].$h[2];
  return '#'.substr($h,0,6);
}

/** Load an image into GD resource */
function ravage_gd_image_create(string $path){
  $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
  if ($ext==='png')  return @imagecreatefrompng($path);
  if ($ext==='gif')  return @imagecreatefromgif($path);
  if ($ext==='webp' && function_exists('imagecreatefromwebp')) return @imagecreatefromwebp($path);
  return @imagecreatefromjpeg($path);
}
