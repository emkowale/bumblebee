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
  $selected  = (array) get_option(BEE_OPT_COPY_STYLES,['Friendly','Concise']);
  $choices   = ['Formal','Casual','Friendly','Professional','Playful','Concise','Detailed','Salesy','Technical','Storytelling'];
  $approved_vendors = get_option(BEE_OPT_APPROVED_VENDORS, []);
  if (!is_array($approved_vendors)) $approved_vendors = [];
  $hub_url   = get_option(BEE_OPT_HUB_URL, 'https://thebeartraxs.com/wp-json/wc/v3');
  $hub_key   = get_option(BEE_OPT_HUB_KEY, '');
  $hub_secret= get_option(BEE_OPT_HUB_SECRET, '');
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

      <?php bumblebee_section_start('Hub Connection (thebeartraxs.com)','🌐'); ?>
        <table class="form-table" role="presentation">
          <tr>
            <th scope="row"><label for="bb_hub_url">Hub Base URL</label></th>
            <td><input type="url" class="regular-text" id="bb_hub_url" name="<?php echo esc_attr(BEE_OPT_HUB_URL); ?>" value="<?php echo esc_attr($hub_url); ?>" placeholder="https://thebeartraxs.com/wp-json/wc/v3" /></td>
          </tr>
          <tr>
            <th scope="row"><label for="bb_hub_key">Consumer Key</label></th>
            <td><input type="text" class="regular-text" id="bb_hub_key" name="<?php echo esc_attr(BEE_OPT_HUB_KEY); ?>" value="<?php echo esc_attr($hub_key); ?>" /></td>
          </tr>
          <tr>
            <th scope="row"><label for="bb_hub_secret">Consumer Secret</label></th>
            <td><input type="text" class="regular-text" id="bb_hub_secret" name="<?php echo esc_attr(BEE_OPT_HUB_SECRET); ?>" value="<?php echo esc_attr($hub_secret); ?>" /></td>
          </tr>
        </table>
        <p style="margin-top:8px;">
          <button type="button" class="button" id="bb-test-hub">Test Hub Connection</button>
          <span id="bb-test-hub-status" style="margin-left:8px;"></span>
        </p>
        <p class="description">Uses the consumer key/secret to talk to the hub REST API for vendor storage.</p>
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

      <?php bumblebee_section_start('Approved Vendors','🏷️'); ?>
        <div id="bb-approved-vendors">
          <div id="bb-approved-vendors-rows" data-next-index="<?php echo esc_attr(count($approved_vendors)); ?>">
            <?php foreach($approved_vendors as $idx => $vendor): ?>
              <?php
                $name = isset($vendor['name']) ? $vendor['name'] : '';
                $code = isset($vendor['code']) ? $vendor['code'] : '';
                $desc = isset($vendor['description']) ? $vendor['description'] : '';
              ?>
              <div class="bb-approved-vendor-row" data-index="<?php echo esc_attr($idx); ?>" style="display:flex;gap:8px;align-items:flex-start;margin-bottom:10px;flex-wrap:wrap;border:1px solid #dcdcde;padding:8px;border-radius:6px;">
                <div style="min-width:200px;">
                  <label style="font-weight:600;">Vendor Name<br>
                    <input type="text" class="regular-text" name="<?php echo esc_attr(BEE_OPT_APPROVED_VENDORS); ?>[<?php echo esc_attr($idx); ?>][name]" value="<?php echo esc_attr($name); ?>" />
                  </label>
                </div>
                <div style="min-width:140px;">
                  <label style="font-weight:600;">Vendor Code<br>
                    <input type="text" class="regular-text" name="<?php echo esc_attr(BEE_OPT_APPROVED_VENDORS); ?>[<?php echo esc_attr($idx); ?>][code]" value="<?php echo esc_attr($code); ?>" />
                  </label>
                </div>
                <div style="flex:1;min-width:240px;">
                  <label style="font-weight:600;">Description<br>
                    <textarea name="<?php echo esc_attr(BEE_OPT_APPROVED_VENDORS); ?>[<?php echo esc_attr($idx); ?>][description]" rows="2" class="large-text"><?php echo esc_textarea($desc); ?></textarea>
                  </label>
                </div>
                <div style="display:flex;align-items:center;">
                  <button type="button" class="button-link-delete bb-remove-approved-vendor">Delete</button>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
          <p style="margin-top:8px;">
            <button type="button" class="button" id="bb-add-approved-vendor">Add Vendor</button>
            <button type="button" class="button button-secondary" id="bb-sync-vendors">Save Vendors to Hub</button>
            <span id="bb-vendor-save-status" style="margin-left:8px;"></span>
          </p>
          <p class="description">Use this list to keep vendor names, codes, and descriptions aligned.</p>
        </div>
        <script type="text/template" id="bb-approved-vendor-template">
          <div class="bb-approved-vendor-row" data-index="__i__" style="display:flex;gap:8px;align-items:flex-start;margin-bottom:10px;flex-wrap:wrap;border:1px solid #dcdcde;padding:8px;border-radius:6px;">
            <div style="min-width:200px;">
              <label style="font-weight:600;">Vendor Name<br>
                <input type="text" class="regular-text" name="<?php echo esc_attr(BEE_OPT_APPROVED_VENDORS); ?>[__i__][name]" value="" />
              </label>
            </div>
            <div style="min-width:140px;">
              <label style="font-weight:600;">Vendor Code<br>
                <input type="text" class="regular-text" name="<?php echo esc_attr(BEE_OPT_APPROVED_VENDORS); ?>[__i__][code]" value="" />
              </label>
            </div>
            <div style="flex:1;min-width:240px;">
              <label style="font-weight:600;">Description<br>
                <textarea name="<?php echo esc_attr(BEE_OPT_APPROVED_VENDORS); ?>[__i__][description]" rows="2" class="large-text"></textarea>
              </label>
            </div>
            <div style="display:flex;align-items:center;">
              <button type="button" class="button-link-delete bb-remove-approved-vendor">Delete</button>
            </div>
          </div>
        </script>
      </div>

      <?php submit_button('Save Settings'); ?>
    </form>
  </div>
  <?php
}
