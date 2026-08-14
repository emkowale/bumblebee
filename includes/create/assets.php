<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function bumblebee_asset_version(string $relative_path): string {
  $path = BUMBLEBEE_PATH . ltrim($relative_path, '/');
  if (file_exists($path)) {
    $mtime = filemtime($path);
    if ($mtime) return (string) $mtime;
  }
  return BUMBLEBEE_VERSION;
}

function bumblebee_enqueue_create_assets(){
  $create_css_ver = bumblebee_asset_version('assets/create.css');
  $picker_js_ver  = bumblebee_asset_version('assets/create.picker.js');
  $form_js_ver    = bumblebee_asset_version('assets/create.form.js');

  wp_enqueue_media();
  wp_enqueue_style('bumblebee-create', BUMBLEBEE_URL.'assets/create.css', [], $create_css_ver);

  wp_register_script('bumblebee-create-pickers', BUMBLEBEE_URL.'assets/create.picker.js', ['jquery','media-editor'], $picker_js_ver, true);
  wp_register_script('bumblebee-create-form', BUMBLEBEE_URL.'assets/create.form.js', ['jquery','bumblebee-create-pickers'], $form_js_ver, true);

  wp_localize_script('bumblebee-create-form','BumblebeeCreate',[
    'ajaxurl'=>admin_url('admin-ajax.php'),
    'nonce'=>wp_create_nonce('bb_create_product'),
    'deleteNonce'=>wp_create_nonce('bb_delete_product'),
    'hubNonce'=>wp_create_nonce('bb_hub_vendors'),
    'prepareMockupNonce'=>wp_create_nonce('bb_prepare_mockup'),
    'originalAttachmentNonce'=>wp_create_nonce('bb_original_attachment_url'),
    'required'=>[
      'price'=>'Please enter a valid price (greater than 0).',
      'title'=>'Please enter a product title.',
      'colormockup'=>'Please choose a mockup image for Color %d.',
      'colorcount'=>'Select how many colors.',
      'colorname'=>'Enter a name for Color %d.',
      'sizes'=>'Enter at least one size.',
      'vendorname'=>'Enter a name for Vendor %d.',
      'vendoritem'=>'Enter an item number for Vendor %d.',
      'production'=>'Choose a Production method.',
      'scrubfield'=>'Select a value for %s.'
    ]
  ]);

  wp_enqueue_script('bumblebee-create-pickers');
  wp_enqueue_script('bumblebee-create-form');
}
