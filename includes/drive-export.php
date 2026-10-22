<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function bumblebee_drive_export_per_page(): int {
  $per_page = isset($_GET['per_page']) ? (int) $_GET['per_page'] : 50;
  if ($per_page < 1) $per_page = 50;
  if ($per_page > 100) $per_page = 100;
  return $per_page;
}

function bumblebee_drive_export_product_assets(int $product_id): array {
  $product = wc_get_product($product_id);
  if (!$product instanceof WC_Product) return [];

  $site_slug = sanitize_key((string) get_post_meta($product_id, 'Site Slug', true));
  if ($site_slug === '' && function_exists('bumblebee_site_slug_from_subdomain')) {
    $site_slug = bumblebee_site_slug_from_subdomain();
  }
  if ($site_slug === '') return [];

  $product_title = trim((string) $product->get_name());
  if ($product_title === '') return [];

  $assets = [];
  $seen_urls = [];

  $image_ids = [];
  $featured_id = (int) $product->get_image_id();
  if ($featured_id > 0) $image_ids[] = $featured_id;

  if ($product->is_type('variable')) {
    foreach ($product->get_children() as $variation_id) {
      $variation = wc_get_product($variation_id);
      if (!$variation instanceof WC_Product_Variation) continue;

      $image_id = (int) $variation->get_image_id();
      if ($image_id > 0) $image_ids[] = $image_id;
    }
  }

  $image_ids = array_values(array_unique(array_filter(array_map('intval', $image_ids))));
  foreach ($image_ids as $image_id) {
    $url = (string) wp_get_attachment_url($image_id);
    if ($url === '' || isset($seen_urls[$url])) continue;

    $seen_urls[$url] = true;
    $assets[] = [
      'asset_key' => 'mockup|' . sha1($url),
      'kind' => 'mockup',
      'print_location' => '',
      'source_url' => esc_url_raw($url),
      'source_name' => sanitize_file_name(wp_basename((string) wp_parse_url($url, PHP_URL_PATH))),
    ];
  }

  foreach ((array) get_post_meta($product_id) as $meta_key => $values) {
    if (stripos((string) $meta_key, 'Original Art ') !== 0) continue;
    if (stripos((string) $meta_key, 'Original Art ID ') === 0) continue;

    $value = is_array($values) ? reset($values) : $values;
    $url = is_scalar($value) ? esc_url_raw((string) $value) : '';
    if ($url === '' || isset($seen_urls[$url])) continue;

    $location = trim((string) preg_replace('/^Original Art\s+/i', '', (string) $meta_key));
    $seen_urls[$url] = true;
    $assets[] = [
      'asset_key' => 'original_art|' . sha1($location . '|' . $url),
      'kind' => 'original_art',
      'print_location' => $location !== '' ? $location : 'art',
      'source_url' => $url,
      'source_name' => sanitize_file_name(wp_basename((string) wp_parse_url($url, PHP_URL_PATH))),
    ];
  }

  if (empty($assets)) return [];

  return [
    'site_slug' => $site_slug,
    'site_url' => esc_url_raw((string) home_url('/')),
    'affiliate_title' => sanitize_text_field((string) get_bloginfo('name')),
    'source_product_id' => $product_id,
    'source_product_url' => esc_url_raw((string) get_permalink($product_id)),
    'product_title' => $product_title,
    'assets' => $assets,
  ];
}

function bumblebee_drive_export_products(WP_REST_Request $request) {
  $page = max(1, (int) $request->get_param('page'));
  $per_page = max(1, min(100, (int) $request->get_param('per_page')));
  if ($per_page <= 0) $per_page = bumblebee_drive_export_per_page();

  $statuses = apply_filters('bumblebee_drive_export_post_statuses', ['publish']);
  if (!is_array($statuses) || empty($statuses)) $statuses = ['publish'];

  $query = new WP_Query([
    'post_type' => 'product',
    'post_status' => array_values(array_unique(array_map('sanitize_key', $statuses))),
    'posts_per_page' => $per_page,
    'paged' => $page,
    'orderby' => 'ID',
    'order' => 'ASC',
    'fields' => 'ids',
    'no_found_rows' => false,
    'meta_query' => [
      [
        'key' => 'Site Slug',
        'compare' => 'EXISTS',
      ],
    ],
  ]);

  $products = [];
  foreach ((array) $query->posts as $product_id) {
    $bundle = bumblebee_drive_export_product_assets((int) $product_id);
    if (empty($bundle)) continue;
    $products[] = $bundle;
  }

  return rest_ensure_response([
    'site' => [
      'site_slug' => function_exists('bumblebee_site_slug_from_subdomain') ? bumblebee_site_slug_from_subdomain() : '',
      'site_url' => esc_url_raw((string) home_url('/')),
      'affiliate_title' => sanitize_text_field((string) get_bloginfo('name')),
    ],
    'page' => $page,
    'per_page' => $per_page,
    'has_more' => $page < (int) $query->max_num_pages,
    'products' => $products,
  ]);
}

add_action('rest_api_init', function() {
  register_rest_route('bumblebee/v1', '/drive-products', [
    'methods' => WP_REST_Server::READABLE,
    'callback' => 'bumblebee_drive_export_products',
    'permission_callback' => '__return_true',
  ]);
});
