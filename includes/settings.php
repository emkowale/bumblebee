<?php
/* Bumblebee Settings (API key + Styles) */
if (!defined('ABSPATH')) exit;
require_once __DIR__.'/helpers.php';

add_action('admin_menu', function(){
  add_submenu_page('bumblebee','Settings','Settings','manage_options','bumblebee-settings','bee_settings_page',20);
});

add_action('admin_init', function(){
  register_setting('bee_settings','bumblebee_ai_key',['type'=>'string','sanitize_callback'=>'sanitize_text_field']);
  register_setting('bee_settings','bumblebee_styles',['type'=>'array','sanitize_callback'=>function($arr){
    $valid=array_keys(bee_styles()); $out=[];
    foreach((array)$arr as $k){ if(in_array($k,$valid,true)) $out[]=$k; }
    return array_values(array_unique($out));
  }]);
});

function bee_settings_page(){
  if(!current_user_can('manage_options')) return;
  $styles=bee_styles(); $sel=(array)get_option('bumblebee_styles',[]);
  $src = defined('BEE_AI_KEY') && BEE_AI_KEY ? 'constant (BEE_AI_KEY)'
       : (getenv('OPENAI_API_KEY') ? 'environment (OPENAI_API_KEY)' : 'option (saved here)');
  ?>
  <div class="wrap">
    <h1>Bumblebee – Settings</h1>
    <p><strong>Key source:</strong> <?php echo esc_html($src); ?>.
       Save an API key below to use the option source.</p>
    <form method="post" action="options.php">
      <?php settings_fields('bee_settings'); ?>
      <table class="form-table" role="presentation">
        <tr>
          <th scope="row"><label for="bee_key">OpenAI API Key</label></th>
          <td>
            <input id="bee_key" name="bumblebee_ai_key" type="password" class="regular-text"
                   value="<?php echo esc_attr(get_option('bumblebee_ai_key','')); ?>" autocomplete="off" />
            <p class="description">Stored in WordPress options. Constant/env (if present) override this.</p>
          </td>
        </tr>
        <tr>
          <th scope="row">Copy Styles (choose ≥1)</th>
          <td>
            <?php foreach($styles as $id=>$label): ?>
              <label style="display:inline-block;margin:0 14px 8px 0;">
                <input type="checkbox" name="bumblebee_styles[]"
                       value="<?php echo esc_attr($id); ?>" <?php checked(in_array($id,$sel,true)); ?> />
                <?php echo esc_html($label); ?>
              </label>
            <?php endforeach; ?>
            <p class="description">Used to shape Title, Description, Short Description, and Tags.</p>
          </td>
        </tr>
      </table>
      <?php submit_button('Save Settings'); ?>
      <a href="#" class="button" id="bee-test-ai">Test OpenAI</a>
      <span id="bee-ai-status" style="margin-left:10px;"></span>
    </form>
  </div>
  <script>
  (function($){
    $('#bee-test-ai').on('click',function(e){
      e.preventDefault();
      var $o=$('#bee-ai-status').text('Testing…');
      $.post(ajaxurl,{action:'bee_test_ai',nonce:'<?php echo esc_js(wp_create_nonce('bee_nonce')); ?>'},function(r){
        if(r&&r.success){ $o.text('✅ OK'); } else { $o.text('❌ '+(r&&r.data?r.data:'failed')); }
      }).fail(function(x){ $o.text('❌ ajax: '+x.status); });
    });
  })(jQuery);
  </script>
  <?php
}
