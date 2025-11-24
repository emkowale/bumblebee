<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Allow SVG, PDF, AI, EPS uploads for admins in wp-admin only.
add_filter('upload_mimes', function($mimes){
  if ( ! is_admin() ) return $mimes;
  if ( ! current_user_can('upload_files') ) return $mimes;

  $out = is_array($mimes) ? $mimes : [];
  $out['svg'] = 'image/svg+xml';
  $out['pdf'] = 'application/pdf';
  $out['ai']  = 'application/postscript';
  $out['eps'] = 'application/postscript';
  return $out;
});
