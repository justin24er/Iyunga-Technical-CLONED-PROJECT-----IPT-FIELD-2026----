<?php

declare(strict_types=1);

namespace App\Core;

use App\Config\Env;

/**
 * The frontend is a separate static site (per the project's own README —
 * "any host: Nginx, Vercel, Netlify") that authenticates via an HTTP-only
 * cookie, so cross-origin requests need explicit CORS + credentials
 * support. FRONTEND_URL must be an exact origin (no wildcard) because
 * `Access-Control-Allow-Credentials: true` is incompatible with `*`.
 */
final class Cors
{
    public static function apply(): void
    {
        $allowed = Env::get('FRONTEND_URL', 'http://localhost:5500');
        $origin = $_SERVER['HTTP_ORIGIN'] ?? null;

        // Support a comma-separated list of allowed origins for multi-env setups.
        $allowedList = array_map('trim', explode(',', $allowed));

        if ($origin !== null && in_array($origin, $allowedList, true)) {
            header("Access-Control-Allow-Origin: {$origin}");
            header('Access-Control-Allow-Credentials: true');
            header('Vary: Origin');
        }

        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
        header('Access-Control-Max-Age: 86400');
    }
}
