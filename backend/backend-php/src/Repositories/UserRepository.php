<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Config\Database;
use PDO;

/**
 * All raw SQL for the `users` table lives here — controllers never write
 * SQL directly. Mirrors the subset of Prisma's `prisma.user.*` calls that
 * the original route handlers actually used.
 */
final class UserRepository
{
    public static function findByEmail(string $email): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT id, school_id, name, email, phone, password_hash, role, avatar, status, last_login, created_at, updated_at
             FROM users WHERE email = :email LIMIT 1'
        );
        $stmt->execute(['email' => $email]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Matches the field selection used by GET /api/auth/me, including the
     * joined school summary.
     */
    public static function findByIdWithSchool(string $id): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT
                u.id, u.name, u.email, u.phone, u.role, u.avatar, u.school_id,
                s.id AS school_id_join, s.name AS school_name, s.logo AS school_logo, s.currency AS school_currency
             FROM users u
             LEFT JOIN schools s ON s.id = u.school_id
             WHERE u.id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        $school = null;
        if ($row['school_id_join'] !== null) {
            $school = [
                'id'       => $row['school_id_join'],
                'name'     => $row['school_name'],
                'logo'     => $row['school_logo'],
                'currency' => $row['school_currency'],
            ];
        }

        return [
            'id'       => $row['id'],
            'name'     => $row['name'],
            'email'    => $row['email'],
            'phone'    => $row['phone'],
            'role'     => $row['role'],
            'avatar'   => $row['avatar'],
            'schoolId' => $row['school_id'],
            'school'   => $school,
        ];
    }

    public static function touchLastLogin(string $id): void
    {
        $stmt = Database::pdo()->prepare('UPDATE users SET last_login = NOW() WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }
}
