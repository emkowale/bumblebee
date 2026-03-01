<?php
/*
 * Plugin Name: Bumblebee
 * Version: 1.5.12
 * Plugin URI: https://github.com/emkowale/bumblebee
 * Description: Product builder for WooCommerce with Create a Product flow and Settings (AI toggle, Orphaned Media Sweep). Media is converted to WebP and renamed with Company Name + Product Title.
 * Author: Eric Kowalewski
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Update URI: https://github.com/emkowale/bumblebee
 * GitHub Plugin URI: emkowale/bumblebee
 */

if ( ! defined( 'ABSPATH' ) ) exit;



define('BUMBLEBEE_VERSION', '1.5.12');
define('BUMBLEBEE_PATH', plugin_dir_path(__FILE__));
define('BUMBLEBEE_URL',  plugin_dir_url(__FILE__));
define('BUMBLEBEE_SLUG', plugin_basename(__FILE__));

function bumblebee_site_slug_from_subdomain(){
  $url = home_url();
  $host = parse_url($url, PHP_URL_HOST);
  if(!is_string($host) || $host==='') return 'site';
  $host = preg_replace('/:\d+$/','', $host);
  $parts = explode('.', $host);
  $slug = isset($parts[0]) ? $parts[0] : '';
  $slug = preg_replace('/[^a-z0-9_]+/i','_', $slug);
  $slug = strtolower(trim($slug,'_'));
  return $slug!=='' ? $slug : 'site';
}

# --- Lightweight GitHub Updater (checks releases for emkowale/bumblebee) ---
add_filter('pre_set_site_transient_update_plugins', function($transient){
  if ( !is_object($transient) ) return $transient;
  if (!isset($transient->response) || !is_array($transient->response)) $transient->response = [];
  if (!isset($transient->no_update) || !is_array($transient->no_update)) $transient->no_update = [];

  // Always clear stale update entries first.
  unset($transient->response[BUMBLEBEE_SLUG]);

  $current = isset($transient->checked[BUMBLEBEE_SLUG]) ? (string) $transient->checked[BUMBLEBEE_SLUG] : BUMBLEBEE_VERSION;
  if ($current === '') $current = BUMBLEBEE_VERSION;
  if ( empty($transient->checked) ) return $transient;

  $api = wp_remote_get('https://api.github.com/repos/emkowale/bumblebee/releases/latest', [
    'headers' => ['User-Agent' => 'WordPress; Bumblebee Updater'],
    'timeout' => 10,
  ]);
  if (is_wp_error($api)) return $transient;
  $data = json_decode(wp_remote_retrieve_body($api), true);
  if (!is_array($data) || empty($data['tag_name'])) return $transient;
  $tag = ltrim($data['tag_name'], 'vV');
  if (version_compare($tag, $current, '<=')) {
    $obj = new stdClass();
    $obj->slug = 'bumblebee';
    $obj->plugin = BUMBLEBEE_SLUG;
    $obj->new_version = $current;
    $obj->url = 'https://github.com/emkowale/bumblebee';
    $obj->package = '';
    $transient->no_update[BUMBLEBEE_SLUG] = $obj;
    return $transient;
  }
  // Prefer an asset named like bumblebee-vX.Y.Z.zip; fall back to zipball_url
  $package = '';
  if (!empty($data['assets'])) {
    foreach ($data['assets'] as $asset) {
      if (!empty($asset['browser_download_url']) && preg_match('/bumblebee-v[0-9]+\.[0-9]+\.[0-9]+\.zip$/', $asset['browser_download_url'])) {
        $package = $asset['browser_download_url']; break;
      }
    }
  }
  if ($package==='') $package = isset($data['zipball_url']) ? $data['zipball_url'] : '';

  $obj = new stdClass();
  $obj->slug = 'bumblebee';
  $obj->plugin = BUMBLEBEE_SLUG;
  $obj->new_version = $tag;
  $obj->url = 'https://github.com/emkowale/bumblebee';
  $obj->package = $package;
  unset($transient->no_update[BUMBLEBEE_SLUG]);
  $transient->response[BUMBLEBEE_SLUG] = $obj;
  return $transient;
});

// Safety net: if a stale update row somehow persists, suppress it when versions match.
add_filter('site_transient_update_plugins', function($transient){
  if (!is_object($transient) || !isset($transient->response[BUMBLEBEE_SLUG])) return $transient;
  $item = $transient->response[BUMBLEBEE_SLUG];
  $incoming = (is_object($item) && isset($item->new_version)) ? (string) $item->new_version : '';
  if ($incoming !== '' && version_compare($incoming, BUMBLEBEE_VERSION, '<=')) {
    unset($transient->response[BUMBLEBEE_SLUG]);
  }
  return $transient;
});

add_filter('plugins_api', function($res, $action, $args){
  if ($action !== 'plugin_information' || (isset($args->slug) && $args->slug !== 'bumblebee')) return $res;
  $info = new stdClass();
  $info->name = 'Bumblebee';
  $info->slug = 'bumblebee';
  $info->version = BUMBLEBEE_VERSION;
  $info->author = '<a href="https://github.com/emkowale">Eric Kowalewski</a>';
  $info->homepage = 'https://github.com/emkowale/bumblebee';
  $info->requires = '6.0';
  $info->tested = '6.8.3';
  $info->sections = [ 'description' => 'Product builder for WooCommerce.' ];
  return $info;
}, 10, 3);

require_once BUMBLEBEE_PATH.'includes/admin.php';
require_once BUMBLEBEE_PATH.'includes/settings.php';
require_once BUMBLEBEE_PATH.'includes/settings_orphan_sweep.php';
require_once BUMBLEBEE_PATH.'includes/media.php';
require_once BUMBLEBEE_PATH.'includes/create.php';
require_once BUMBLEBEE_PATH.'includes/ai.php';
require_once BUMBLEBEE_PATH.'includes/create_handler.php';
require_once BUMBLEBEE_PATH.'includes/product_data.php';
require_once BUMBLEBEE_PATH.'includes/soundwave.php';

if (is_admin()) {
  $settings_url = admin_url('admin.php?page=bumblebee-settings');
  $callback = function($links) use ($settings_url){
    array_unshift($links, '<a href="'.esc_url($settings_url).'">Settings</a>');
    return $links;
  };
  add_filter('plugin_action_links_' . plugin_basename(__FILE__), $callback);
  add_filter('network_admin_plugin_action_links_' . plugin_basename(__FILE__), $callback);
}
