<?php
/*
 * Ravage core (orchestrator): garment-only tint, filename build, media attach
 * Version: 1.3.1-mod
 */
if (!defined('ABSPATH')) exit;

/** Module requires (all tiny files) */
require_once __DIR__ . '/filename.php';
require_once __DIR__ . '/io.php';
require_once __DIR__ . '/mask_imagick.php';
require_once __DIR__ . '/mask_gd.php';
require_once __DIR__ . '/util.php';

/**
 * Generate a mockup image and save to Media Library.
 * Args:
 *  - base_front_id, base_back_id, print_location
 *  - target_hex, canvas_px, filename_tokens
 *  - artwork_id (reserved for next step)
 */
function ravage_generate(array $args): array {
  $front  = absint($args['base_front_id'] ?? 0);
  $back   = absint($args['base_back_id']  ?? 0);
  $loc    = strtolower($args['print_location'] ?? 'front');
  $baseId = ($loc === 'back') ? ($back ?: $front) : ($front ?: $back);
  if (!$baseId) return [];

  $hex    = sanitize_text_field($args['target_hex'] ?? '#cccccc');
  $canvas = max(64, min(4000, intval($args['canvas_px'] ?? 500)));
  $tokens = $args['filename_tokens'] ?? [];

  // Build filename/meta
  [$stem, $filename, $mimeGuess] = ravage_build_filename($tokens);
  $metaText = $stem;

  // Resolve local path for the base garment
  $basePath = ravage_resolve_base_path($baseId);
  if (!$basePath) return [];

  // Temp path for render
  $tmp = wp_tempnam('ravage');
  if (!$tmp) return [];

  // Render with Imagick if available; else GD fallback
  $result = null;
  if (class_exists('Imagick')) {
    $result = ravage_render_tinted_imagick($basePath, $hex, $canvas, $tmp);
  }
  if (!$result) {
    $result = ravage_render_tinted_gd($basePath, $hex, $canvas, $tmp, $filename);
    if ($result && !empty($result['filename'])) {
      $filename = $result['filename']; // switch to .png if webp unsupported
    }
  }
  if (!$result || empty($result['path']) || empty($result['mime'])) return [];

  // Attach to Media Library
  return ravage_attach_media($result['path'], $filename, $metaText, $mimeGuess ?: $result['mime']);
}

/** Preview helper: base64 data URI (not stored) */
function ravage_preview(array $args): array {
  $res = ravage_generate($args);
  if (empty($res['path'])) return [];
  $data = @file_get_contents($res['path']);
  if ($data === false) return [];
  $mime = (substr($res['filename'] ?? '', -5) === '.webp') ? 'image/webp' : 'image/png';
  return ['data_uri' => 'data:'.$mime.';base64,'.base64_encode($data)];
}
