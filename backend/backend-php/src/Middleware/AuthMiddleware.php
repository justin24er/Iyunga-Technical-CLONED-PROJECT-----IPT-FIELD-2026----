<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Security\Auth;

/**
 * Attaches the authenticated session to the request, or short-circuits
 * with 401 if there isn't one. Direct port of middleware/auth.middleware.ts.
 *
 * Usage in routes/api.php:
 *   $router->get('/students', [StudentController::class, 'index'], [
 *       AuthMiddleware::handle(...),
 *   ]);
 */
final class AuthMiddleware
{
    /** Stores the current session for the duration of the request. */
    private static ?array $session = null;

    public static function handle(Request $request): void
    {
        $session = Auth::getSession();

        if ($session === null) {
            Response::unauthorized('You must be signed in');
        }

        self::$session = $session;
    }

    /**
     * Fetches the session resolved by handle(). Must only be called from
     * a controller action reached after this middleware ran.
     */
    public static function session(): array
    {
        if (self::$session === null) {
            // Defensive: a controller called this without the middleware
            // having run first. Treat as unauthenticated rather than
            // returning stale data from a previous request.
            Response::unauthorized('You must be signed in');
        }

        return self::$session;
    }
}
