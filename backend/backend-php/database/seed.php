<?php

declare(strict_types=1);

/**
 * Demo data seeder — port of backend/prisma/seed.ts.
 * Run from the project root:
 *
 *   php database/seed.php
 *
 * DEMO CREDENTIALS — for local development only. Change or remove before
 * production, exactly as the original seed script warned.
 */

require __DIR__ . '/../bootstrap.php';

use App\Config\Database;
use App\Support\Id;
use App\Support\Password;

const DEMO_PASSWORD = 'Password123!';

function upsertSchool(PDO $pdo, array $data): string
{
    $stmt = $pdo->prepare('SELECT id FROM schools WHERE code = :code');
    $stmt->execute(['code' => $data['code']]);
    $existing = $stmt->fetchColumn();
    if ($existing) {
        return (string) $existing;
    }

    $id = Id::generate();
    $stmt = $pdo->prepare(
        'INSERT INTO schools (id, name, code, email, city, region, country, currency, status, created_at, updated_at)
         VALUES (:id, :name, :code, :email, :city, :region, :country, :currency, :status, NOW(), NOW())'
    );
    $stmt->execute([
        'id'       => $id,
        'name'     => $data['name'],
        'code'     => $data['code'],
        'email'    => $data['email'],
        'city'     => $data['city'],
        'region'   => $data['region'],
        'country'  => $data['country'],
        'currency' => $data['currency'],
        'status'   => $data['status'],
    ]);
    return $id;
}

function upsertUser(PDO $pdo, ?string $schoolId, string $name, string $email, string $role, string $passwordHash): string
{
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email');
    $stmt->execute(['email' => $email]);
    $existing = $stmt->fetchColumn();
    if ($existing) {
        return (string) $existing;
    }

    $id = Id::generate();
    $stmt = $pdo->prepare(
        'INSERT INTO users (id, school_id, name, email, password_hash, role, status, created_at, updated_at)
         VALUES (:id, :school_id, :name, :email, :password_hash, :role, "ACTIVE", NOW(), NOW())'
    );
    $stmt->execute([
        'id'            => $id,
        'school_id'     => $schoolId,
        'name'          => $name,
        'email'         => $email,
        'password_hash' => $passwordHash,
        'role'          => $role,
    ]);
    return $id;
}

function main(): void
{
    $pdo = Database::pdo();
    $passwordHash = Password::hash(DEMO_PASSWORD);

    // ---- Super Admin (no school) ----
    upsertUser($pdo, null, 'Platform Super Admin', 'superadmin@example.com', 'SUPER_ADMIN', $passwordHash);

    // ---- School 1: Academia International School ----
    $school1Id = upsertSchool($pdo, [
        'name' => 'Academia International School', 'code' => 'AIS',
        'email' => 'info@academia-intl.example', 'city' => 'Dar es Salaam',
        'region' => 'Dar es Salaam', 'country' => 'Tanzania', 'currency' => 'TZS', 'status' => 'ACTIVE',
    ]);

    $yearId = Id::generate();
    $pdo->prepare(
        'INSERT INTO academic_years (id, school_id, name, start_date, end_date, is_current)
         VALUES (:id, :school_id, "2025/2026", "2025-09-01", "2026-06-30", 1)
         ON DUPLICATE KEY UPDATE id = id'
    )->execute(['id' => $yearId, 'school_id' => $school1Id]);

    $roles = [
        ['email' => 'admin@example.com', 'name' => 'Grace Mwangi', 'role' => 'SCHOOL_ADMIN'],
        ['email' => 'headmaster@example.com', 'name' => 'Daniel Kessy', 'role' => 'HEADMASTER'],
        ['email' => 'accountant@example.com', 'name' => 'Neema Mushi', 'role' => 'ACCOUNTANT'],
        ['email' => 'teacher@example.com', 'name' => 'Jack Snyder', 'role' => 'TEACHER'],
        ['email' => 'student@example.com', 'name' => 'Frances Swann', 'role' => 'STUDENT'],
        ['email' => 'parent@example.com', 'name' => 'Peter Swann', 'role' => 'PARENT'],
    ];

    foreach ($roles as $r) {
        upsertUser($pdo, $school1Id, $r['name'], $r['email'], $r['role'], $passwordHash);
    }

    $classId = Id::generate();
    $pdo->prepare(
        'INSERT INTO classes (id, school_id, name, level, academic_year_id, capacity, status)
         VALUES (:id, :school_id, "Form 1A", "Form 1", :year_id, 40, "ACTIVE")'
    )->execute(['id' => $classId, 'school_id' => $school1Id, 'year_id' => $yearId]);

    foreach ([['Mathematics', 'MATH'], ['English', 'ENG'], ['Biology', 'BIO']] as [$name, $code]) {
        $exists = $pdo->prepare('SELECT id FROM subjects WHERE school_id = :s AND code = :c');
        $exists->execute(['s' => $school1Id, 'c' => $code]);
        if ($exists->fetchColumn()) {
            continue;
        }
        $pdo->prepare(
            'INSERT INTO subjects (id, school_id, name, code, status) VALUES (:id, :school_id, :name, :code, "ACTIVE")'
        )->execute(['id' => Id::generate(), 'school_id' => $school1Id, 'name' => $name, 'code' => $code]);
    }

    // ---- School 2: Mbeya Modern School ----
    upsertSchool($pdo, [
        'name' => 'Mbeya Modern School', 'code' => 'MMS',
        'email' => 'info@mbeyamodern.example', 'city' => 'Mbeya',
        'region' => 'Mbeya', 'country' => 'Tanzania', 'currency' => 'TZS', 'status' => 'ACTIVE',
    ]);

    echo "Seed complete.\n";
    echo 'Demo login password for all seeded users: ' . DEMO_PASSWORD . "\n";
    echo "Class created: Form 1A\n";
}

main();
