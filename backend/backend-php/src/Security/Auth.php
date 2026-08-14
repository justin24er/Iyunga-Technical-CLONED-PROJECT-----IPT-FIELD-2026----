<?php

declare(strict_types=1);

namespace App\Security;

use App\Config\Env;
use App\Support\Jwt;

/**
 * Session handling — direct port of lib/auth.ts's session concerns.
 *
 * Session is an HS256 JWT stored in an HTTP-only, SameSite=Lax cookie.
 * It is NEVER exposed to JS and NEVER stored in localStorage — the
 * frontend only ever talks to it implicitly via `credentials: "include"`
 * on fetch() calls (see frontend/assets/js/api.js, unchanged).
 */
final class Auth
{
    private const COOKIE_NAME = 'academia_session';
    private const MAX_AGE_SECONDS = 60 * 60 * 24 * 7; // 7 days

    /**
     * @param array{userId:string,schoolId:?string,role:string,email:string,name:string} $payload
     */
    public static function createSession(array $payload): void
    {
        $token = Jwt::encode($payload, self::secret(), self::MAX_AGE_SECONDS);

        setcookie(self::COOKIE_NAME, $token, [
            'expires'  => time() + self::MAX_AGE_SECONDS,
            'path'     => '/',
            'httponly' => true,
            'secure'   => Env::getBool('APP_HTTPS', false),
            'samesite' => 'Lax',
        ]);
    }

    public static function destroySession(): void
    {
        setcookie(self::COOKIE_NAME, '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'httponly' => true,
            'secure'   => Env::getBool('APP_HTTPS', false),
            'samesite' => 'Lax',
        ]);
    }

    /**
     * Reads and verifies the session cookie. This is the ONLY source of
     * truth for the authenticated user's schoolId and role — controllers
     * must never trust a schoolId sent from the client for tenant scoping.
     *
     * @return array{userId:string,schoolId:?string,role:string,email:string,name:string}|null
     */
    public static function getSession(): ?array
    {
        $token = $_COOKIE[self::COOKIE_NAME] ?? null;
        if (!$token) {
            return null;
        }

        $payload = Jwt::decode($token, self::secret());
        if ($payload === null) {
            return null;
        }

        if (!isset($payload['userId'], $payload['role'], $payload['email'], $payload['name'])) {
            return null;
        }

        return [
            'userId'   => (string) $payload['userId'],
            'schoolId' => isset($payload['schoolId']) && $payload['schoolId'] !== null
                ? (string) $payload['schoolId']
                : null,
            'role'     => (string) $payload['role'],
            'email'    => (string) $payload['email'],
            'name'     => (string) $payload['name'],
        ];
    }

    private static function secret(): string
    {
        $secret = Env::get('AUTH_SECRET');
        if (!$secret) {
            throw new \RuntimeException('AUTH_SECRET is not set');
        }
        return $secret;
    }
}
