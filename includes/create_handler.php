<?php
if ( ! defined( 'ABSPATH' ) ) exit;

require_once __DIR__.'/create_handler/validate.php';
require_once __DIR__.'/create_handler/art_meta.php';
require_once __DIR__.'/create_handler/product.php';
require_once __DIR__.'/create_handler/ai_content.php';
require_once __DIR__.'/create_handler/request.php';

add_action('wp_ajax_bumblebee_create_product', 'bumblebee_handle_create_product');
