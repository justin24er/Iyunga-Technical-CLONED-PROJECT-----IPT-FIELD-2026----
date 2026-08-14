<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Logging\AuditLogger;
use App\Middleware\AuthMiddleware;
use App\Repositories\UserRepository;
use App\Security\Auth;
use App\Support\Password;
use App\Support\Validator;

/**
 * Port of app/api/auth/{login,logout,me}/route.ts.
 */
final class AuthController
{
    public static function login(Request $request): void
    {
        $validator = new Validator($request->allInput(), [
            'email'    => ['required', 'email'],
            'password' => ['required', 'min:6'],
        ]);

        if ($validator->fails()) {
            Response::fail('Invalid input', 422, $validator->errors());
        }

        $data = $validator->validated();
        $user = UserRepository::findByEmail($data['email']);

        // Generic message so we don't leak whether the email exists.
        if (!$user || $user['status'] !== 'ACTIVE') {
            Response::fail('Invalid email or password', 401);
        }

        if (!Password::verify($data['password'], $user['password_hash'])) {
            Response::fail('Invalid email or password', 401);
        }

        Auth::createSession([
            'userId'   => $user['id'],
            'schoolId' => $user['school_id'],
            'role'     => $user['role'],
            'email'    => $user['email'],
            'name'     => $user['name'],
        ]);

        UserRepository::touchLastLogin($user['id']);

        AuditLogger::log(
            $user['school_id'],
            $user['id'],
            'LOGIN',
            'user',
            $user['id'],
            $request->ip()
        );

        Response::ok([
            'id'       => $user['id'],
            'name'     => $user['name'],
            'email'    => $user['email'],
            'role'     => $user['role'],
            'schoolId' => $user['school_id'],
            'avatar'   => $user['avatar'],
        ]);
    }

    public static function logout(Request $request): void
    {
        $session = Auth::getSession();
        Auth::destroySession();

        if ($session !== null) {
            AuditLogger::log(
                $session['schoolId'],
                $session['userId'],
                'LOGOUT',
                'user',
                $session['userId']
            );
        }

        Response::ok(['message' => 'Signed out']);
    }

    public static function me(Request $request): void
    {
        $session = AuthMiddleware::session();

        $user = UserRepository::findByIdWithSchool($session['userId']);
        if ($user === null) {
            Response::notFound('User not found');
        }

        Response::ok($user);
    }
}
