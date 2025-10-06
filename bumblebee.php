<?php
/*
 * Plugin Name: Bumblebee
 * Description: Batch mockup generator for WooCommerce product images (powered by Ravage). This plugin replaces the WooCommerce variation generation feature.
 * Plugin URI: https://github.com/emkowale/bumblebee
 * Author: Eric Kowalewski
 * Version: 1.2.13
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author URI: https://erickowalewski.com/
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: bumblebee
 * Domain Path: /languages
 * Update URI: https://github.com/emkowale/bumblebee
 */

if (!defined('ABSPATH')) exit;

define('BUMBLEBEE_VERSION', '1.2.7');
define('BUMBLEBEE_SLUG', 'bumblebee');

require_once __DIR__ . '/includes/util/config.php';

# --- Load textdomain (optional .mo in /languages) ---
add_action('plugins_loaded', function(){
  load_plugin_textdomain('bumblebee', false, dirname(plugin_basename(__FILE__)).'/languages');
});

# --- Core Engine (Ravage) ---
require_once __DIR__ . '/includes/ravage/core.php';

# --- Admin UI + AJAX isolated here (locks admin.js/admin.css design) ---
if (is_admin()) {
  require_once __DIR__ . '/includes/admin.php';
}

require_once __DIR__ . '/includes/actions.php';


# --- Minimal dependency check (WooCommerce) ---
add_action('admin_init', function(){
  if (!class_exists('WooCommerce')) {
    add_action('admin_notices', function(){
      echo '<div class="notice notice-error"><p><strong>Bumblebee</strong> requires WooCommerce to be active.</p></div>';
    });
  }
});

# --- GitHub updater (public repo: emkowale/bumblebee) ---
add_filter('pre_set_site_transient_update_plugins', function($t){
  if (empty($t->checked)) return $t;
  $plugin = plugin_basename(__FILE__);
  $resp = wp_remote_get('https://api.github.com/repos/emkowale/bumblebee/releases/latest',
    ['headers'=>['User-Agent'=>'WordPress']]);
  if (is_wp_error($resp)) return $t;
  $rel = json_decode(wp_remote_retrieve_body($resp));
  if (!$rel || empty($rel->tag_name)) return $t;
  $new = ltrim($rel->tag_name, 'v');
  if (version_compare(BUMBLEBEE_VERSION, $new, '>=')) return $t;
  $asset = (!empty($rel->assets) && is_array($rel->assets)) ? ($rel->assets[0]->browser_download_url ?? '') : '';
  $t->response[$plugin] = (object)[
    'slug'        => 'bumblebee',
    'new_version' => $new,
    'url'         => 'https://github.com/emkowale/bumblebee',
    'package'     => $asset
  ];
  return $t;
});
