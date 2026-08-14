<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Standard JSON response envelope, deliberately mirroring the original
 * TypeScript lib/response.ts so the frontend (assets/js/api.js) needs
 * zero changes:
 *
 *   success responses: { success: true, data }
 *   paginated:          { success: true, data, page, limit, total, totalPages }
 *   errors:             { success: false, message, errors }
 */
final class Response
{
    public static function ok(mixed $data, int $status = 200): never
    {
        self::send($status, ['success' => true, 'data' => $data]);
    }

    public static function paginated(array $data, int $page, int $limit, int $total): never
    {
        self::send(200, [
            'success'    => true,
            'data'       => $data,
            'page'       => $page,
            'limit'      => $limit,
            'total'      => $total,
            'totalPages' => max(1, (int) ceil($total / max(1, $limit))),
        ]);
    }

    public static function fail(string $message, int $status = 400, array $errors = []): never
    {
        self::send($status, ['success' => false, 'message' => $message, 'errors' => $errors]);
    }

    public static function unauthorized(string $message = 'You must be signed in'): never
    {
        self::fail($message, 401);
    }

    public static function forbidden(string $message = 'You do not have permission to perform this action'): never
    {
        self::fail($message, 403);
    }

    public static function notFound(string $message = 'Resource not found'): never
    {
        self::fail($message, 404);
    }

    public static function serverError(string $message = 'Something went wrong'): never
    {
        self::fail($message, 500);
    }

    /** Used to answer CORS preflight (OPTIONS) requests with no body. */
    public static function send204(): never
    {
        if (!headers_sent()) {
            http_response_code(204);
        }
        exit;
    }

    private static function send(int $status, array $payload): never
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }
}
