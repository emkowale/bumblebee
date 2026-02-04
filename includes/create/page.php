<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function bumblebee_render_create_page(){
  if(!current_user_can('manage_woocommerce')) return;

  bumblebee_enqueue_create_assets();
  $bb_locations = bumblebee_locations_map();
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
        <span class="bb-tooltip" aria-label="<?php echo esc_attr(get_bloginfo('name')); ?> will automatically be appended to the front of the product title you enter.">?</span>
        <span class="bb-tooltip__text"><?php echo esc_html(get_bloginfo('name')); ?> will automatically be appended to the front of the product title you enter.</span>
      </th>
      <td><input type="text" id="bb_title" class="regular-text" required /><div class="bb-inline-error" data-for="bb_title"></div></td>
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
        <span class="bb-tooltip" aria-label="The sizes of the vendor garment. Usually &quot;S,M,L,XL,2XL,3XL&quot;.">?</span>
        <span class="bb-tooltip__text">The sizes of the vendor garment. Usually "S,M,L,XL,2XL,3XL".</span>
      </th>
      <td><input type="text" id="bb_sizes" class="regular-text" required /><div class="bb-inline-error" data-for="bb_sizes"></div></td>
    </tr>
    <tr>
      <th>
        <label for="bb_color_count">Garment Color(s)</label>
        <span class="bb-tooltip" aria-label="The number of colors the product will be available in. You will need a Mockup Image for each Garment Color.">?</span>
        <span class="bb-tooltip__text">The number of colors the product will be available in. You will need a Mockup Image for each Garment Color.</span>
      </th>
      <td>
        <select id="bb_color_count" required>
          <option value="">— Select —</option>
<?php for($i=1; $i<=50; $i++): ?>
          <option value="<?php echo esc_attr($i); ?>"><?php echo esc_html($i); ?></option>
<?php endfor; ?>
        </select>
        <div class="bb-inline-error" data-for="bb_color_count"></div>
        <div id="bb-color-rows"></div>
      </td>
    </tr>
    <tr><th><label for="bb_production">Production</label></th><td><select id="bb_production" required><option value="">— Select —</option><option>DTG</option><option>DTF</option><option>Embroidery</option><option>UV</option></select><div class="bb-inline-error" data-for="bb_production"></div></td></tr>
    <tr>
      <th>
        Print Location(s)
        <span class="bb-tooltip" aria-label="The position on the garment that the Original Art will be located. You will need an Original Art for each Print Location. Be sure it is a 600dpi PNG.">?</span>
        <span class="bb-tooltip__text">The position on the garment that the Original Art will be located. You will need an Original Art for each Print Location. Be sure it is a 600dpi PNG.</span>
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
    <tr id="bb_optional_turbo_row" class="bb-optional-row" style="display:none;">
      <th>Turbo RIP</th>
      <td>
        <div class="bb-optional-file" data-field="turbo">
          <button type="button" class="button bb-optional-file-pick" data-field="turbo">Upload/Choose</button>
          <span class="bb-file-pill" data-field="turbo" style="margin-left:8px;display:none;"></span>
          <div class="bb-optional-error" data-field="turbo" style="display:none;margin-top:6px;"></div>
          <input type="hidden" id="bb_turbo_rip_url" name="turbo_rip_url" />
          <input type="hidden" id="bb_turbo_rip_id" name="turbo_rip_id" />
        </div>
      </td>
    </tr>
    <tr id="bb_optional_embroidery_row" class="bb-optional-row" style="display:none;">
      <th>Embroidery File</th>
      <td>
        <div class="bb-optional-file" data-field="embroidery">
          <button type="button" class="button bb-optional-file-pick" data-field="embroidery">Upload/Choose</button>
          <span class="bb-file-pill" data-field="embroidery" style="margin-left:8px;display:none;"></span>
          <div class="bb-optional-error" data-field="embroidery" style="display:none;margin-top:6px;"></div>
          <input type="hidden" id="bb_embroidery_file_url" name="embroidery_file_url" />
          <input type="hidden" id="bb_embroidery_file_id" name="embroidery_file_id" />
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
