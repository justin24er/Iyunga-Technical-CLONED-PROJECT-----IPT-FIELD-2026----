<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Response;

/**
 * CRITICAL MULTI-TENANCY RULE (unchanged from tenant.middleware.ts):
 * schoolId used in every query MUST come from the authenticated session,
 * never from the request body, query string, or URL params.
 *
 * This is not wired in as a router middleware (it needs to return a value,
 * not just pass/fail), so controllers call TenantMiddleware::schoolId($session)
 * directly, mirroring the original requireSchoolScope(session) helper.
 */
final class TenantMiddleware
{
    /**
     * Returns the trusted schoolId for the current session, or sends a 403
     * and halts execution if the account has no valid tenant scope.
     */
    public static function schoolId(array $session): string
    {
        if ($session['role'] === 'SUPER_ADMIN') {
            // SUPER_ADMIN routes should explicitly pass schoolId as a route
            // param (e.g. GET /api/schools/{id}/students), validated
            // against the schools table — never trusted blindly either.
            Response::forbidden('SUPER_ADMIN must access tenant data via /api/schools/{id}/* routes');
        }

        if (empty($session['schoolId'])) {
            Response::forbidden('No school context on this account');
        }

        return $session['schoolId'];
    }
}
