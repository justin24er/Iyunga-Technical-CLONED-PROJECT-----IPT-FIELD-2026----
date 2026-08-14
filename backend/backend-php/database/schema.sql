-- ==========================================================
-- ACADEMIA — Multi-Tenant School Management SaaS
-- MySQL / MariaDB schema
--
-- Complete, one-to-one port of backend/prisma/schema.prisma.
-- Every table, enum, index, and foreign key from the original Prisma
-- model is represented here, even though only a subset (users, schools,
-- students, classes, subjects, teachers, attendance, results, events,
-- invoices, payments, audit_logs) is currently wired up to PHP endpoints.
-- The rest exists so Phase 10+ modules (see project README Roadmap) can
-- be built against a complete data model, exactly as the original
-- project intended.
--
-- IDs: CHAR(25) app-generated strings (see src/Support/Id.php), matching
-- the length/shape of Prisma's default cuid() ids.
-- ==========================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------
-- PLATFORM: SCHOOLS (TENANTS)
-- ---------------------------------------------------------

CREATE TABLE schools (
    id                  CHAR(25)     NOT NULL PRIMARY KEY,
    name                VARCHAR(255) NOT NULL,
    code                VARCHAR(50)  NOT NULL UNIQUE,
    email               VARCHAR(255) NULL,
    phone               VARCHAR(50)  NULL,
    address             VARCHAR(255) NULL,
    city                VARCHAR(120) NULL,
    region              VARCHAR(120) NULL,
    country             VARCHAR(120) NOT NULL DEFAULT 'Tanzania',
    logo                VARCHAR(255) NULL,
    website             VARCHAR(255) NULL,
    registration_number VARCHAR(120) NULL,
    currency            VARCHAR(10)  NOT NULL DEFAULT 'TZS',
    status              ENUM('ACTIVE','SUSPENDED','TRIAL','CANCELLED') NOT NULL DEFAULT 'TRIAL',
    created_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE school_settings (
    id                  CHAR(25) NOT NULL PRIMARY KEY,
    school_id           CHAR(25) NOT NULL UNIQUE,
    academic_year_id    CHAR(25) NULL,
    current_term_id     CHAR(25) NULL,
    attendance_settings JSON NULL,
    fee_settings        JSON NULL,
    notification_prefs  JSON NULL,
    updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_school_settings_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------
-- USERS & AUTH
-- ---------------------------------------------------------

CREATE TABLE users (
    id            CHAR(25) NOT NULL PRIMARY KEY,
    school_id     CHAR(25) NULL, -- null for SUPER_ADMIN
    name          VARCHAR(255) NOT NULL,
    email         VARCHAR(255) NOT NULL UNIQUE,
    phone         VARCHAR(50) NULL,
    password_hash VARCHAR(255) NOT NULL,
    role          ENUM('SUPER_ADMIN','SCHOOL_ADMIN','HEADMASTER','ACCOUNTANT','TEACHER','STUDENT','PARENT') NOT NULL,
    avatar        VARCHAR(255) NULL,
    status        ENUM('ACTIVE','INACTIVE','SUSPENDED') NOT NULL DEFAULT 'ACTIVE',
    last_login    DATETIME NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_users_school_id (school_id),
    CONSTRAINT fk_users_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------
-- ACADEMIC STRUCTURE
-- ---------------------------------------------------------

CREATE TABLE academic_years (
    id         CHAR(25) NOT NULL PRIMARY KEY,
    school_id  CHAR(25) NOT NULL,
    name       VARCHAR(50) NOT NULL, -- e.g. "2025/2026"
    start_date DATE NOT NULL,
    end_date   DATE NOT NULL,
    is_current TINYINT(1) NOT NULL DEFAULT 0,
    INDEX idx_academic_years_school_id (school_id),
    CONSTRAINT fk_academic_years_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE terms (
    id               CHAR(25) NOT NULL PRIMARY KEY,
    academic_year_id CHAR(25) NOT NULL,
    name             VARCHAR(50) NOT NULL, -- Term 1, Term 2, Term 3
    start_date       DATE NOT NULL,
    end_date         DATE NOT NULL,
    CONSTRAINT fk_terms_academic_year FOREIGN KEY (academic_year_id) REFERENCES academic_years(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE parents (
    id         CHAR(25) NOT NULL PRIMARY KEY,
    school_id  CHAR(25) NOT NULL,
    user_id    CHAR(25) NULL UNIQUE,
    first_name VARCHAR(120) NOT NULL,
    last_name  VARCHAR(120) NOT NULL,
    email      VARCHAR(255) NULL,
    phone      VARCHAR(50) NULL,
    address    VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_parents_school_id (school_id),
    CONSTRAINT fk_parents_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
    CONSTRAINT fk_parents_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE teachers (
    id              CHAR(25) NOT NULL PRIMARY KEY,
    school_id       CHAR(25) NOT NULL,
    user_id         CHAR(25) NULL UNIQUE,
    employee_number VARCHAR(50) NOT NULL,
    first_name      VARCHAR(120) NOT NULL,
    middle_name     VARCHAR(120) NULL,
    last_name       VARCHAR(120) NOT NULL,
    gender          ENUM('MALE','FEMALE','OTHER') NOT NULL,
    email           VARCHAR(255) NULL,
    phone           VARCHAR(50) NULL,
    address         VARCHAR(255) NULL,
    qualification   VARCHAR(255) NULL,
    specialization  VARCHAR(255) NULL,
    joining_date    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    salary          DECIMAL(12,2) NULL,
    photo           VARCHAR(255) NULL,
    status          ENUM('ACTIVE','INACTIVE','ON_LEAVE','TERMINATED') NOT NULL DEFAULT 'ACTIVE',
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_teachers_school_employee (school_id, employee_number),
    INDEX idx_teachers_school_id (school_id),
    CONSTRAINT fk_teachers_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
    CONSTRAINT fk_teachers_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE classes (
    id               CHAR(25) NOT NULL PRIMARY KEY,
    school_id        CHAR(25) NOT NULL,
    name             VARCHAR(120) NOT NULL, -- Form 1A
    level            VARCHAR(120) NOT NULL, -- Form 1
    academic_year_id CHAR(25) NOT NULL,
    class_teacher_id CHAR(25) NULL,
    capacity         INT NOT NULL DEFAULT 40,
    status           VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
    INDEX idx_classes_school_id (school_id),
    CONSTRAINT fk_classes_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
    CONSTRAINT fk_classes_academic_year FOREIGN KEY (academic_year_id) REFERENCES academic_years(id),
    CONSTRAINT fk_classes_teacher FOREIGN KEY (class_teacher_id) REFERENCES teachers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE subjects (
    id          CHAR(25) NOT NULL PRIMARY KEY,
    school_id   CHAR(25) NOT NULL,
    name        VARCHAR(120) NOT NULL,
    code        VARCHAR(30) NOT NULL,
    description VARCHAR(255) NULL,
    status      VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
    UNIQUE KEY uq_subjects_school_code (school_id, code),
    INDEX idx_subjects_school_id (school_id),
    CONSTRAINT fk_subjects_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE class_subjects (
    id         CHAR(25) NOT NULL PRIMARY KEY,
    class_id   CHAR(25) NOT NULL,
    subject_id CHAR(25) NOT NULL,
    teacher_id CHAR(25) NULL,
    UNIQUE KEY uq_class_subjects (class_id, subject_id),
    CONSTRAINT fk_class_subjects_class FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    CONSTRAINT fk_class_subjects_subject FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    CONSTRAINT fk_class_subjects_teacher FOREIGN KEY (teacher_id) REFERENCES teachers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE teacher_subjects (
    id         CHAR(25) NOT NULL PRIMARY KEY,
    teacher_id CHAR(25) NOT NULL,
    subject_id CHAR(25) NOT NULL,
    UNIQUE KEY uq_teacher_subjects (teacher_id, subject_id),
    CONSTRAINT fk_teacher_subjects_teacher FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
    CONSTRAINT fk_teacher_subjects_subject FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------
-- PEOPLE: STUDENTS
-- ---------------------------------------------------------

CREATE TABLE students (
    id             CHAR(25) NOT NULL PRIMARY KEY,
    school_id      CHAR(25) NOT NULL,
    user_id        CHAR(25) NULL UNIQUE,
    student_number VARCHAR(50) NOT NULL,
    first_name     VARCHAR(120) NOT NULL,
    middle_name    VARCHAR(120) NULL,
    last_name      VARCHAR(120) NOT NULL,
    gender         ENUM('MALE','FEMALE','OTHER') NOT NULL,
    date_of_birth  DATE NOT NULL,
    email          VARCHAR(255) NULL,
    phone          VARCHAR(50) NULL,
    address        VARCHAR(255) NULL,
    photo          VARCHAR(255) NULL,
    parent_id      CHAR(25) NULL,
    class_id       CHAR(25) NULL,
    admission_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status         ENUM('ACTIVE','INACTIVE','GRADUATED','SUSPENDED','TRANSFERRED') NOT NULL DEFAULT 'ACTIVE',
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_students_school_number (school_id, student_number),
    INDEX idx_students_school_id (school_id),
    INDEX idx_students_class_id (class_id),
    CONSTRAINT fk_students_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
    CONSTRAINT fk_students_user FOREIGN KEY (user_id) REFERENCES users(id),
    CONSTRAINT fk_students_parent FOREIGN KEY (parent_id) REFERENCES parents(id),
    CONSTRAINT fk_students_class FOREIGN KEY (class_id) REFERENCES classes(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------
-- ATTENDANCE
-- ---------------------------------------------------------

CREATE TABLE attendance (
    id         CHAR(25) NOT NULL PRIMARY KEY,
    school_id  CHAR(25) NOT NULL,
    student_id CHAR(25) NOT NULL,
    class_id   CHAR(25) NOT NULL,
    date       DATE NOT NULL,
    status     ENUM('PRESENT','ABSENT','LATE','EXCUSED') NOT NULL,
    marked_by  CHAR(25) NOT NULL,
    remarks    VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_attendance_student_date (student_id, date),
    INDEX idx_attendance_school_date (school_id, date),
    CONSTRAINT fk_attendance_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
    CONSTRAINT fk_attendance_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    CONSTRAINT fk_attendance_class FOREIGN KEY (class_id) REFERENCES classes(id),
    CONSTRAINT fk_attendance_marker FOREIGN KEY (marked_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------
-- EXAMS & RESULTS
-- ---------------------------------------------------------

CREATE TABLE exams (
    id               CHAR(25) NOT NULL PRIMARY KEY,
    school_id        CHAR(25) NOT NULL,
    name             VARCHAR(120) NOT NULL,
    academic_year_id CHAR(25) NOT NULL,
    term_id          CHAR(25) NULL,
    start_date       DATE NOT NULL,
    end_date         DATE NOT NULL,
    status           ENUM('DRAFT','SCHEDULED','ONGOING','COMPLETED','PUBLISHED') NOT NULL DEFAULT 'DRAFT',
    INDEX idx_exams_school_id (school_id),
    CONSTRAINT fk_exams_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
    CONSTRAINT fk_exams_academic_year FOREIGN KEY (academic_year_id) REFERENCES academic_years(id),
    CONSTRAINT fk_exams_term FOREIGN KEY (term_id) REFERENCES terms(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE exam_subjects (
    id         CHAR(25) NOT NULL PRIMARY KEY,
    exam_id    CHAR(25) NOT NULL,
    subject_id CHAR(25) NOT NULL,
    max_marks  INT NOT NULL DEFAULT 100,
    UNIQUE KEY uq_exam_subjects (exam_id, subject_id),
    CONSTRAINT fk_exam_subjects_exam FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE,
    CONSTRAINT fk_exam_subjects_subject FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE results (
    id          CHAR(25) NOT NULL PRIMARY KEY,
    school_id   CHAR(25) NOT NULL,
    student_id  CHAR(25) NOT NULL,
    exam_id     CHAR(25) NOT NULL,
    subject_id  CHAR(25) NOT NULL,
    marks       DECIMAL(5,2) NOT NULL,
    grade       VARCHAR(10) NULL,
    remarks     VARCHAR(255) NULL,
    entered_by  CHAR(25) NOT NULL,
    approved_by CHAR(25) NULL,
    status      ENUM('PENDING','ENTERED','APPROVED','REJECTED') NOT NULL DEFAULT 'PENDING',
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_results_student_exam_subject (student_id, exam_id, subject_id),
    INDEX idx_results_school_id (school_id),
    CONSTRAINT fk_results_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
    CONSTRAINT fk_results_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    CONSTRAINT fk_results_exam FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE,
    CONSTRAINT fk_results_subject FOREIGN KEY (subject_id) REFERENCES subjects(id),
    CONSTRAINT fk_results_enterer FOREIGN KEY (entered_by) REFERENCES users(id),
    CONSTRAINT fk_results_approver FOREIGN KEY (approved_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE grading_scales (
    id        CHAR(25) NOT NULL PRIMARY KEY,
    school_id CHAR(25) NOT NULL,
    grade     VARCHAR(10) NOT NULL, -- A, B, C...
    min_marks INT NOT NULL,
    max_marks INT NOT NULL,
    points    FLOAT NULL,
    remark    VARCHAR(255) NULL,
    INDEX idx_grading_scales_school_id (school_id),
    CONSTRAINT fk_grading_scales_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------
-- FEES & FINANCE
-- ---------------------------------------------------------

CREATE TABLE fee_structures (
    id               CHAR(25) NOT NULL PRIMARY KEY,
    school_id        CHAR(25) NOT NULL,
    class_id         CHAR(25) NOT NULL,
    academic_year_id CHAR(25) NOT NULL,
    term             VARCHAR(50) NOT NULL,
    tuition_fee      DECIMAL(12,2) NOT NULL DEFAULT 0,
    exam_fee         DECIMAL(12,2) NOT NULL DEFAULT 0,
    transport_fee    DECIMAL(12,2) NOT NULL DEFAULT 0,
    library_fee      DECIMAL(12,2) NOT NULL DEFAULT 0,
    uniform_fee      DECIMAL(12,2) NOT NULL DEFAULT 0,
    other_fee        DECIMAL(12,2) NOT NULL DEFAULT 0,
    total            DECIMAL(12,2) NOT NULL,
    INDEX idx_fee_structures_school_id (school_id),
    CONSTRAINT fk_fee_structures_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
    CONSTRAINT fk_fee_structures_class FOREIGN KEY (class_id) REFERENCES classes(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE invoices (
    id             CHAR(25) NOT NULL PRIMARY KEY,
    school_id      CHAR(25) NOT NULL,
    student_id     CHAR(25) NOT NULL,
    class_id       CHAR(25) NULL,
    term_id        CHAR(25) NULL,
    invoice_number VARCHAR(50) NOT NULL UNIQUE,
    total_amount   DECIMAL(12,2) NOT NULL,
    paid_amount    DECIMAL(12,2) NOT NULL DEFAULT 0,
    due_date       DATE NOT NULL,
    status         ENUM('UNPAID','PARTIAL','PAID','OVERDUE','CANCELLED') NOT NULL DEFAULT 'UNPAID',
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_invoices_school_id (school_id),
    CONSTRAINT fk_invoices_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
    CONSTRAINT fk_invoices_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    CONSTRAINT fk_invoices_class FOREIGN KEY (class_id) REFERENCES classes(id),
    CONSTRAINT fk_invoices_term FOREIGN KEY (term_id) REFERENCES terms(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE invoice_items (
    id          CHAR(25) NOT NULL PRIMARY KEY,
    invoice_id  CHAR(25) NOT NULL,
    description VARCHAR(255) NOT NULL,
    amount      DECIMAL(12,2) NOT NULL,
    CONSTRAINT fk_invoice_items_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE payments (
    id                    CHAR(25) NOT NULL PRIMARY KEY,
    school_id             CHAR(25) NOT NULL,
    student_id            CHAR(25) NOT NULL,
    invoice_id            CHAR(25) NOT NULL,
    amount                DECIMAL(12,2) NOT NULL,
    payment_method        ENUM('CASH','BANK','MOBILE_MONEY','CARD') NOT NULL,
    transaction_reference VARCHAR(120) NULL,
    paid_by               CHAR(25) NOT NULL,
    payment_date          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_payments_school_id (school_id),
    CONSTRAINT fk_payments_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
    CONSTRAINT fk_payments_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    CONSTRAINT fk_payments_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id),
    CONSTRAINT fk_payments_recorder FOREIGN KEY (paid_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE receipts (
    id             CHAR(25) NOT NULL PRIMARY KEY,
    payment_id     CHAR(25) NOT NULL UNIQUE,
    receipt_number VARCHAR(50) NOT NULL UNIQUE,
    issued_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_receipts_payment FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE expense_categories (
    id        CHAR(25) NOT NULL PRIMARY KEY,
    school_id CHAR(25) NOT NULL,
    name      VARCHAR(120) NOT NULL,
    UNIQUE KEY uq_expense_categories_school_name (school_id, name),
    CONSTRAINT fk_expense_categories_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE expenses (
    id             CHAR(25) NOT NULL PRIMARY KEY,
    school_id      CHAR(25) NOT NULL,
    category_id    CHAR(25) NOT NULL,
    description    VARCHAR(255) NOT NULL,
    amount         DECIMAL(12,2) NOT NULL,
    payment_method ENUM('CASH','BANK','MOBILE_MONEY','CARD') NOT NULL,
    expense_date   DATE NOT NULL,
    recorded_by    CHAR(25) NOT NULL,
    receipt        VARCHAR(255) NULL,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_expenses_school_id (school_id),
    CONSTRAINT fk_expenses_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
    CONSTRAINT fk_expenses_category FOREIGN KEY (category_id) REFERENCES expense_categories(id),
    CONSTRAINT fk_expenses_recorder FOREIGN KEY (recorded_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------
-- EVENTS, ANNOUNCEMENTS, MESSAGING
-- ---------------------------------------------------------

CREATE TABLE events (
    id          CHAR(25) NOT NULL PRIMARY KEY,
    school_id   CHAR(25) NOT NULL,
    title       VARCHAR(255) NOT NULL,
    description TEXT NULL,
    date        DATETIME NOT NULL,
    location    VARCHAR(255) NULL,
    event_type  ENUM('ACADEMIC','EXAM','HOLIDAY','MEETING','SPORTS','OTHER') NOT NULL DEFAULT 'OTHER',
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_events_school_date (school_id, date),
    CONSTRAINT fk_events_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE announcements (
    id           CHAR(25) NOT NULL PRIMARY KEY,
    school_id    CHAR(25) NOT NULL,
    title        VARCHAR(255) NOT NULL,
    content      TEXT NOT NULL,
    image        VARCHAR(255) NULL,
    target_role  ENUM('SUPER_ADMIN','SCHOOL_ADMIN','HEADMASTER','ACCOUNTANT','TEACHER','STUDENT','PARENT') NULL,
    target_class VARCHAR(120) NULL,
    publish_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expiry_date  DATETIME NULL,
    status       VARCHAR(20) NOT NULL DEFAULT 'PUBLISHED',
    INDEX idx_announcements_school_id (school_id),
    CONSTRAINT fk_announcements_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE messages (
    id           CHAR(25) NOT NULL PRIMARY KEY,
    school_id    CHAR(25) NOT NULL,
    sender_id    CHAR(25) NOT NULL,
    recipient_id CHAR(25) NOT NULL,
    subject      VARCHAR(255) NULL,
    body         TEXT NOT NULL,
    is_read      TINYINT(1) NOT NULL DEFAULT 0,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_messages_school_recipient (school_id, recipient_id),
    CONSTRAINT fk_messages_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
    CONSTRAINT fk_messages_sender FOREIGN KEY (sender_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE notifications (
    id         CHAR(25) NOT NULL PRIMARY KEY,
    school_id  CHAR(25) NOT NULL,
    user_id    CHAR(25) NOT NULL,
    type       ENUM('INFO','SUCCESS','WARNING','ERROR','PAYMENT','RESULT','ATTENDANCE','MESSAGE','ANNOUNCEMENT') NOT NULL,
    title      VARCHAR(255) NOT NULL,
    body       TEXT NOT NULL,
    is_read    TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_notifications_school_user (school_id, user_id),
    CONSTRAINT fk_notifications_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
    CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------
-- TIMETABLE
-- ---------------------------------------------------------

CREATE TABLE timetables (
    id         CHAR(25) NOT NULL PRIMARY KEY,
    school_id  CHAR(25) NOT NULL,
    day        VARCHAR(20) NOT NULL,
    start_time VARCHAR(10) NOT NULL,
    end_time   VARCHAR(10) NOT NULL,
    class_id   CHAR(25) NOT NULL,
    subject_id CHAR(25) NOT NULL,
    teacher_id CHAR(25) NOT NULL,
    room       VARCHAR(50) NULL,
    INDEX idx_timetables_school_id (school_id),
    CONSTRAINT fk_timetables_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
    CONSTRAINT fk_timetables_class FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    CONSTRAINT fk_timetables_subject FOREIGN KEY (subject_id) REFERENCES subjects(id),
    CONSTRAINT fk_timetables_teacher FOREIGN KEY (teacher_id) REFERENCES teachers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------
-- AUDIT LOG
-- ---------------------------------------------------------

CREATE TABLE audit_logs (
    id          CHAR(25) NOT NULL PRIMARY KEY,
    school_id   CHAR(25) NULL,
    user_id     CHAR(25) NULL,
    action      VARCHAR(100) NOT NULL,
    resource    VARCHAR(100) NOT NULL,
    resource_id CHAR(25) NULL,
    ip_address  VARCHAR(64) NULL,
    metadata    JSON NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_audit_logs_school_id (school_id),
    CONSTRAINT fk_audit_logs_school FOREIGN KEY (school_id) REFERENCES schools(id),
    CONSTRAINT fk_audit_logs_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;
