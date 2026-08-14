<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Config\Database;
use PDO;

/**
 * All queries backing GET /api/dashboard/*. Every number here is real and
 * DB-driven — zero hardcoded values — same principle stated in the
 * original README. Ports app/api/dashboard/{summary,attendance,
 * student-demographics,performance}/route.ts one-for-one.
 */
final class DashboardRepository
{
    public static function summary(string $schoolId): array
    {
        $pdo = Database::pdo();

        $totalStudents = self::scalar($pdo,
            'SELECT COUNT(*) FROM students WHERE school_id = :s AND status = "ACTIVE"', $schoolId);

        $totalTeachers = self::scalar($pdo,
            'SELECT COUNT(*) FROM teachers WHERE school_id = :s AND status = "ACTIVE"', $schoolId);

        $totalSubjects = self::scalar($pdo,
            'SELECT COUNT(*) FROM subjects WHERE school_id = :s AND status = "ACTIVE"', $schoolId);

        $totalRevenue = self::scalar($pdo,
            'SELECT COALESCE(SUM(amount), 0) FROM payments WHERE school_id = :s', $schoolId);

        $outstandingStmt = $pdo->prepare(
            'SELECT COALESCE(SUM(total_amount), 0) AS total, COALESCE(SUM(paid_amount), 0) AS paid
             FROM invoices WHERE school_id = :s AND status IN ("UNPAID", "PARTIAL", "OVERDUE")'
        );
        $outstandingStmt->execute(['s' => $schoolId]);
        $outstandingRow = $outstandingStmt->fetch();
        $outstandingFees = (float) $outstandingRow['total'] - (float) $outstandingRow['paid'];

        $eventsStmt = $pdo->prepare(
            'SELECT id, title, description, date, location, event_type
             FROM events WHERE school_id = :s AND date >= NOW()
             ORDER BY date ASC LIMIT 5'
        );
        $eventsStmt->execute(['s' => $schoolId]);
        $upcomingEvents = $eventsStmt->fetchAll();

        // Attendance percentage over the last 30 days.
        $present = self::scalar($pdo,
            'SELECT COUNT(*) FROM attendance WHERE school_id = :s AND date >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND status = "PRESENT"',
            $schoolId);
        $totalMarked = self::scalar($pdo,
            'SELECT COUNT(*) FROM attendance WHERE school_id = :s AND date >= DATE_SUB(NOW(), INTERVAL 30 DAY)',
            $schoolId);

        return [
            'totalStudents'        => (int) $totalStudents,
            'totalTeachers'        => (int) $totalTeachers,
            'totalSubjects'        => (int) $totalSubjects,
            'totalRevenue'         => (float) $totalRevenue,
            'outstandingFees'      => $outstandingFees,
            'attendancePercentage' => $totalMarked > 0 ? round(($present / $totalMarked) * 1000) / 10 : 0,
            'upcomingEvents'       => $upcomingEvents,
        ];
    }

    /** @return array<int,array{date:string,present:int,absent:int,percentage:float}> */
    public static function attendanceSeries(string $schoolId, int $days): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT date, status, COUNT(*) AS cnt
             FROM attendance
             WHERE school_id = :s AND date >= DATE_SUB(NOW(), INTERVAL :days DAY)
             GROUP BY date, status
             ORDER BY date ASC'
        );
        $stmt->bindValue('s', $schoolId);
        $stmt->bindValue('days', $days, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        $byDate = [];
        foreach ($rows as $row) {
            $key = substr($row['date'], 0, 10);
            if (!isset($byDate[$key])) {
                $byDate[$key] = ['date' => $key, 'present' => 0, 'absent' => 0];
            }
            if ($row['status'] === 'PRESENT') {
                $byDate[$key]['present'] += (int) $row['cnt'];
            }
            if ($row['status'] === 'ABSENT') {
                $byDate[$key]['absent'] += (int) $row['cnt'];
            }
        }

        return array_values(array_map(static function (array $d): array {
            $marked = $d['present'] + $d['absent'];
            $d['percentage'] = $marked > 0 ? round(($d['present'] / $marked) * 1000) / 10 : 0;
            return $d;
        }, $byDate));
    }

    public static function studentDemographics(string $schoolId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT gender, COUNT(*) AS cnt FROM students
             WHERE school_id = :s AND status = "ACTIVE"
             GROUP BY gender'
        );
        $stmt->execute(['s' => $schoolId]);
        $rows = $stmt->fetchAll();

        $girls = 0;
        $boys = 0;
        $other = 0;
        foreach ($rows as $row) {
            match ($row['gender']) {
                'FEMALE' => $girls = (int) $row['cnt'],
                'MALE'   => $boys = (int) $row['cnt'],
                'OTHER'  => $other = (int) $row['cnt'],
                default  => null,
            };
        }

        return ['total' => $girls + $boys + $other, 'girls' => $girls, 'boys' => $boys, 'other' => $other];
    }

    /**
     * @return array{0: array<int,array<string,mixed>>, 1: int} [rows, total]
     */
    public static function performance(string $schoolId, int $page, int $limit, ?string $classId, ?string $grade): array
    {
        $conditions = ['r.school_id = :s', 'r.status = "APPROVED"'];
        $params = ['s' => $schoolId];

        if ($grade) {
            $conditions[] = 'r.grade = :grade';
            $params['grade'] = $grade;
        }

        if ($classId) {
            $conditions[] = 'st.class_id = :class_id';
            $params['class_id'] = $classId;
        }

        $whereSql = implode(' AND ', $conditions);
        $offset = ($page - 1) * $limit;

        $pdo = Database::pdo();

        $countStmt = $pdo->prepare(
            "SELECT COUNT(*) FROM results r JOIN students st ON st.id = r.student_id WHERE {$whereSql}"
        );
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $listStmt = $pdo->prepare(
            "SELECT
                st.id AS student_id, st.first_name, st.last_name, st.student_number, st.photo,
                c.name AS class_name, r.grade, r.marks, r.status
             FROM results r
             JOIN students st ON st.id = r.student_id
             LEFT JOIN classes c ON c.id = st.class_id
             WHERE {$whereSql}
             ORDER BY r.marks DESC
             LIMIT :limit OFFSET :offset"
        );
        foreach ($params as $key => $value) {
            $listStmt->bindValue($key, $value);
        }
        $listStmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $listStmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $listStmt->execute();

        $rows = array_map(static fn (array $r): array => [
            'studentId'     => $r['student_id'],
            'name'          => trim($r['first_name'] . ' ' . $r['last_name']),
            'studentNumber' => $r['student_number'],
            'photo'         => $r['photo'],
            'class'         => $r['class_name'] ?? '—',
            'grade'         => $r['grade'],
            'percentage'    => (float) $r['marks'],
            'status'        => $r['status'],
        ], $listStmt->fetchAll());

        return [$rows, $total];
    }

    private static function scalar(PDO $pdo, string $sql, string $schoolId): int|float
    {
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['s' => $schoolId]);
        return $stmt->fetchColumn() + 0; // numeric coercion, matches Number(x ?? 0)
    }
}
