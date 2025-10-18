<?php
if ( ! defined( 'ABSPATH' ) ) exit;
const BEE_OPT_AI_DISABLED='bumblebee_disable_ai';
const BEE_OPT_OPENAI_KEY_PRIMARY='bumblebee_openai_key_primary';
const BEE_OPT_OPENAI_KEY_SECONDARY='bumblebee_openai_key_secondary';
const BEE_OPT_COPY_STYLES='bumblebee_copy_styles_whitelist';
add_action('admin_init', function(){
  register_setting('bumblebee_settings',BEE_OPT_AI_DISABLED,['type'=>'boolean','sanitize_callback'=>'rest_sanitize_boolean']);
  register_setting('bumblebee_settings',BEE_OPT_OPENAI_KEY_PRIMARY);
  register_setting('bumblebee_settings',BEE_OPT_OPENAI_KEY_SECONDARY);
  register_setting('bumblebee_settings',BEE_OPT_COPY_STYLES,['type'=>'array','sanitize_callback'=>'sanitize_text_field']);
});
function bumblebee_section_start($title,$icon=''){ echo '<div style="background:#fff;padding:16px;border:1px solid #dcdcde;border-radius:8px;margin-bottom:16px"><h2 style="margin:0 0 12px;display:flex;gap:8px;align-items:center;">'.$icon.' '.esc_html($title).'</h2>'; }
function bumblebee_render_settings_page(){ if(!current_user_can('manage_woocommerce')) return; ?>
<div class="wrap">
  <h1>Bumblebee Settings <span style="color:#777;font-weight:normal;">v<?php echo esc_html(BUMBLEBEE_VERSION); ?></span></h1>
  <form method="post" action="options.php">
    <?php settings_fields('bumblebee_settings'); ?>
    <?php bumblebee_section_start('Primary OpenAI API Key','🔑'); ?>
      <input type="text" class="regular-text" name="<?php echo esc_attr(BEE_OPT_OPENAI_KEY_PRIMARY); ?>" value="<?php echo esc_attr(get_option(BEE_OPT_OPENAI_KEY_PRIMARY,'')); ?>" />
      <p><button type="button" class="button">Test Primary Key</button></p>
    </div>
    <?php bumblebee_section_start('Secondary OpenAI API Key','🔒'); ?>
      <input type="text" class="regular-text" name="<?php echo esc_attr(BEE_OPT_OPENAI_KEY_SECONDARY); ?>" value="<?php echo esc_attr(get_option(BEE_OPT_OPENAI_KEY_SECONDARY,'')); ?>" />
      <p><button type="button" class="button">Test Secondary Key</button></p>
    </div>
    <?php bumblebee_section_start('AI Generation','🤖'); ?>
      <label><input type="checkbox" name="<?php echo esc_attr(BEE_OPT_AI_DISABLED); ?>" value="1" <?php checked((bool)get_option(BEE_OPT_AI_DISABLED,false)); ?> /> Disable AI creation of product description, product tags, and short description</label>
    </div>
    <?php bumblebee_section_start('Copy Styles (choose ≥1)','✏️'); ?>
      <?php $choices=['Formal','Casual','Friendly','Professional','Playful','Concise','Detailed','Salesy','Technical','Storytelling']; $selected=(array)get_option(BEE_OPT_COPY_STYLES,[]); foreach($choices as $c): ?>
        <label style="margin-right:12px;"><input type="checkbox" name="<?php echo esc_attr(BEE_OPT_COPY_STYLES); ?>[]" value="<?php echo esc_attr($c); ?>" <?php checked(in_array($c,$selected,true)); ?> /> <?php echo esc_html($c); ?></label>
      <?php endforeach; ?>
    </div>
    <?php bumblebee_section_start('Orphaned Media Cleanup','🧹'); ?>
      <p>Preview how many items would be removed, then confirm. Deletions are permanent.</p>
      <p><button type="button" class="button" id="bb_orphan_preview">Preview Sweep</button> <button type="button" class="button button-primary" id="bb_orphan_delete" disabled>Delete Orphans</button> <span id="bb_orphan_result" style="margin-left:8px;"></span></p>
    </div>
    <?php submit_button('Save Settings'); ?>
  </form>
</div>
<?php }
add_action('admin_enqueue_scripts', function($hook){
  if($hook!=='toplevel_page_bumblebee-create' && $hook!=='bumblebee_page_bumblebee-settings') return;
  wp_enqueue_script('bumblebee-settings', BUMBLEBEE_URL.'assets/settings.js',['jquery'],BUMBLEBEE_VERSION,true);
  wp_localize_script('bumblebee-settings','BumblebeeSettings',['ajaxurl'=>admin_url('admin-ajax.php'),'nonce'=>wp_create_nonce('bb_orphan_sweep')]);
});