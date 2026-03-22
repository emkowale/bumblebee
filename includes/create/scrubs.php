<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function bumblebee_scrub_attribute_definitions(): array {
  return [
    'fabric' => [
      'label' => 'Fabric',
      'request_key' => 'scrub_fabric',
      'choices' => [
        'performance' => [
          'label' => 'Performance',
          'attributes' => ['Performance'],
        ],
        'brushed_cotton' => [
          'label' => 'Brushed Cotton',
          'attributes' => ['Brushed Cotton'],
        ],
        'both' => [
          'label' => 'BOTH',
          'attributes' => ['Performance', 'Brushed Cotton'],
        ],
      ],
    ],
    'wear_style' => [
      'label' => 'Wear Style',
      'request_key' => 'scrub_wear_style',
      'choices' => [
        'left_out' => [
          'label' => 'Left Out',
          'attributes' => ['Left Out'],
        ],
        'both' => [
          'label' => 'Both',
          'attributes' => ['Left Out', 'Tuck In'],
        ],
        'tuck_in' => [
          'label' => 'Tuck In',
          'attributes' => ['Tuck In'],
        ],
      ],
    ],
    'pants_style' => [
      'label' => 'Pants Style',
      'request_key' => 'scrub_pants_style',
      'choices' => [
        'straight' => [
          'label' => 'Straight',
          'attributes' => ['Straight'],
        ],
        'jogger' => [
          'label' => 'Jogger',
          'attributes' => ['Jogger'],
        ],
        'flare' => [
          'label' => 'Flare',
          'attributes' => ['Flare'],
        ],
        'wide' => [
          'label' => 'Wide',
          'attributes' => ['Wide'],
        ],
      ],
    ],
    'fit' => [
      'label' => 'Fit',
      'request_key' => 'scrub_fit',
      'choices' => [
        'modern' => [
          'label' => 'Modern',
          'attributes' => ['Modern'],
        ],
        'missy' => [
          'label' => 'Missy',
          'attributes' => ['Missy'],
        ],
        'modern_classic' => [
          'label' => 'Modern & Classic',
          'attributes' => ['Modern', 'Classic'],
        ],
      ],
    ],
  ];
}

function bumblebee_scrubs_choice_from_request(): string {
  $raw = isset($_POST['scrubs']) ? (string) wp_unslash($_POST['scrubs']) : '';
  return strtolower(trim($raw)) === 'yes' ? 'yes' : 'no';
}

function bumblebee_parse_scrub_attributes_from_request(): array {
  $definitions = bumblebee_scrub_attribute_definitions();
  $selected = [];

  foreach ($definitions as $field_key => $definition) {
    $request_key = isset($definition['request_key']) ? (string) $definition['request_key'] : '';
    if ($request_key === '') continue;

    $raw_choice = isset($_POST[$request_key]) ? sanitize_key(wp_unslash($_POST[$request_key])) : '';
    $choices = isset($definition['choices']) && is_array($definition['choices']) ? $definition['choices'] : [];
    if ($raw_choice === '' || empty($choices[$raw_choice])) {
      wp_send_json([
        'success' => false,
        'message' => sprintf('%s is required for Scrubs products.', (string) ($definition['label'] ?? $field_key)),
      ]);
    }

    $choice = $choices[$raw_choice];
    $selected[$field_key] = [
      'label' => (string) ($definition['label'] ?? $field_key),
      'choice_key' => $raw_choice,
      'choice_label' => (string) ($choice['label'] ?? $raw_choice),
      'attributes' => isset($choice['attributes']) && is_array($choice['attributes'])
        ? array_values(array_filter(array_map('sanitize_text_field', $choice['attributes'])))
        : [],
    ];
  }

  return $selected;
}
