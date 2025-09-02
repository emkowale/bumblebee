<?php
/*
 * File: includes/ravage/core.php
 * Description: Ravage core facade for Bumblebee (variation mockup generation).
 * Version: 1.2.8
 */

if (!defined('ABSPATH')) exit;

/* ========= Public API ========= */
function ravage_generate(array $args): array {
  $front = absint($args['base_front_id'] ?? 0);
  $back  = absint($args['base_back_id']  ?? 0);
  $loc   = ($args['print_location'] ?? 'front') === 'back' ? 'back' : 'front';
  $base  = $loc==='back' ? ($back ?: $front) : ($front ?: $back);
  if (!$base) return [];

  $art_id= absint($args['artwork_id'] ?? 0);
  $hex   = sanitize_text_field($args['target_hex'] ?? '#cccccc');
  $scale = max(1, min(400, intval($args['scale_pct'] ?? 100)));
  $ox    = intval($args['offset_x'] ?? 0);
  $oy    = intval($args['offset_y'] ?? 0);
  $rot   = intval($args['rotation_deg'] ?? 0);
  $canvas= max(64, min(4000, intval($args['canvas_px'] ?? 500)));

  // Build filename + meta text
  $stem   = ravage_build_stem($args['filename_tokens'] ?? []);
  $nameW  = $stem . '.webp';   // target extension (WEBP preferred)
  $meta   = ravage_meta_text_from_stem($stem);

  $base_path = get_attached_file($base);
  $tmp = wp_tempnam('ravage'); if(!$tmp) return [];

  /* ---- Imagick path (preferred; WEBP) ---- */
  if (class_exists('Imagick') && file_exists($base_path)) {
    try {
      $img = new Imagick($base_path); $img->setImageColorspace(Imagick::COLORSPACE_RGB);
      $w = $img->getImageWidth(); $h = $img->getImageHeight();

      // Simple tint overlay to approximate color – preserves shading
      $ov = new Imagick(); $ov->newImage($w,$h,new ImagickPixel(ravage_norm_hex($hex)),'png');
      $ov->setImageAlpha(0.35); $img->compositeImage($ov, Imagick::COMPOSITE_OVER, 0, 0); $ov->destroy();

      // Optional artwork overlay
      if ($art_id) {
        $ap = get_attached_file($art_id);
        if ($ap && file_exists($ap)) {
          $art = new Imagick($ap);
          if ($rot) $art->rotateImage(new ImagickPixel('none'), $rot);
          $aw = max(1, (int) round(($w * $scale) / 100));
          $art->resizeImage($aw, 0, Imagick::FILTER_LANCZOS, 1, true);
          $img->compositeImage($art, Imagick::COMPOSITE_OVER, $ox, $oy);
          $art->destroy();
        }
      }

      // Square canvas export → WEBP
      $img->thumbnailImage($canvas,$canvas,true);
      $img->extentImage($canvas,$canvas,0,0);
      $img->setImageFormat('webp');
      $img->setImageCompressionQuality(85);
      $img->writeImage($tmp);
      $img->destroy();

      return ravage_attach($tmp, $nameW, $meta);
    } catch (Throwable $e) { /* fall through to GD */ }
  }

  /* ---- GD fallback (tries WEBP, else PNG) ---- */
  $im = imagecreatetruecolor($canvas,$canvas);
  $rgb = ravage_hex2rgb($hex); $col=imagecolorallocate($im,$rgb[0],$rgb[1],$rgb[2]);
  imagefilledrectangle($im,0,0,$canvas,$canvas,$col);

  $can_webp = function_exists('imagewebp');
  if ($can_webp) {
    $out = $tmp . '.webp';
    imagewebp($im, $out, 85);
    imagedestroy($im);
    return ravage_attach($out, $nameW, $meta);
  } else {
    $out = $tmp . '.png';
    imagepng($im, $out);
    imagedestroy($im);
    $nameP = $stem . '.png';
    return ravage_attach($out, $nameP, $meta);
  }
}

function ravage_preview(array $args): array {
  $res = ravage_generate($args); if (empty($res['path'])) return [];
  $data = @file_get_contents($res['path']); if(!$data) return [];
  $mime = (substr($res['filename'], -5) === '.webp') ? 'image/webp' : 'image/png';
  return ['data_uri'=>'data:'.$mime.';base64,'.base64_encode($data)];
}

/* ========= Helpers ========= */
function ravage_build_stem(array $t): string {
  // Assemble tokens, skipping empties
  $parts = [];
  $parts[] = (string)($t['productName'] ?? '');
  $parts[] = (string)($t['color'] ?? '');
  $parts[] = (string)($t['printLocation'] ?? '');
  if (!empty($t['quality']))      $parts[] = (string)$t['quality'];
  if (!empty($t['wpUrl'])) {
    $u = parse_url($t['wpUrl']);
    $parts[] = (($u['host'] ?? '').($u['path'] ?? ''));
  }
  $base = implode('_', array_values(array_filter($parts, function($p){ return $p!=='' && $p!==null; })));
  // Slug-sanitize for filesystem safety
  $s = strtolower(trim(preg_replace('/[^a-z0-9]+/i','-', $base), '-'));
  return $s ?: 'bumblebee';
}

function ravage_meta_text_from_stem(string $stem): string {
  // Human-readable enough; requirement: all four fields use the SAME text
  return $stem;
}

function ravage_attach(string $path, string $filename, string $meta_text): array {
  $file = [
    'name'     => $filename,
    'type'     => wp_check_filetype($filename)['type'] ?: (substr($filename,-5)==='.webp' ? 'image/webp' : 'image/png'),
    'tmp_name' => $path,
    'size'     => @filesize($path),
    'error'    => 0
  ];
  $up = wp_handle_sideload($file, ['test_form'=>false]); if(isset($up['error'])) return [];

  // Insert attachment with unified meta (title/caption/description/alt all the SAME)
  $att = [
    'post_mime_type' => $up['type'],
    'post_title'     => $meta_text,      // Title
    'post_content'   => $meta_text,      // Description
    'post_excerpt'   => $meta_text,      // Caption
    'post_status'    => 'inherit'
  ];
  $id = wp_insert_attachment($att, $up['file']);
  if (!$id) return [];

  // Alt text
  update_post_meta($id, '_wp_attachment_image_alt', $meta_text);

  // Generate attachment metadata (sizes, etc.)
  require_once ABSPATH.'wp-admin/includes/image.php';
  $meta = wp_generate_attachment_metadata($id, $up['file']);
  wp_update_attachment_metadata($id, $meta);

  return ['attachment_id'=>$id,'filename'=>basename($up['file']),'path'=>$up['file']];
}

function ravage_hex2rgb(string $hex): array {
  $h = ltrim($hex,'#'); if(strlen($h)===3){$h=$h[0].$h[0].$h[1].$h[1].$h[2].$h[2];}
  return [hexdec(substr($h,0,2)),hexdec(substr($h,2,2)),hexdec(substr($h,4,2))];
}
function ravage_norm_hex(string $hex): string {
  $h=ltrim($hex,'#'); if(strlen($h)===3)$h=$h[0].$h[0].$h[1].$h[1].$h[2].$h[2];
  return '#'.substr($h,0,6);
}
