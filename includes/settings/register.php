<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action('admin_init', function(){
  register_setting('bumblebee_settings', BEE_OPT_AI_DISABLED, [
    'type'              => 'boolean',
    'sanitize_callback' => 'rest_sanitize_boolean',
    'default'           => false,
  ]);

  register_setting('bumblebee_settings', BEE_OPT_OPENAI_KEY_PRIMARY, [
    'type'              => 'string',
    'sanitize_callback' => 'sanitize_text_field',
    'default'           => '',
  ]);

  register_setting('bumblebee_settings', BEE_OPT_OPENAI_KEY_SECONDARY, [
    'type'              => 'string',
    'sanitize_callback' => 'sanitize_text_field',
    'default'           => '',
  ]);

  // Require ≥1 style; default to Friendly+Concise if none chosen
  register_setting('bumblebee_settings', BEE_OPT_COPY_STYLES, [
    'type'              => 'array',
    'sanitize_callback' => function($v){
      $v = is_array($v) ? $v : [];
      $v = array_values(array_filter(array_map('sanitize_text_field', $v)));
      if (!$v) {
        add_settings_error('bumblebee_settings', 'bb_styles_required', 'Select at least one Copy Style (defaulting to Friendly + Concise).');
        $v = ['Friendly','Concise'];
      }
      return $v;
    },
    'default'           => ['Friendly','Concise'],
  ]);
});
