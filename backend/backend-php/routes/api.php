<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\StudentController;
use App\Core\Router;
use App\Middleware\AuthMiddleware;
use App\Middleware\RoleMiddleware;

/**
 * Route table. All paths are relative to /api (see public/index.php).
 * Mirrors the Next.js app/api/** file-based routes one-for-one so the
 * frontend's fetch() calls in assets/js/api.js need no changes:
 *
 *   app/api/auth/login/route.ts             -> POST /api/auth/login
 *   app/api/auth/logout/route.ts            -> POST /api/auth/logout
 *   app/api/auth/me/route.ts                -> GET  /api/auth/me
 *   app/api/dashboard/summary/route.ts      -> GET  /api/dashboard/summary
 *   app/api/dashboard/attendance/route.ts   -> GET  /api/dashboard/attendance
 *   app/api/dashboard/student-demographics/ -> GET  /api/dashboard/student-demographics
 *   app/api/dashboard/performance/route.ts  -> GET  /api/dashboard/performance
 *   app/api/students/route.ts               -> GET+POST /api/students
 *
 * @var Router $router injected by public/index.php
 */

// ---- Auth ----------------------------------------------------------------
$router->post('/api/auth/login', [AuthController::class, 'login']);
$router->post('/api/auth/logout', [AuthController::class, 'logout']);
$router->get('/api/auth/me', [AuthController::class, 'me'], [
    AuthMiddleware::handle(...),
]);

// ---- Dashboard -------------------------------------------------------------
$router->get('/api/dashboard/summary', [DashboardController::class, 'summary'], [
    AuthMiddleware::handle(...),
]);
$router->get('/api/dashboard/attendance', [DashboardController::class, 'attendance'], [
    AuthMiddleware::handle(...),
]);
$router->get('/api/dashboard/student-demographics', [DashboardController::class, 'studentDemographics'], [
    AuthMiddleware::handle(...),
]);
$router->get('/api/dashboard/performance', [DashboardController::class, 'performance'], [
    AuthMiddleware::handle(...),
]);

// ---- Students (reference CRUD module) --------------------------------------
$router->get('/api/students', [StudentController::class, 'index'], [
    AuthMiddleware::handle(...),
    RoleMiddleware::require('students:read'),
]);
$router->post('/api/students', [StudentController::class, 'store'], [
    AuthMiddleware::handle(...),
    RoleMiddleware::require('students:write'),
]);
