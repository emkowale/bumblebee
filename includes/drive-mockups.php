<?php
if (!defined('ABSPATH')) exit;

/** Drive is the source; hidden attachment records keep WooCommerce image IDs compatible. */
function bumblebee_mockup_source(): string {
  return get_user_meta(get_current_user_id(), 'bumblebee_mockup_source', true) === 'drive' ? 'drive' : 'media';
}

function bumblebee_drive_file_reference(string $value) {
  $value = trim($value);
  $id = $value;
  $resource_key = '';
  if (strpos($value, '://') !== false) {
    $parts = wp_parse_url($value);
    if (!$parts || ($parts['scheme'] ?? '') !== 'https' || !in_array(strtolower($parts['host'] ?? ''), ['drive.google.com', 'docs.google.com'], true)) {
      return new WP_Error('bb_drive_link', 'Enter a Google Drive file link or file ID.');
    }
    parse_str($parts['query'] ?? '', $query);
    $id = isset($query['id']) && is_string($query['id']) ? $query['id'] : '';
    if (preg_match('~/file/d/([A-Za-z0-9_-]+)~', $parts['path'] ?? '', $match)) $id = $match[1];
    $resource_key = isset($query['resourcekey']) && is_string($query['resourcekey']) ? $query['resourcekey'] : '';
  }
  if (!preg_match('/^[A-Za-z0-9_-]{10,200}$/D', $id) || ($resource_key !== '' && !preg_match('/^[A-Za-z0-9_-]{1,200}$/D', $resource_key))) {
    return new WP_Error('bb_drive_link', 'Enter a valid Google Drive file link or file ID.');
  }
  return ['id' => $id, 'resource_key' => $resource_key];
}

function bumblebee_drive_service_path(): string {
  return defined('BUMBLEBEE_DRIVE_SERVICE_ACCOUNT_FILE') ? (string) BUMBLEBEE_DRIVE_SERVICE_ACCOUNT_FILE : (string) get_option('bumblebee_drive_service_account_file', '');
}

function bumblebee_drive_token() {
  $path = bumblebee_drive_service_path();
  if (!$path || !is_readable($path)) return new WP_Error('bb_drive_setup', 'Configure a readable Google Drive service account file in Bumblebee Settings to browse private files.');
  $service = json_decode((string) file_get_contents($path), true);
  if (!is_array($service) || ($service['type'] ?? '') !== 'service_account' || empty($service['client_email']) || empty($service['private_key'])) {
    return new WP_Error('bb_drive_setup', 'The Google Drive service account file is invalid.');
  }
  $key = 'bb_drive_token_' . md5($service['client_email'] . $service['private_key']);
  $token = get_transient($key);
  if ($token) return $token;
  $encode = static function($value) { return rtrim(strtr(base64_encode($value), '+/', '-_'), '='); };
  $now = time();
  $unsigned = $encode(wp_json_encode(['alg'=>'RS256','typ'=>'JWT'])) . '.' . $encode(wp_json_encode([
    'iss'=>$service['client_email'], 'scope'=>'https://www.googleapis.com/auth/drive.readonly',
    'aud'=>'https://oauth2.googleapis.com/token', 'iat'=>$now, 'exp'=>$now+3600,
  ]));
  $signature = '';
  if (!@openssl_sign($unsigned, $signature, $service['private_key'], OPENSSL_ALGO_SHA256)) return new WP_Error('bb_drive_auth', 'Could not authenticate the Drive connection.');
  $response = wp_remote_post('https://oauth2.googleapis.com/token', [
    'timeout'=>20, 'body'=>['grant_type'=>'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion'=>$unsigned.'.'.$encode($signature)],
  ]);
  if (is_wp_error($response)) return $response;
  $body = json_decode(wp_remote_retrieve_body($response), true);
  if (200 !== (int) wp_remote_retrieve_response_code($response) || empty($body['access_token'])) return new WP_Error('bb_drive_auth', 'Google Drive authentication failed. Check the service account configuration.');
  set_transient($key, $body['access_token'], 50 * MINUTE_IN_SECONDS);
  return $body['access_token'];
}

function bumblebee_drive_api(string $path, array $query = []) {
  $token = bumblebee_drive_token();
  if (is_wp_error($token)) return $token;
  $response = wp_remote_get(add_query_arg($query, 'https://www.googleapis.com/drive/v3/' . $path), [
    'timeout'=>20, 'headers'=>['Authorization'=>'Bearer '.$token], 'redirection'=>0,
  ]);
  if (is_wp_error($response)) return $response;
  if (200 !== (int) wp_remote_retrieve_response_code($response)) return new WP_Error('bb_drive_access', 'Drive could not open this file or folder. Check that it is shared with the service account.');
  $body = json_decode(wp_remote_retrieve_body($response), true);
  return is_array($body) ? $body : new WP_Error('bb_drive_response', 'Drive returned an invalid response.');
}

function bumblebee_drive_cache_mockup(array $ref) {
  $file_id = $ref['id'];
  $existing = get_posts(['post_type'=>'attachment', 'post_status'=>'inherit', 'numberposts'=>1, 'fields'=>'ids', 'meta_key'=>'_bb_drive_file_id', 'meta_value'=>$file_id]);
  $attachment_id = $existing ? (int) $existing[0] : 0;
  if ($attachment_id && is_file((string) get_attached_file($attachment_id)) && (int) get_post_meta($attachment_id, '_bb_drive_cached_at', true) > time() - DAY_IN_SECONDS) return $attachment_id;

  $name = 'drive-mockup-' . $file_id;
  $headers = [];
  if (bumblebee_drive_service_path() !== '') {
    $file = bumblebee_drive_api('files/'.rawurlencode($file_id), ['fields'=>'id,name,mimeType,capabilities(canDownload)', 'supportsAllDrives'=>'true']);
    if (is_wp_error($file)) return $file;
    if (!in_array($file['mimeType'] ?? '', ['image/png','image/jpeg','image/webp'], true) || empty($file['capabilities']['canDownload'])) return new WP_Error('bb_drive_type', 'Choose a downloadable PNG, JPG, or WebP image.');
    $name = $file['name'];
    $token = bumblebee_drive_token();
    if (is_wp_error($token)) return $token;
    $headers['Authorization'] = 'Bearer '.$token;
    $url = 'https://www.googleapis.com/drive/v3/files/'.rawurlencode($file_id).'?alt=media&supportsAllDrives=true';
    if (!empty($ref['resource_key'])) $headers['X-Goog-Drive-Resource-Keys'] = $file_id.'/'.$ref['resource_key'];
  } else {
    $url = add_query_arg(['id'=>$file_id, 'export'=>'download', 'resourcekey'=>$ref['resource_key']], 'https://drive.google.com/uc');
  }
  require_once ABSPATH.'wp-admin/includes/file.php';
  require_once ABSPATH.'wp-admin/includes/image.php';
  $tmp = wp_tempnam('bb-drive');
  if (!$tmp) return new WP_Error('bb_drive_cache', 'Could not create an image cache file.');
  $response = wp_safe_remote_get($url, ['timeout'=>45, 'headers'=>$headers, 'stream'=>true, 'filename'=>$tmp, 'limit_response_size'=>20 * MB_IN_BYTES + 1, 'redirection'=>empty($headers) ? 3 : 0]);
  if (is_wp_error($response)) { @unlink($tmp); return $response; }
  if (200 !== (int) wp_remote_retrieve_response_code($response) || filesize($tmp) > 20 * MB_IN_BYTES) {
    @unlink($tmp);
    return new WP_Error('bb_drive_download', 'Could not download the image (maximum 20 MB). Check its Drive sharing permissions.');
  }
  $size = wp_getimagesize($tmp);
  $mime = $size['mime'] ?? '';
  $extensions = ['image/png'=>'png', 'image/jpeg'=>'jpg', 'image/webp'=>'webp'];
  if (!isset($extensions[$mime]) || empty($size[0]) || empty($size[1]) || $size[0] * $size[1] > 40000000) {
    @unlink($tmp);
    return new WP_Error('bb_drive_image', 'Drive did not return a valid PNG, JPG, or WebP image. For public links, share the file as Anyone with the link.');
  }
  $uploads = wp_upload_dir();
  if (!empty($uploads['error'])) { @unlink($tmp); return new WP_Error('bb_drive_cache', 'The image cache directory is unavailable.'); }
  $directory = $uploads['basedir'].'/bumblebee-drive-cache';
  if (!wp_mkdir_p($directory)) { @unlink($tmp); return new WP_Error('bb_drive_cache', 'Could not create the image cache directory.'); }
  $filename = hash('sha256', $file_id).'.'.$extensions[$mime];
  $destination = $directory.'/'.$filename;
  $old_file = $attachment_id ? get_attached_file($attachment_id) : '';
  $staged = tempnam($directory, '.bb-drive-');
  if (!$staged || !copy($tmp, $staged) || !rename($staged, $destination)) {
    @unlink($tmp);
    if ($staged) @unlink($staged);
    return new WP_Error('bb_drive_cache', 'Could not save the cached image.');
  }
  @unlink($tmp);
  @chmod($destination, 0644);
  $url = $uploads['baseurl'].'/bumblebee-drive-cache/'.$filename;
  if (!$attachment_id) {
    $attachment_id = wp_insert_attachment(['post_title'=>sanitize_text_field($name), 'post_status'=>'inherit', 'post_mime_type'=>$mime, 'guid'=>$url, 'meta_input'=>['_bb_drive_file_id'=>$file_id]], $destination, 0, true);
    if (is_wp_error($attachment_id)) return $attachment_id;
  } else {
    wp_update_post(['ID'=>$attachment_id, 'post_title'=>sanitize_text_field($name), 'post_mime_type'=>$mime]);
    update_attached_file($attachment_id, $destination);
  }
  wp_update_attachment_metadata($attachment_id, wp_generate_attachment_metadata($attachment_id, $destination));
  if ($old_file && $old_file !== $destination && strpos($old_file, $directory.'/') === 0) @unlink($old_file);
  update_post_meta($attachment_id, '_bb_drive_resource_key', $ref['resource_key']);
  update_post_meta($attachment_id, '_bb_drive_cached_at', time());
  return (int) $attachment_id;
}

function bumblebee_drive_ajax_guard() {
  if (!current_user_can('manage_woocommerce') || !current_user_can('upload_files')) wp_send_json_error(['message'=>'You cannot select mockup images.'], 403);
  check_ajax_referer('bb_drive_mockups', 'nonce');
}

add_action('wp_ajax_bb_mockup_source', function() {
  bumblebee_drive_ajax_guard();
  $source = isset($_POST['source']) && is_string($_POST['source']) ? wp_unslash($_POST['source']) : '';
  if (!in_array($source, ['media','drive'], true)) wp_send_json_error(['message'=>'Choose WordPress Media Library or Google Drive.'], 400);
  update_user_meta(get_current_user_id(), 'bumblebee_mockup_source', $source);
  wp_send_json_success(['source'=>$source]);
});

add_action('wp_ajax_bb_drive_mockups_list', function() {
  bumblebee_drive_ajax_guard();
  $folder = (string) get_option('bumblebee_drive_mockup_folder', '');
  if (!preg_match('/^[A-Za-z0-9_-]{10,200}$/D', $folder)) wp_send_json_error(['message'=>'Set a Google Drive mockup folder in Bumblebee Settings, or paste an image link below.'], 400);
  $query = ['q'=>"'".$folder."' in parents and trashed = false and (mimeType = 'image/png' or mimeType = 'image/jpeg' or mimeType = 'image/webp')", 'fields'=>'nextPageToken,files(id,name)', 'pageSize'=>50, 'orderBy'=>'name', 'supportsAllDrives'=>'true', 'includeItemsFromAllDrives'=>'true'];
  if (!empty($_POST['page_token']) && is_string($_POST['page_token'])) $query['pageToken'] = sanitize_text_field(wp_unslash($_POST['page_token']));
  $files = bumblebee_drive_api('files', $query);
  if (is_wp_error($files)) wp_send_json_error(['message'=>$files->get_error_message()], 400);
  wp_send_json_success($files);
});

add_action('wp_ajax_bb_drive_mockup_select', function() {
  bumblebee_drive_ajax_guard();
  $ref = bumblebee_drive_file_reference(isset($_POST['file']) && is_string($_POST['file']) ? wp_unslash($_POST['file']) : '');
  if (is_wp_error($ref)) wp_send_json_error(['message'=>$ref->get_error_message()], 400);
  $id = bumblebee_drive_cache_mockup($ref);
  if (is_wp_error($id)) wp_send_json_error(['message'=>$id->get_error_message()], 400);
  wp_send_json_success(['image_id'=>$id, 'url'=>wp_get_attachment_url($id), 'name'=>get_the_title($id)]);
});

// Keep the compatibility records out of both Media Library views and the media picker.
add_action('pre_get_posts', function($query) {
  if (!is_admin() || !$query->is_main_query() || $query->get('post_type') !== 'attachment') return;
  $query->set('meta_query', ['relation'=>'AND', (array) $query->get('meta_query'), ['key'=>'_bb_drive_file_id','compare'=>'NOT EXISTS']]);
});
add_filter('ajax_query_attachments_args', function($args) {
  $args['meta_query'] = ['relation'=>'AND', (array) ($args['meta_query'] ?? []), ['key'=>'_bb_drive_file_id','compare'=>'NOT EXISTS']];
  return $args;
});

// Refresh used cache entries in the background; an outage never removes the last good image.
add_filter('wp_get_attachment_url', function($url, $id) {
  if (get_post_meta($id, '_bb_drive_file_id', true) && (int) get_post_meta($id, '_bb_drive_cached_at', true) < time() - DAY_IN_SECONDS && !wp_next_scheduled('bumblebee_refresh_drive_mockup', [(int) $id])) {
    wp_schedule_single_event(time()+60, 'bumblebee_refresh_drive_mockup', [(int) $id]);
  }
  return $url;
}, 10, 2);
add_action('bumblebee_refresh_drive_mockup', function($id) {
  $file_id = (string) get_post_meta($id, '_bb_drive_file_id', true);
  if ($file_id === '') return;
  $result = bumblebee_drive_cache_mockup(['id'=>$file_id, 'resource_key'=>(string) get_post_meta($id, '_bb_drive_resource_key', true)]);
  if (is_wp_error($result)) update_post_meta($id, '_bb_drive_cached_at', time());
});

function bumblebee_render_mockup_source_control() {
  ?>
  <select id="bb_mockup_source" aria-describedby="bb-mockup-source-status">
    <option value="media" <?php selected(bumblebee_mockup_source(), 'media'); ?>>WordPress Media Library</option>
    <option value="drive" <?php selected(bumblebee_mockup_source(), 'drive'); ?>>Google Drive</option>
  </select>
  <span id="bb-mockup-source-status" role="status" aria-live="polite"></span>
  <?php
}

function bumblebee_enqueue_drive_mockups() {
  wp_enqueue_script('bumblebee-drive-mockups', BUMBLEBEE_URL.'assets/drive-mockups.js', ['jquery'], (string) filemtime(BUMBLEBEE_PATH.'assets/drive-mockups.js'), true);
  wp_localize_script('bumblebee-drive-mockups', 'BumblebeeDrive', ['ajaxurl'=>admin_url('admin-ajax.php'), 'nonce'=>wp_create_nonce('bb_drive_mockups')]);
  wp_enqueue_style('wp-components');
  wp_add_inline_style('wp-components', '.bb-drive-dialog{border:1px solid #8c8f94;border-radius:6px;padding:24px;width:min(600px,90vw);max-height:80vh;overflow:auto}.bb-drive-dialog::backdrop{background:rgba(0,0,0,.45)}.bb-drive-dialog li{margin:0}.bb-drive-dialog .bb-drive-file{width:100%;text-align:left;padding:8px;height:auto;white-space:normal}.bb-drive-dialog input{width:100%}.bb-drive-dialog [role=status]{min-height:24px}.bb-drive-dialog h2{margin-top:0}');
}
