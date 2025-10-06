<?php
if (!defined('ABSPATH')) exit;
require_once __DIR__.'/helpers.php';
require_once __DIR__.'/ai_client.php';

add_action('admin_menu',function(){
  add_menu_page('Bumblebee','Bumblebee','manage_woocommerce','bumblebee','bee_new_product','dashicons-buddicons-replies',56);
  add_submenu_page('bumblebee','Create a Product','Create a Product','manage_woocommerce','bumblebee','bee_new_product');
  add_submenu_page('bumblebee','Settings','Settings','manage_woocommerce','bumblebee-settings','bee_settings');
});
add_action('admin_enqueue_scripts',function($hook){
  if(!in_array($hook,['toplevel_page_bumblebee','bumblebee_page_bumblebee','bumblebee_page_bumblebee-settings'],true))return;
  $styles=(array)get_option('bumblebee_styles',[]); $aiReady=(bool)bee_ai_key();
  wp_enqueue_media();
  wp_enqueue_script('bumblebee-admin',plugins_url('../assets/admin.js',__FILE__),['jquery'],defined('BUMBLEBEE_VERSION')?BUMBLEBEE_VERSION:false,true);
  wp_localize_script('bumblebee-admin','BEE',['ajax'=>admin_url('admin-ajax.php'),'nonce'=>wp_create_nonce('bee_nonce'),'aiReady'=>$aiReady,'stylesCount'=>count($styles),'settingsUrl'=>admin_url('admin.php?page=bumblebee-settings')]);
  wp_enqueue_style('bumblebee-admin',plugins_url('../assets/admin.css',__FILE__),[],defined('BUMBLEBEE_VERSION')?BUMBLEBEE_VERSION:false);
});
function bee_new_product(){ ?>
  <div class="wrap"><h1>Create a Product</h1>
  <form id="bee-new-form" onsubmit="return false;" class="card" style="max-width:900px;padding:16px">
  <table class="form-table"><tbody>
    <tr><th>Price (USD)</th><td><input id="bee_price" type="number" step="0.01" min="0" class="regular-text" required></td></tr>
    <tr><th>Taxable</th><td><select id="bee_taxable"><option value="yes" selected>Yes</option><option value="no">No</option></select></td></tr>
    <tr><th>Product Image (500×500 webp)</th><td><input id="bee_image_url" type="url" class="regular-text" required><input type="hidden" id="bee_image_id"><button type="button" class="button bee-pick" data-target="image">Upload/Choose</button></td></tr>
    <tr><th>Original-Art URL (vector)</th><td><input id="bee_art_url" type="url" class="regular-text" required><button type="button" class="button" id="bee_pick_art">Upload/Choose</button></td></tr>
    <tr><th>Color(s)</th><td><input id="bee_colors" class="regular-text" placeholder="White,Black" required></td></tr>
    <tr><th>Size(s)</th><td><input id="bee_sizes" class="regular-text" value="S,M,L,XL,2XL,3XL" required></td></tr>
    <tr><th>Print Location(s)</th><td><fieldset id="bee_print_checks">
      <label><input type="checkbox" value="Front"> Front</label><br>
      <label><input type="checkbox" value="Back"> Back</label><br>
      <label><input type="checkbox" value="Left Chest &amp; Back"> Left Chest &amp; Back</label><br>
      <label><input type="checkbox" value="Left Chest"> Left Chest</label><br>
      <label><input type="checkbox" value="Right Chest"> Right Chest</label>
    </fieldset><p class="description">At least one is required.</p></td></tr>
    <tr><th>Quality – Vendor(ItemNumber)</th><td><input id="bee_quality" class="regular-text" placeholder="SanMar(DT6000)" required></td></tr>
    <tr><th>Vendor Product URL (optional)</th><td><input id="bee_vendor_url" class="regular-text" type="url" placeholder="If auto-detect fails, paste vendor product page URL here"></td></tr>
  </tbody></table>
  <p><button id="bee_create" class="button button-primary">Create Product</button><span id="bee_status" style="margin-left:12px"></span></p>
  </form></div><?php
}
function bee_settings(){ $sel=(array)get_option('bumblebee_styles',[]); $styles=bee_styles(); ?>
  <div class="wrap"><h1>Settings</h1>
  <form method="post" action="options.php"><?php settings_fields('bumblebee_group'); ?>
    <table class="form-table"><tbody>
      <tr><th>Copy Styles (choose ≥1)</th><td><?php foreach($styles as $k=>$l){printf('<label style="display:block"><input type="checkbox" name="bumblebee_styles[]" value="%s"%s> %s</label>',esc_attr($k),in_array($k,$sel,true)?' checked':'',esc_html($l));} ?></td></tr>
      <tr><th>OpenAI</th><td>Key loads from <code>includes/secret_key.php</code> (or constant/env). <button type="button" class="button" id="bee_test_ai">Test OpenAI</button> <span id="bee_ai_status"></span></td></tr>
    </tbody></table><?php submit_button(); ?></form></div><?php
}
add_action('admin_init',function(){
  register_setting('bumblebee_group','bumblebee_styles',['type'=>'array','sanitize_callback'=>function($v){$v=array_map('sanitize_text_field',(array)$v);return array_values(array_intersect(array_keys(bee_styles()),$v));}]);
});
