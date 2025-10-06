<?php
/* Bumblebee config (Soundwave-style) */
if (!defined('ABSPATH')) exit;

/**
 * Define your permanent OpenAI key here to make installs “just work”.
 * IMPORTANT: Do not commit real secrets to public repos.
 *
 * Recommended:
 * - Keep this blank in Git, and define BEE_AI_KEY in an MU-plugin or wp-config.php on the server, OR
 * - Use a private repo if you must hard-code.
 */
if (!defined('BEE_AI_KEY')) {
  define('BEE_AI_KEY', 'REDACTED'); // e.g. 'sk-…' (set on server or private repo)
}
