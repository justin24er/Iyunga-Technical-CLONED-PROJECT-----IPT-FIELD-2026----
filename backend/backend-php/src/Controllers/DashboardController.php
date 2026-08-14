<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Middleware\AuthMiddleware;
use App\Middleware\TenantMiddleware;
use App\Repositories\DashboardRepository;

/**
 * Port of app/api/dashboard/{summary,attendance,student-demographics,
 * performance}/route.ts.
 */
final class DashboardController
{
    public static function summary(Request $request): void
    {
        $session = AuthMiddleware::session();
        $schoolId = TenantMiddleware::schoolId($session);

        Response::ok(DashboardRepository::summary($schoolId));
    }

    public static function attendance(Request $request): void
    {
        $session = AuthMiddleware::session();
        $schoolId = TenantMiddleware::schoolId($session);

        $days = (int) ($request->query('range', '10'));
        if ($days <= 0) {
            $days = 10;
        }

        Response::ok(DashboardRepository::attendanceSeries($schoolId, $days));
    }

    public static function studentDemographics(Request $request): void
    {
        $session = AuthMiddleware::session();
        $schoolId = TenantMiddleware::schoolId($session);

        Response::ok(DashboardRepository::studentDemographics($schoolId));
    }

    public static function performance(Request $request): void
    {
        $session = AuthMiddleware::session();
        $schoolId = TenantMiddleware::schoolId($session);

        $page = max(1, (int) $request->query('page', '1'));
        $limit = max(1, min(100, (int) $request->query('limit', '10')));
        $classId = $request->query('classId') ?: null;
        $grade = $request->query('grade') ?: null;

        [$rows, $total] = DashboardRepository::performance($schoolId, $page, $limit, $classId, $grade);

        Response::paginated($rows, $page, $limit, $total);
    }
}
