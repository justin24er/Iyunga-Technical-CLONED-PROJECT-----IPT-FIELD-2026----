import type { UserRole } from "@prisma/client";

/**
 * Resource-level permission matrix.
 * Add a new resource key here whenever a new module is built (Phase 10+).
 */
export const PERMISSIONS: Record<string, UserRole[]> = {
  "schools:manage": ["SUPER_ADMIN"],
  "school:update": ["SUPER_ADMIN", "SCHOOL_ADMIN"],
  "users:manage": ["SUPER_ADMIN", "SCHOOL_ADMIN"],

  "students:read": ["SUPER_ADMIN", "SCHOOL_ADMIN", "HEADMASTER", "ACCOUNTANT", "TEACHER", "STUDENT", "PARENT"],
  "students:write": ["SCHOOL_ADMIN"],

  "teachers:read": ["SUPER_ADMIN", "SCHOOL_ADMIN", "HEADMASTER"],
  "teachers:write": ["SCHOOL_ADMIN"],

  "classes:manage": ["SCHOOL_ADMIN"],
  "subjects:manage": ["SCHOOL_ADMIN"],

  "attendance:mark": ["SCHOOL_ADMIN", "TEACHER"],
  "attendance:read": ["SUPER_ADMIN", "SCHOOL_ADMIN", "HEADMASTER", "TEACHER", "STUDENT", "PARENT"],

  "exams:manage": ["SCHOOL_ADMIN"],
  "results:enter": ["TEACHER"],
  "results:approve": ["HEADMASTER", "SCHOOL_ADMIN"],
  "results:read": ["SUPER_ADMIN", "SCHOOL_ADMIN", "HEADMASTER", "TEACHER", "STUDENT", "PARENT"],

  "fees:manage": ["SCHOOL_ADMIN", "ACCOUNTANT"],
  "payments:record": ["ACCOUNTANT", "SCHOOL_ADMIN"],
  "expenses:manage": ["ACCOUNTANT", "SCHOOL_ADMIN"],

  "reports:financial": ["SCHOOL_ADMIN", "ACCOUNTANT", "HEADMASTER"],
  "reports:academic": ["SCHOOL_ADMIN", "HEADMASTER", "TEACHER"],

  "settings:manage": ["SCHOOL_ADMIN"],
  "audit:read": ["SUPER_ADMIN", "SCHOOL_ADMIN"],
};

export function can(role: UserRole, permission: keyof typeof PERMISSIONS): boolean {
  return PERMISSIONS[permission]?.includes(role) ?? false;
}
