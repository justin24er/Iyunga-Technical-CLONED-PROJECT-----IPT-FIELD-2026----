<?php
/**
 * Router script for PHP's built-in dev server, which does not read
 * .htaccess. Only needed for local development:
 *
 *   php -S localhost:8000 -t public public/router.php
 *
 * Apache/Nginx in production use public/.htaccess (or an nginx try_files
 * rule) instead — this file is not involved there.
 */

$path = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// Serve real files (if any ever end up in public/) as-is; everything else
// goes through the front controller.
if ($path !== '/' && file_exists(__DIR__ . $path) && !is_dir(__DIR__ . $path)) {
    return false;
}

require __DIR__ . '/index.php';
