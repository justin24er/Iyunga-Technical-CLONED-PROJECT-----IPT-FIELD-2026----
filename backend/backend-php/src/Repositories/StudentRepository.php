<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Config\Database;
use App\Support\Id;
use PDO;

/**
 * Port of the query logic in app/api/students/route.ts. The reference
 * implementation for every other future CRUD module — same pattern:
 * build a WHERE clause always anchored to the trusted schoolId, paginate,
 * count in parallel (here: two prepared statements back to back).
 */
final class StudentRepository
{
    /**
     * @return array{0: array<int,array<string,mixed>>, 1: int} [rows, total]
     */
    public static function search(
        string $schoolId,
        int $page,
        int $limit,
        ?string $search,
        ?string $classId,
        ?string $gender,
        ?string $status
    ): array {
        $conditions = ['s.school_id = :school_id'];
        $params = ['school_id' => $schoolId];

        if ($classId) {
            $conditions[] = 's.class_id = :class_id';
            $params['class_id'] = $classId;
        }

        if ($gender) {
            $conditions[] = 's.gender = :gender';
            $params['gender'] = $gender;
        }

        if ($status) {
            $conditions[] = 's.status = :status';
            $params['status'] = $status;
        }

        if ($search) {
            $conditions[] = '(s.first_name LIKE :search1 OR s.last_name LIKE :search2 OR s.student_number LIKE :search3)';
            $like = '%' . $search . '%';
            $params['search1'] = $like;
            $params['search2'] = $like;
            $params['search3'] = $like;
        }

        $whereSql = implode(' AND ', $conditions);
        $offset = ($page - 1) * $limit;

        $pdo = Database::pdo();

        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM students s WHERE {$whereSql}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $listSql = "SELECT
                s.id, s.student_number, s.first_name, s.middle_name, s.last_name, s.gender,
                s.date_of_birth, s.email, s.phone, s.address, s.photo, s.class_id,
                s.admission_date, s.status, s.created_at, s.updated_at,
                c.id AS class_id_join, c.name AS class_name
            FROM students s
            LEFT JOIN classes c ON c.id = s.class_id
            WHERE {$whereSql}
            ORDER BY s.created_at DESC
            LIMIT :limit OFFSET :offset";

        $listStmt = $pdo->prepare($listSql);
        foreach ($params as $key => $value) {
            $listStmt->bindValue($key, $value);
        }
        $listStmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $listStmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $listStmt->execute();

        $rows = array_map(self::mapRow(...), $listStmt->fetchAll());

        return [$rows, $total];
    }

    public static function findByStudentNumber(string $schoolId, string $studentNumber): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT id FROM students WHERE school_id = :school_id AND student_number = :student_number LIMIT 1'
        );
        $stmt->execute(['school_id' => $schoolId, 'student_number' => $studentNumber]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function create(string $schoolId, array $data): array
    {
        $id = Id::generate();

        $stmt = Database::pdo()->prepare(
            'INSERT INTO students
                (id, school_id, student_number, first_name, middle_name, last_name, gender,
                 date_of_birth, email, phone, address, class_id, parent_id, admission_date, status, created_at, updated_at)
             VALUES
                (:id, :school_id, :student_number, :first_name, :middle_name, :last_name, :gender,
                 :date_of_birth, :email, :phone, :address, :class_id, :parent_id, NOW(), \'ACTIVE\', NOW(), NOW())'
        );

        $stmt->execute([
            'id'             => $id,
            'school_id'      => $schoolId,
            'student_number' => $data['studentNumber'],
            'first_name'     => $data['firstName'],
            'middle_name'    => $data['middleName'] ?? null,
            'last_name'      => $data['lastName'],
            'gender'         => $data['gender'],
            'date_of_birth'  => $data['dateOfBirth'],
            'email'          => $data['email'] ?? null,
            'phone'          => $data['phone'] ?? null,
            'address'        => $data['address'] ?? null,
            'class_id'       => $data['classId'] ?? null,
            'parent_id'      => $data['parentId'] ?? null,
        ]);

        return self::findById($id) ?? [];
    }

    public static function findById(string $id): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT s.*, c.id AS class_id_join, c.name AS class_name
             FROM students s LEFT JOIN classes c ON c.id = s.class_id
             WHERE s.id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ? self::mapRow($row) : null;
    }

    /**
     * Normalizes a raw joined row into the camelCase shape the frontend
     * expects (matching Prisma's default JSON serialization).
     */
    private static function mapRow(array $row): array
    {
        return [
            'id'            => $row['id'],
            'studentNumber' => $row['student_number'],
            'firstName'     => $row['first_name'],
            'middleName'    => $row['middle_name'],
            'lastName'      => $row['last_name'],
            'gender'        => $row['gender'],
            'dateOfBirth'   => $row['date_of_birth'],
            'email'         => $row['email'],
            'phone'         => $row['phone'],
            'address'       => $row['address'],
            'photo'         => $row['photo'],
            'classId'       => $row['class_id'],
            'admissionDate' => $row['admission_date'],
            'status'        => $row['status'],
            'createdAt'     => $row['created_at'],
            'updatedAt'     => $row['updated_at'],
            'class'         => $row['class_id_join'] !== null
                ? ['id' => $row['class_id_join'], 'name' => $row['class_name']]
                : null,
        ];
    }
}
