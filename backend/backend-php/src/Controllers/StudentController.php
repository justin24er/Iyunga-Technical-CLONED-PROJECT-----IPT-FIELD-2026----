<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Logging\AuditLogger;
use App\Middleware\AuthMiddleware;
use App\Middleware\TenantMiddleware;
use App\Repositories\StudentRepository;
use App\Support\Validator;

/**
 * Port of app/api/students/route.ts — the reference CRUD implementation
 * the original project used as the pattern for every future module
 * (teachers, classes, exams, fees, etc — see README Roadmap).
 */
final class StudentController
{
    // GET /api/students?page=1&limit=20&search=&classId=&gender=&status=
    public static function index(Request $request): void
    {
        $session = AuthMiddleware::session();
        $schoolId = TenantMiddleware::schoolId($session);

        $paginationValidator = new Validator($request->allQuery(), [
            'page'   => ['int', 'positive'],
            'limit'  => ['int', 'positive', 'max:100'],
            'search' => ['string'],
        ]);

        if ($paginationValidator->fails()) {
            Response::fail('Invalid input', 422, $paginationValidator->errors());
        }

        $params = $paginationValidator->withDefaults(['page' => 1, 'limit' => 20]);
        $page = (int) $params['page'];
        $limit = (int) $params['limit'];
        $search = $params['search'] ?? null;

        $classId = $request->query('classId') ?: null;
        $gender = $request->query('gender') ?: null;
        $status = $request->query('status') ?: null;

        [$rows, $total] = StudentRepository::search($schoolId, $page, $limit, $search, $classId, $gender, $status);

        Response::paginated($rows, $page, $limit, $total);
    }

    // POST /api/students
    public static function store(Request $request): void
    {
        $session = AuthMiddleware::session();
        $schoolId = TenantMiddleware::schoolId($session);

        $validator = new Validator($request->allInput(), [
            'studentNumber' => ['required', 'string', 'min:1'],
            'firstName'     => ['required', 'string', 'min:1'],
            'middleName'    => ['string'],
            'lastName'      => ['required', 'string', 'min:1'],
            'gender'        => ['required', 'in:MALE,FEMALE,OTHER'],
            'dateOfBirth'   => ['required', 'date'],
            'email'         => ['email'],
            'phone'         => ['string'],
            'address'       => ['string'],
            'classId'       => ['string'],
            'parentId'      => ['string'],
        ]);

        if ($validator->fails()) {
            Response::fail('Invalid input', 422, $validator->errors());
        }

        $data = $validator->validated();
        // Normalize the date to Y-m-d for storage.
        $data['dateOfBirth'] = date('Y-m-d', strtotime($data['dateOfBirth']));

        $existing = StudentRepository::findByStudentNumber($schoolId, $data['studentNumber']);
        if ($existing !== null) {
            Response::fail('A student with this student number already exists', 409);
        }

        $student = StudentRepository::create($schoolId, $data);

        AuditLogger::log(
            $schoolId,
            $session['userId'],
            'CREATE_STUDENT',
            'student',
            $student['id'] ?? null
        );

        Response::ok($student, 201);
    }
}
