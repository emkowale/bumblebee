<?php
/*
 * Plugin Name: Bumblebee
 * Version: 1.5.35
 * Plugin URI: https://github.com/emkowale/bumblebee
 * Description: Product builder for WooCommerce with Create a Product flow and Settings (AI toggle, Orphaned Media Sweep). Media is converted to WebP and renamed with Company Name + Product Title.
 * Author: Eric Kowalewski
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Update URI: https://github.com/emkowale/bumblebee
 * GitHub Plugin URI: emkowale/bumblebee
 */

if ( ! defined( 'ABSPATH' ) ) exit;



define('BUMBLEBEE_VERSION', '1.5.35');
define('BUMBLEBEE_PATH', plugin_dir_path(__FILE__));
define('BUMBLEBEE_URL',  plugin_dir_url(__FILE__));
define('BUMBLEBEE_SLUG', plugin_basename(__FILE__));
define('BUMBLEBEE_GITHUB_REPOSITORY', 'emkowale/bumblebee');
define('BUMBLEBEE_GITHUB_CACHE_KEY', 'bumblebee_github_release');

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


# --- GitHub updater ----------------------------------------------------------
function bumblebee_update_log($m) { if (defined('WP_DEBUG') && WP_DEBUG) error_log('[Bumblebee updater] ' . $m); }
function bumblebee_github_headers() { return array('Accept'=>'application/vnd.github+json','User-Agent'=>'Bumblebee/'.BUMBLEBEE_VERSION.'; '.home_url('/'),'X-GitHub-Api-Version'=>'2022-11-28'); }
function bumblebee_github_request($path) {
  $r=wp_remote_get('https://api.github.com/repos/'.BUMBLEBEE_GITHUB_REPOSITORY.$path,array('headers'=>bumblebee_github_headers(),'timeout'=>15,'redirection'=>3));
  if(is_wp_error($r)){bumblebee_update_log('HTTP error for '.$path.': '.$r->get_error_code().' - '.$r->get_error_message());return array('ok'=>false,'status'=>0,'error'=>$r->get_error_message());}
  $status=(int)wp_remote_retrieve_response_code($r);$h=wp_remote_retrieve_headers($r);$remaining=isset($h['x-ratelimit-remaining'])?(int)$h['x-ratelimit-remaining']:null;$reset=isset($h['x-ratelimit-reset'])?(int)$h['x-ratelimit-reset']:null;
  if($status<200||$status>=300){$body=json_decode(wp_remote_retrieve_body($r),true);$message=is_array($body)&&!empty($body['message'])?$body['message']:'Unexpected HTTP status';bumblebee_update_log('GitHub HTTP '.$status.' for '.$path.': '.$message.' (rate remaining: '.($remaining===null?'unknown':$remaining).')');return array('ok'=>false,'status'=>$status,'error'=>$message,'rate_remaining'=>$remaining,'rate_reset'=>$reset);}
  $data=json_decode(wp_remote_retrieve_body($r),true);if(!is_array($data)){bumblebee_update_log('Malformed JSON response from '.$path.' (HTTP '.$status.').');return array('ok'=>false,'status'=>$status,'error'=>'Malformed JSON response','rate_remaining'=>$remaining,'rate_reset'=>$reset);}
  return array('ok'=>true,'status'=>$status,'data'=>$data,'rate_remaining'=>$remaining,'rate_reset'=>$reset);
}
function bumblebee_release_from_response($r,$status,$remaining,$reset) {
  if(!is_array($r)||empty($r['tag_name'])||!is_string($r['tag_name'])){bumblebee_update_log('GitHub release response is missing tag_name.');return false;}$version=ltrim($r['tag_name'],'vV');
  if(!preg_match('/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/',$version)){bumblebee_update_log('GitHub release has an invalid tag: '.$r['tag_name']);return false;}$expected='bumblebee-v'.$version.'.zip';$package='';
  if(!empty($r['assets'])&&is_array($r['assets']))foreach($r['assets']as $a){if(!empty($a['name'])&&$a['name']===$expected&&!empty($a['browser_download_url'])){$package=esc_url_raw($a['browser_download_url']);break;}}
  if($package===''){bumblebee_update_log('Release '.$r['tag_name'].' is missing '.$expected.'; source ZIP fallback is disabled.');return false;}return array('version'=>$version,'tag'=>$r['tag_name'],'package'=>$package,'status'=>$status,'rate_remaining'=>$remaining,'rate_reset'=>$reset);
}
function bumblebee_get_github_release($force=false) {
  $cached=get_site_transient(BUMBLEBEE_GITHUB_CACHE_KEY);if(!$force&&is_array($cached)&&!empty($cached['success'])&&!empty($cached['release']))return $cached['release'];if(!$force&&is_array($cached)&&empty($cached['success']))return false;
  $q=bumblebee_github_request('/releases/latest');$release=$q['ok']?bumblebee_release_from_response($q['data'],$q['status'],$q['rate_remaining'],$q['rate_reset']):false;
  if(!$release){$q=bumblebee_github_request('/releases?per_page=20');if($q['ok'])foreach($q['data']as $candidate){if(empty($candidate['draft'])&&empty($candidate['prerelease'])){$release=bumblebee_release_from_response($candidate,$q['status'],$q['rate_remaining'],$q['rate_reset']);if($release)break;}}}
  if(!$release&&($tag=get_site_option('bumblebee_github_last_tag'))){$q=bumblebee_github_request('/releases/tags/'.rawurlencode($tag));if($q['ok'])$release=bumblebee_release_from_response($q['data'],$q['status'],$q['rate_remaining'],$q['rate_reset']);}
  if($release){update_site_option('bumblebee_github_last_tag',$release['tag']);set_site_transient(BUMBLEBEE_GITHUB_CACHE_KEY,array('success'=>true,'release'=>$release),6*HOUR_IN_SECONDS);return $release;}set_site_transient(BUMBLEBEE_GITHUB_CACHE_KEY,array('success'=>false),5*MINUTE_IN_SECONDS);return false;
}
function bumblebee_apply_release_to_transient($t,$force=false) {
  if(!is_object($t))return $t;if(!isset($t->response)||!is_array($t->response))$t->response=array();if(!isset($t->no_update)||!is_array($t->no_update))$t->no_update=array();unset($t->response[BUMBLEBEE_SLUG],$t->no_update[BUMBLEBEE_SLUG]);$r=bumblebee_get_github_release($force);if(!$r)return $t;
  $i=(object)array('slug'=>'bumblebee','plugin'=>BUMBLEBEE_SLUG,'new_version'=>$r['version'],'url'=>'https://github.com/'.BUMBLEBEE_GITHUB_REPOSITORY,'package'=>'');if(version_compare($r['version'],BUMBLEBEE_VERSION,'>')){$i->package=$r['package'];$t->response[BUMBLEBEE_SLUG]=$i;}else $t->no_update[BUMBLEBEE_SLUG]=$i;return $t;
}
add_filter('pre_set_site_transient_update_plugins',function($t){return bumblebee_apply_release_to_transient($t);});
add_filter('site_transient_update_plugins',function($t){return bumblebee_apply_release_to_transient($t);});
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
require_once BUMBLEBEE_PATH.'includes/drive-export.php';

if (is_admin()) {
  $settings_url = admin_url('admin.php?page=bumblebee-settings');
  $callback = function($links) use ($settings_url){
    array_unshift($links, '<a href="'.esc_url($settings_url).'">Settings</a>');
    return $links;
  };
  add_filter('plugin_action_links_' . plugin_basename(__FILE__), $callback);
  add_filter('network_admin_plugin_action_links_' . plugin_basename(__FILE__), $callback);
}
