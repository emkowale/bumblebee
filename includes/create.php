<?php
if ( ! defined( 'ABSPATH' ) ) exit;
function bumblebee_render_create_page(){
  if(!current_user_can('manage_woocommerce')) return;
  wp_enqueue_media();
  wp_enqueue_style('bumblebee-create', BUMBLEBEE_URL.'assets/create.css',[],BUMBLEBEE_VERSION);
  wp_enqueue_script('bumblebee-create', BUMBLEBEE_URL.'assets/create.js',['jquery'],BUMBLEBEE_VERSION,true);
  wp_localize_script('bumblebee-create','BumblebeeCreate',[
    'ajaxurl'=>admin_url('admin-ajax.php'),
    'nonce'=>wp_create_nonce('bb_create_product'),
    'required'=>[
      'price'=>'Please enter a valid price (greater than 0).',
      'title'=>'Please enter a product title.',
      'image'=>'Please select a product image.',
      'vector'=>'Please select original vector art.',
      'colors'=>'Enter at least one color.',
      'sizes'=>'Enter at least one size.',
      'vendor'=>'Enter a vendor code.',
      'production'=>'Choose a Production method.',
      'printloc'=>'Choose a Print Location.'
    ]
  ]); ?>
  <div class="wrap"><h1>Create a Product <span style="color:#777;font-weight:normal;">Bumblebee v<?php echo esc_html(BUMBLEBEE_VERSION); ?></span></h1>
  <table class="form-table" role="presentation"><tbody>
    <tr><th><label for="bb_price">Price (USD)</label></th><td><input type="number" min="0" step="0.01" id="bb_price" class="regular-text" required /><div class="bb-inline-error" data-for="bb_price"></div></td></tr>
    <tr><th>Taxable</th><td><label><input type="radio" name="bb_taxable" value="taxable" checked> Yes</label> &nbsp;<label><input type="radio" name="bb_taxable" value="none"> No</label></td></tr>
    <tr><th><label for="bb_title">Enter a product title</label></th><td><input type="text" id="bb_title" class="regular-text" required /><div class="bb-inline-error" data-for="bb_title"></div></td></tr>
    <tr><th>Product Image (500×500 webp)</th><td><button class="button" id="bb_pick_image">Upload/Choose</button> <span id="bb_image_preview" style="margin-left:10px;"></span><input type="hidden" id="bb_image_id" /><div class="bb-inline-error" data-for="bb_image_id"></div></td></tr>
    <tr><th>Original-Art (vector)</th><td><button class="button" id="bb_pick_vector">Upload/Choose</button> <span id="bb_vector_preview" style="margin-left:10px;"></span><input type="hidden" id="bb_vector_id" /><input type="hidden" id="bb_vector_url" /><div class="bb-inline-error" data-for="bb_vector_id"></div></td></tr>
    <tr><th><label for="bb_colors">Color(s)</label></th><td><input type="text" id="bb_colors" class="regular-text" required /><div class="bb-inline-error" data-for="bb_colors"></div></td></tr>
    <tr><th><label for="bb_sizes">Size(s)</label></th><td><input type="text" id="bb_sizes" class="regular-text" required /><div class="bb-inline-error" data-for="bb_sizes"></div></td></tr>
    <tr><th><label for="bb_vendor_code">Vendor Code (Vendor(ItemNumber))</label></th><td><input type="text" id="bb_vendor_code" class="regular-text" required /><div class="bb-inline-error" data-for="bb_vendor_code"></div></td></tr>
    <tr><th><label for="bb_production">Production</label></th><td><select id="bb_production" required><option value="">— Select —</option><option>Screen Print</option><option>DF</option><option>Embroidery</option></select><div class="bb-inline-error" data-for="bb_production"></div></td></tr>
    <tr><th><label for="bb_print_location">Print Location</label></th><td><select id="bb_print_location" required><option value="">— Select —</option><option>Front</option><option>Back</option><option>Front &amp; Back</option><option>Left Chest</option><option>Right Chest</option><option>Left Chest &amp; Back</option><option>Right Chest &amp; Back</option></select><div class="bb-inline-error" data-for="bb_print_location"></div></td></tr>
  </tbody></table>
  <p><button class="button button-primary" id="bb_create_btn">Create Product</button><span class="spinner" style="float:none;"></span></p></div>
<?php }