import type { SessionPayload } from "@/lib/auth";
import { forbidden } from "@/lib/response";

/**
 * CRITICAL MULTI-TENANCY RULE:
 * schoolId used in every query MUST come from the authenticated session,
 * never from the request body, query string, or URL params.
 *
 * This helper returns the trusted schoolId for the current request, or an
 * error Response if the user has no school context (e.g. malformed session)
 * or is a SUPER_ADMIN acting outside a tenant scope where one is required.
 */
export function requireSchoolScope(
  session: SessionPayload
): { schoolId: string; error: null } | { schoolId: null; error: Response } {
  if (session.role === "SUPER_ADMIN") {
    // SUPER_ADMIN routes should explicitly pass schoolId as a route param
    // (e.g. GET /api/schools/:id/students) which is then validated against
    // the schools table — never trusted blindly either.
    return {
      schoolId: null,
      error: forbidden("SUPER_ADMIN must access tenant data via /api/schools/:id/* routes"),
    };
  }

  if (!session.schoolId) {
    return { schoolId: null, error: forbidden("No school context on this account") };
  }

  return { schoolId: session.schoolId, error: null };
}
