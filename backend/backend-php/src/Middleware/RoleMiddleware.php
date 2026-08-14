<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Security\Permissions;

/**
 * Port of middleware/role.middleware.ts's requirePermission(). Must run
 * after AuthMiddleware::handle() so a session is already attached.
 *
 * Usage:
 *   $router->post('/students', [StudentController::class, 'store'], [
 *       AuthMiddleware::handle(...),
 *       RoleMiddleware::require('students:write'),
 *   ]);
 */
final class RoleMiddleware
{
    public static function require(string $permission): callable
    {
        return static function (Request $request) use ($permission): void {
            $session = AuthMiddleware::session();

            if (!Permissions::can($session['role'], $permission)) {
                Response::forbidden("Role {$session['role']} cannot perform \"{$permission}\"");
            }
        };
    }
}
