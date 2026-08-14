<?php
/**
 * Application bootstrap.
 *
 * Deliberately dependency-free: no Composer required. This keeps the
 * project runnable on cheap shared hosting (cPanel etc.) where `composer
 * install` may not be available, while still following a clean PSR-4-like
 * namespace layout under src/.
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0'); // never leak stack traces to API clients

define('APP_ROOT', __DIR__);
define('SRC_ROOT', APP_ROOT . '/src');

// ---------------------------------------------------------------------
// Minimal PSR-4 autoloader: App\Foo\Bar => src/Foo/Bar.php
// ---------------------------------------------------------------------
spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $path = SRC_ROOT . '/' . str_replace('\\', '/', $relative) . '.php';

    if (is_file($path)) {
        require $path;
    }
});

use App\Config\Env;
use App\Config\Database;

// ---------------------------------------------------------------------
// Load environment variables from .env (if present)
// ---------------------------------------------------------------------
Env::load(APP_ROOT . '/.env');

// ---------------------------------------------------------------------
// Timezone + error handler
// ---------------------------------------------------------------------
date_default_timezone_set(Env::get('APP_TIMEZONE', 'Africa/Dar_es_Salaam'));

set_exception_handler(function (Throwable $e): void {
    error_log('[UNCAUGHT] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Something went wrong',
        'errors'  => [],
    ]);
    exit;
});

// Warm the DB connection lazily — first call to Database::pdo() connects.
// We don't connect here eagerly so that e.g. a healthcheck route can run
// even if the DB is briefly unavailable.
