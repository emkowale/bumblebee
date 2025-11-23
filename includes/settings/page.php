<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function bumblebee_section_start($title,$icon=''){
  echo '<div style="background:#fff;padding:16px;border:1px solid #dcdcde;border-radius:8px;margin-bottom:16px">';
  echo '<h2 style="margin:0 0 12px;display:flex;gap:8px;align-items:center;">'.$icon.' '.esc_html($title).'</h2>';
}

function bumblebee_render_settings_page(){
  if(!current_user_can('manage_woocommerce')) return;

  settings_errors('bumblebee_settings');

  $primary   = get_option(BEE_OPT_OPENAI_KEY_PRIMARY,'');
  $secondary = get_option(BEE_OPT_OPENAI_KEY_SECONDARY,'');
  $ai_off    = (bool) get_option(BEE_OPT_AI_DISABLED,false);
  $selected  = (array) get_option(BEE_OPT_COPY_STYLES,['Friendly','Concise']);
  $choices   = ['Formal','Casual','Friendly','Professional','Playful','Concise','Detailed','Salesy','Technical','Storytelling'];
  ?>
  <div class="wrap">
    <h1>Bumblebee Settings <span style="color:#777;font-weight:normal;">v<?php echo esc_html(BUMBLEBEE_VERSION); ?></span></h1>
    <form method="post" action="options.php" id="bb-settings-form">
      <?php settings_fields('bumblebee_settings'); ?>

      <?php bumblebee_section_start('Primary OpenAI API Key','🔑'); ?>
        <input type="text" class="regular-text" name="<?php echo esc_attr(BEE_OPT_OPENAI_KEY_PRIMARY); ?>" value="<?php echo esc_attr($primary); ?>" />
        <p style="margin-top:8px;">
          <button type="button" class="button" id="bb-test-primary">Test Primary Key</button>
          <span id="bb-test-primary-status" style="margin-left:8px;"></span>
        </p>
      </div>

      <?php bumblebee_section_start('Secondary OpenAI API Key','🔒'); ?>
        <input type="text" class="regular-text" name="<?php echo esc_attr(BEE_OPT_OPENAI_KEY_SECONDARY); ?>" value="<?php echo esc_attr($secondary); ?>" />
        <p style="margin-top:8px;">
          <button type="button" class="button" id="bb-test-secondary">Test Secondary Key</button>
          <span id="bb-test-secondary-status" style="margin-left:8px;"></span>
        </p>
      </div>

      <?php bumblebee_section_start('AI Generation','🤖'); ?>
        <label>
          <input type="checkbox" name="<?php echo esc_attr(BEE_OPT_AI_DISABLED); ?>" value="1" <?php checked($ai_off); ?> />
          Disable AI creation of product description, product tags, and short description
        </label>
      </div>

      <?php bumblebee_section_start('Copy Styles (choose ≥1)','✏️'); ?>
        <?php foreach($choices as $c): ?>
          <label style="margin-right:12px; display:inline-block; margin-bottom:6px;">
            <input type="checkbox" name="<?php echo esc_attr(BEE_OPT_COPY_STYLES); ?>[]" value="<?php echo esc_attr($c); ?>" <?php checked(in_array($c,$selected,true)); ?> />
            <?php echo esc_html($c); ?>
          </label>
        <?php endforeach; ?>
        <p style="margin-top:8px;color:#666;">If none are selected, Bumblebee defaults to Friendly + Concise and shows an error.</p>
      </div>

      <?php submit_button('Save Settings'); ?>
    </form>
  </div>
  <?php
}
