import type { SessionPayload } from "@/lib/auth";
import { can, PERMISSIONS } from "@/lib/permissions";
import { forbidden } from "@/lib/response";

/**
 * Use after requireAuth():
 *
 *   const roleCheck = requirePermission(session, "students:write");
 *   if (roleCheck) return roleCheck; // it's an error Response
 */
export function requirePermission(
  session: SessionPayload,
  permission: keyof typeof PERMISSIONS
): Response | null {
  if (!can(session.role, permission)) {
    return forbidden(`Role ${session.role} cannot perform "${permission}"`);
  }
  return null;
}
