<?php
/* Bumblebee config (no secrets in Git) */
if (!defined('ABSPATH')) exit;

/* Leave this blank in the repo. You can still paste a key here if you want,
   but Settings (option) and BEE_AI_KEY/env will also work. */
if (!defined('BEE_AI_KEY')) {
  define('BEE_AI_KEY', '');  // keep empty; add key in Settings page
}
