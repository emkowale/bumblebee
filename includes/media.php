<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! function_exists( 'wp_unique_filename' ) ) {
  require_once ABSPATH . 'wp-admin/includes/file.php';
}

/**
 * Convert an attachment to WebP (when possible) and rename it to a stable,
 * human-readable pattern:
 *   "{Company Name} {Product Title} brought to you by The Bear Traxs thebeartraxs.com"
 *
 * If a file with that name already exists in the target folder, we rely on
 * wp_unique_filename() to append "-1", "-2", etc. so we never overwrite files.
 *
 * @param int    $attachment_id
 * @param string $company_name
 * @param string $product_title
 * @return int   Final attachment ID (new WebP when conversion succeeds; otherwise original)
 */
function bumblebee_convert_and_rename_to_webp( $attachment_id, $company_name, $product_title ) {

  $file = get_attached_file( $attachment_id );
  if ( ! $file || ! file_exists( $file ) ) {
    return $attachment_id;
  }

  $dir      = trailingslashit( dirname( $file ) );
  $base_raw = trim( $company_name . ' ' . $product_title . ' brought to you by The Bear Traxs thebeartraxs.com' );
  $base_slug = sanitize_file_name( $base_raw );

  // Try to convert to WebP
  $editor = wp_get_image_editor( $file );

  // If editor isn't available, safely rename original file
  if ( is_wp_error( $editor ) ) {
    $ext          = pathinfo( $file, PATHINFO_EXTENSION );
    $target_fname = wp_unique_filename( $dir, "{$base_slug}.{$ext}" );
    $target_path  = $dir . $target_fname;

    if ( @rename( $file, $target_path ) ) {
      update_attached_file( $attachment_id, $target_path );
      wp_update_post( [
        'ID'           => $attachment_id,
        'post_title'   => $base_raw,
        'post_excerpt' => $base_raw,
        'post_content' => $base_raw,
      ] );
      update_post_meta( $attachment_id, '_wp_attachment_image_alt', $base_raw );
    }

    return $attachment_id;
  }

  // Save as WebP to a temp path first
  $saved = $editor->save( null, 'image/webp' );
  if ( is_wp_error( $saved ) || empty( $saved['path'] ) || ! file_exists( $saved['path'] ) ) {
    // Fallback: safely rename original
    $ext          = pathinfo( $file, PATHINFO_EXTENSION );
    $target_fname = wp_unique_filename( $dir, "{$base_slug}.{$ext}" );
    $target_path  = $dir . $target_fname;

    if ( @rename( $file, $target_path ) ) {
      update_attached_file( $attachment_id, $target_path );
      wp_update_post( [
        'ID'           => $attachment_id,
        'post_title'   => $base_raw,
        'post_excerpt' => $base_raw,
        'post_content' => $base_raw,
      ] );
      update_post_meta( $attachment_id, '_wp_attachment_image_alt', $base_raw );
    }

    return $attachment_id;
  }

  // Move WebP to a unique, final path (adds -1, -2, ...)
  $target_fname = wp_unique_filename( $dir, "{$base_slug}.webp" );
  $target_path  = $dir . $target_fname;

  if ( $saved['path'] !== $target_path ) {
    if ( ! @rename( $saved['path'], $target_path ) ) {
      // If rename fails, keep the saved path
      $target_path  = $saved['path'];
      $target_fname = basename( $target_path );
    }
  }

  // Register new WebP as a fresh attachment
  $filetype = wp_check_filetype( $target_fname, null );
  $new_id = wp_insert_attachment( [
    'post_mime_type' => $filetype['type'] ?: 'image/webp',
    'post_title'     => $base_raw,
    'post_content'   => $base_raw,
    'post_excerpt'   => $base_raw,
    'post_status'    => 'inherit',
  ], $target_path );

  if ( is_wp_error( $new_id ) || ! $new_id ) {
    return $attachment_id;
  }

  require_once ABSPATH . 'wp-admin/includes/image.php';
  $meta = wp_generate_attachment_metadata( $new_id, $target_path );
  if ( ! is_wp_error( $meta ) && ! empty( $meta ) ) {
    wp_update_attachment_metadata( $new_id, $meta );
  }
  update_post_meta( $new_id, '_wp_attachment_image_alt', $base_raw );

  return $new_id;
}
