<?php
/*
 * Ravage filename builder
 * Rules:
 *  - tokens: productName, color, printLocation, quality, host(wpUrl)
 *  - inside tokens: spaces→hyphens; strip non [a-z0-9-] (host allows dot)
 *  - between tokens: underscores
 *  - default ext: .webp
 */
if (!defined('ABSPATH')) exit;

function ravage_build_filename(array $tokens): array {
  $norm = function($s, $allowDot=false){
    $s = strtolower(trim((string)$s));
    $s = preg_replace('/\s+/', '-', $s);                   // spaces -> hyphens
    $s = $allowDot ? preg_replace('/[^a-z0-9\.-]/','', $s) // host can keep dots
                   : preg_replace('/[^a-z0-9-]/','',  $s);
    return trim($s, '-');
  };

  // host from wpUrl (host only)
  $host = '';
  if (!empty($tokens['wpUrl'])) {
    $u = parse_url($tokens['wpUrl']);
    $host = $u['host'] ?? '';
  }

  $parts = [];
  if (!empty($tokens['productName']))   $parts[] = $norm($tokens['productName']);
  if (!empty($tokens['color']))         $parts[] = $norm($tokens['color']);
  if (!empty($tokens['printLocation'])) $parts[] = $norm($tokens['printLocation']);
  if (!empty($tokens['quality']))       $parts[] = $norm($tokens['quality']);
  if (!empty($host))                    $parts[] = $norm($host, true);

  $stem = implode('_', array_values(array_filter($parts, fn($p)=>$p!=='')));
  if ($stem === '') $stem = 'bumblebee';

  $filename  = $stem . '.webp';
  $mimeGuess = 'image/webp';
  return [$stem, $filename, $mimeGuess];
}
