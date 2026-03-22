<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function bumblebee_render_create_page(){
  if(!current_user_can('manage_woocommerce')) return;

  bumblebee_enqueue_create_assets();
  $bb_locations = bumblebee_locations_map();
  $bb_scrub_defs = function_exists('bumblebee_scrub_attribute_definitions')
    ? bumblebee_scrub_attribute_definitions()
    : [];
  ?>
  <div class="wrap"><h1>Create a Product<br>
    <span class="bb-create-version" style="color:#9aa0a6;font-weight:400;font-size:14px;">Bumblebee <?php echo esc_html(BUMBLEBEE_VERSION); ?></span>
  </h1>
  <div id="bb-ai-fail" class="notice notice-warning" style="display:none;">
    <p>AI generation of the product description, short description and product tags failed because <span id="bb-ai-reason"></span>. Do you want to continue?</p>
    <p class="bb-ai-fail-actions">
      <button type="button" class="button button-primary" id="bb-ai-continue">Yes</button>
      <button type="button" class="button" id="bb-ai-cancel">No</button>
    </p>
  </div>
  <table class="form-table" role="presentation"><tbody>
    <tr>
      <th>
        <label for="bb_title">Enter a product title</label>
        <span class="bb-tooltip dashicons dashicons-editor-help" aria-label="<?php echo esc_attr(get_bloginfo('name')); ?> will automatically be appended to the front of the product title you enter." title="<?php echo esc_attr(get_bloginfo('name')); ?> will automatically be appended to the front of the product title you enter."></span>
      </th>
      <td><input type="text" id="bb_title" class="regular-text" required /><div class="bb-inline-error" data-for="bb_title"></div></td>
    </tr>
    <tr>
      <th>Scrubs</th>
      <td>
        <div class="bb-scrubs" data-bb-scrubs>
          <div class="bb-scrubs__toggle" role="radiogroup" aria-label="Scrubs">
            <label class="bb-scrubs__toggle-option" for="bb_scrubs_no">
              <input type="radio" class="bb-scrubs__toggle-input" id="bb_scrubs_no" name="bb_scrubs" value="no" checked />
              <span class="bb-scrubs__toggle-label">No</span>
            </label>
            <label class="bb-scrubs__toggle-option" for="bb_scrubs_yes">
              <input type="radio" class="bb-scrubs__toggle-input" id="bb_scrubs_yes" name="bb_scrubs" value="yes" />
              <span class="bb-scrubs__toggle-label">Yes</span>
            </label>
          </div>
          <div class="bb-scrubs__card" data-bb-scrubs-card aria-hidden="true">
            <?php foreach ($bb_scrub_defs as $bb_field_key => $bb_definition): ?>
              <?php
                $bb_field_id = 'bb_' . sanitize_key((string) $bb_definition['request_key']);
                $bb_label = isset($bb_definition['label']) ? (string) $bb_definition['label'] : ucwords(str_replace('_', ' ', (string) $bb_field_key));
                $bb_choices = isset($bb_definition['choices']) && is_array($bb_definition['choices']) ? $bb_definition['choices'] : [];
              ?>
              <div class="bb-scrubs__field">
                <label for="<?php echo esc_attr($bb_field_id); ?>"><?php echo esc_html($bb_label); ?></label>
                <select id="<?php echo esc_attr($bb_field_id); ?>" data-bb-scrub-select disabled>
                  <option value="">— Select —</option>
                  <?php foreach ($bb_choices as $bb_choice_key => $bb_choice): ?>
                    <option value="<?php echo esc_attr($bb_choice_key); ?>"><?php echo esc_html((string) ($bb_choice['label'] ?? $bb_choice_key)); ?></option>
                  <?php endforeach; ?>
                </select>
                <div class="bb-inline-error" data-for="<?php echo esc_attr($bb_field_id); ?>"></div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </td>
    </tr>
    <tr><th><label for="bb_price">Price (USD)</label></th><td><input type="number" min="0" step="0.01" id="bb_price" class="regular-text" required /><div class="bb-inline-error" data-for="bb_price"></div></td></tr>
    <tr><th>Taxable</th><td><label><input type="radio" name="bb_taxable" value="taxable" checked> Yes</label> &nbsp;<label><input type="radio" name="bb_taxable" value="none"> No</label></td></tr>
    <tr>
      <th>Vendor</th>
      <td>
        <div id="bb-vendor-rows"></div>
      </td>
    </tr>
    <tr>
      <th>
        <label for="bb_sizes">Garment Size(s)</label>
        <span class="bb-tooltip dashicons dashicons-editor-help" aria-label="The sizes of the vendor garment. Usually S,M,L,XL,2XL,3XL." title="The sizes of the vendor garment. Usually S,M,L,XL,2XL,3XL."></span>
      </th>
      <td><input type="text" id="bb_sizes" class="regular-text" required /><div class="bb-inline-error" data-for="bb_sizes"></div></td>
    </tr>
    <tr>
      <th>
        <label for="bb_color_count">Garment Color(s)</label>
        <span class="bb-tooltip dashicons dashicons-editor-help" aria-label="The number of colors the product will be available in. You will need a Mockup Image for each Garment Color." title="The number of colors the product will be available in. You will need a Mockup Image for each Garment Color."></span>
      </th>
      <td>
        <select id="bb_color_count" required>
          <option value="">— Select —</option>
<?php for($i=1; $i<=100; $i++): ?>
          <option value="<?php echo esc_attr($i); ?>"><?php echo esc_html($i); ?></option>
<?php endfor; ?>
        </select>
        <div class="bb-inline-error" data-for="bb_color_count"></div>
        <div id="bb-color-rows"></div>
      </td>
    </tr>
    <tr><th><label for="bb_production">Production</label></th><td><select id="bb_production" required><option value="">— Select —</option><option>DTG</option><option>DTF</option><option>Embroidery</option><option>UV</option><option>Fulfill</option></select><div class="bb-inline-error" data-for="bb_production"></div></td></tr>
    <tr>
      <th>
        Print Location(s)
        <span class="bb-tooltip dashicons dashicons-editor-help" aria-label="The position on the garment that the Original Art will be located. You will need an Original Art for each Print Location." title="The position on the garment that the Original Art will be located. You will need an Original Art for each Print Location."></span>
      </th>
      <td>
        <div id="bb-print-locations" class="bb-section bb-phase1">
          <?php foreach ($bb_locations as $slug => $label): ?>
            <label class="bb-location-check" style="display:flex;gap:8px;align-items:center;margin:6px 0;">
              <input type="checkbox" class="bb-location-checkbox" id="bb_loc_<?php echo esc_attr($slug); ?>" data-name="<?php echo esc_attr($label); ?>" />
              <span><?php echo esc_html($label); ?></span>
            </label>
          <?php endforeach; ?>
        </div>
      </td>
    </tr>
    <tr>
      <th><label for="bb_special_instructions">Special Instructions for production</label></th>
      <td>
        <textarea id="bb_special_instructions" rows="4" class="large-text" placeholder="Notes for production team (optional)"></textarea>
      </td>
    </tr>
  </tbody></table>
  <p><button class="button button-primary" id="bb_create_btn">Create Product</button><span class="spinner" style="float:none;"></span></p></div>
<?php }
