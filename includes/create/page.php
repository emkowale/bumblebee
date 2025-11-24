<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function bumblebee_render_create_page(){
  if(!current_user_can('manage_woocommerce')) return;

  bumblebee_enqueue_create_assets();
  $bb_locations = bumblebee_locations_map();
  ?>
  <div class="wrap"><h1>Create a Product <span style="color:#777;font-weight:normal;">Bumblebee v<?php echo esc_html(BUMBLEBEE_VERSION); ?></span></h1>
  <table class="form-table" role="presentation"><tbody>
    <tr><th><label for="bb_price">Price (USD)</label></th><td><input type="number" min="0" step="0.01" id="bb_price" class="regular-text" required /><div class="bb-inline-error" data-for="bb_price"></div></td></tr>
    <tr><th>Taxable</th><td><label><input type="radio" name="bb_taxable" value="taxable" checked> Yes</label> &nbsp;<label><input type="radio" name="bb_taxable" value="none"> No</label></td></tr>
    <tr><th><label for="bb_title">Enter a product title</label></th><td><input type="text" id="bb_title" class="regular-text" required /><div class="bb-inline-error" data-for="bb_title"></div></td></tr>
    <tr><th>Product Image (500×500 webp)</th><td><button class="button" id="bb_pick_image">Upload/Choose</button> <span id="bb_image_preview" style="margin-left:10px;"></span><input type="hidden" id="bb_image_id" /><div class="bb-inline-error" data-for="bb_image_id"></div></td></tr>
    <tr>
      <th><label for="bb_color_count">Color(s)</label></th>
      <td>
        <select id="bb_color_count" required>
          <option value="">— Select —</option>
          <option value="0">N/A</option>
<?php for($i=1; $i<=50; $i++): ?>
          <option value="<?php echo esc_attr($i); ?>"><?php echo esc_html($i); ?></option>
<?php endfor; ?>
        </select>
        <div class="bb-inline-error" data-for="bb_color_count"></div>
        <div id="bb-color-rows"></div>
      </td>
    </tr>
    <tr><th><label for="bb_sizes">Size(s)</label></th><td><input type="text" id="bb_sizes" class="regular-text" required /><div class="bb-inline-error" data-for="bb_sizes"></div></td></tr>
    <tr>
      <th><label for="bb_vendor_count">Vendor Code (Vendor(ItemNumber))</label></th>
      <td>
        <select id="bb_vendor_count" required>
          <option value="">— Select —</option>
<?php for($i=1; $i<=5; $i++): ?>
          <option value="<?php echo esc_attr($i); ?>"><?php echo esc_html($i); ?></option>
<?php endfor; ?>
        </select>
        <div class="bb-inline-error" data-for="bb_vendor_count"></div>
        <div id="bb-vendor-rows"></div>
      </td>
    </tr>
    <tr><th><label for="bb_production">Production</label></th><td><select id="bb_production" required><option value="">— Select —</option><option>Screen Print</option><option>DF</option><option>Embroidery</option></select><div class="bb-inline-error" data-for="bb_production"></div></td></tr>
    <tr>
      <th>Print Locations (Bumblebee)</th>
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
