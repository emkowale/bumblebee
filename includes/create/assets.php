<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function bumblebee_enqueue_create_assets(){
  wp_enqueue_media();
  wp_enqueue_style('bumblebee-create', BUMBLEBEE_URL.'assets/create.css',[],BUMBLEBEE_VERSION);
  wp_add_inline_style('bumblebee-create', '
    #bb-print-locations .bb-location-check{ display:flex; align-items:center; gap:8px; margin:6px 0; }
    #bb-print-locations .bb-location-check > span:first-of-type{ min-width:140px; display:inline-block; }
    #bb-print-locations .bb-upload-original{ margin-left:8px; }
    #bb-print-locations .bb-location-check.bb-missing{ outline:1px solid #d93025; border-radius:6px; }
    .bb-optional-row td{ display:flex; flex-direction:column; gap:6px; }
    .bb-optional-row .bb-optional-file{ display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
    .bb-optional-row .bb-optional-error{ color:#a40000; font-size:12px; display:none; }
  ');

  wp_register_script('bumblebee-create-pickers', BUMBLEBEE_URL.'assets/create.picker.js', ['jquery','media-editor'], BUMBLEBEE_VERSION, true);
  wp_register_script('bumblebee-create-locations', BUMBLEBEE_URL.'assets/create.locations.js', ['jquery','media-editor'], BUMBLEBEE_VERSION, true);
  wp_register_script('bumblebee-create-form', BUMBLEBEE_URL.'assets/create.form.js', ['jquery','bumblebee-create-locations','bumblebee-create-pickers'], BUMBLEBEE_VERSION, true);

  wp_localize_script('bumblebee-create-form','BumblebeeCreate',[
    'ajaxurl'=>admin_url('admin-ajax.php'),
    'nonce'=>wp_create_nonce('bb_create_product'),
    'deleteNonce'=>wp_create_nonce('bb_delete_product'),
    'hubNonce'=>wp_create_nonce('bb_hub_vendors'),
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
      'printloc'=>'Select at least one Print Location.'
    ],
    'optional'=>[
      'turboInvalid'=>'Turbo RIP files must have a .trd extension.'
    ]
  ]);

  wp_enqueue_script('bumblebee-create-pickers');
  wp_enqueue_script('bumblebee-create-locations');
  wp_enqueue_script('bumblebee-create-form');
}
