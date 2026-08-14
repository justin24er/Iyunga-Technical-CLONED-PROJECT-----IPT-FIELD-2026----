<?php

declare(strict_types=1);

namespace App\Security;

/**
 * Resource-level permission matrix — a direct, one-to-one port of
 * lib/permissions.ts. Add a new resource key here whenever a new module
 * route is built (Phase 10+), exactly as the original comment instructed.
 */
final class Permissions
{
    /** @var array<string, array<int,string>> */
    private const MATRIX = [
        'schools:manage' => ['SUPER_ADMIN'],
        'school:update'  => ['SUPER_ADMIN', 'SCHOOL_ADMIN'],
        'users:manage'   => ['SUPER_ADMIN', 'SCHOOL_ADMIN'],

        'students:read'  => ['SUPER_ADMIN', 'SCHOOL_ADMIN', 'HEADMASTER', 'ACCOUNTANT', 'TEACHER', 'STUDENT', 'PARENT'],
        'students:write' => ['SCHOOL_ADMIN'],

        'teachers:read'  => ['SUPER_ADMIN', 'SCHOOL_ADMIN', 'HEADMASTER'],
        'teachers:write' => ['SCHOOL_ADMIN'],

        'classes:manage'  => ['SCHOOL_ADMIN'],
        'subjects:manage' => ['SCHOOL_ADMIN'],

        'attendance:mark' => ['SCHOOL_ADMIN', 'TEACHER'],
        'attendance:read' => ['SUPER_ADMIN', 'SCHOOL_ADMIN', 'HEADMASTER', 'TEACHER', 'STUDENT', 'PARENT'],

        'exams:manage'    => ['SCHOOL_ADMIN'],
        'results:enter'   => ['TEACHER'],
        'results:approve' => ['HEADMASTER', 'SCHOOL_ADMIN'],
        'results:read'    => ['SUPER_ADMIN', 'SCHOOL_ADMIN', 'HEADMASTER', 'TEACHER', 'STUDENT', 'PARENT'],

        'fees:manage'     => ['SCHOOL_ADMIN', 'ACCOUNTANT'],
        'payments:record' => ['ACCOUNTANT', 'SCHOOL_ADMIN'],
        'expenses:manage' => ['ACCOUNTANT', 'SCHOOL_ADMIN'],

        'reports:financial' => ['SCHOOL_ADMIN', 'ACCOUNTANT', 'HEADMASTER'],
        'reports:academic'  => ['SCHOOL_ADMIN', 'HEADMASTER', 'TEACHER'],

        'settings:manage' => ['SCHOOL_ADMIN'],
        'audit:read'      => ['SUPER_ADMIN', 'SCHOOL_ADMIN'],
    ];

    public static function can(string $role, string $permission): bool
    {
        return in_array($role, self::MATRIX[$permission] ?? [], true);
    }

    /** @return array<int,string> */
    public static function rolesFor(string $permission): array
    {
        return self::MATRIX[$permission] ?? [];
    }
}
