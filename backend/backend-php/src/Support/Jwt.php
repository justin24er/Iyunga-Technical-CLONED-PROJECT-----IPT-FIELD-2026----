<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;

/**
 * Minimal, dependency-free HS256 JWT sign/verify.
 *
 * The original backend used the `jose` npm package to sign session tokens.
 * We don't pull in firebase/php-jwt (or any Composer package) here so the
 * project stays runnable on plain shared PHP hosting with zero install
 * step — HS256 is simple enough to implement correctly in ~60 lines using
 * only hash_hmac(), which ships with PHP core.
 *
 * Format is standard JWT: base64url(header).base64url(payload).base64url(signature)
 */
final class Jwt
{
    public static function encode(array $payload, string $secret, int $expiresInSeconds): string
    {
        $header = ['alg' => 'HS256', 'typ' => 'JWT'];

        $now = time();
        $fullPayload = array_merge($payload, [
            'iat' => $now,
            'exp' => $now + $expiresInSeconds,
        ]);

        $segments = [
            self::base64UrlEncode(json_encode($header, JSON_UNESCAPED_SLASHES)),
            self::base64UrlEncode(json_encode($fullPayload, JSON_UNESCAPED_SLASHES)),
        ];

        $signingInput = implode('.', $segments);
        $signature = hash_hmac('sha256', $signingInput, $secret, true);
        $segments[] = self::base64UrlEncode($signature);

        return implode('.', $segments);
    }

    /**
     * @return array<string,mixed>|null Returns the decoded payload, or null
     *                                   if the token is malformed, has an
     *                                   invalid signature, or has expired.
     */
    public static function decode(string $token, string $secret): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        [$headerB64, $payloadB64, $signatureB64] = $parts;

        $header = json_decode(self::base64UrlDecode($headerB64), true);
        if (!is_array($header) || ($header['alg'] ?? null) !== 'HS256') {
            return null;
        }

        $expectedSignature = hash_hmac('sha256', "{$headerB64}.{$payloadB64}", $secret, true);
        $actualSignature = self::base64UrlDecode($signatureB64);

        if (!hash_equals($expectedSignature, $actualSignature)) {
            return null;
        }

        $payload = json_decode(self::base64UrlDecode($payloadB64), true);
        if (!is_array($payload)) {
            return null;
        }

        if (isset($payload['exp']) && time() >= (int) $payload['exp']) {
            return null; // expired
        }

        return $payload;
    }

    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $data): string
    {
        $padded = str_pad($data, strlen($data) % 4 === 0 ? strlen($data) : strlen($data) + (4 - strlen($data) % 4), '=');
        $decoded = base64_decode(strtr($padded, '-_', '+/'), true);
        if ($decoded === false) {
            throw new RuntimeException('Invalid base64url segment in JWT');
        }
        return $decoded;
    }
}
