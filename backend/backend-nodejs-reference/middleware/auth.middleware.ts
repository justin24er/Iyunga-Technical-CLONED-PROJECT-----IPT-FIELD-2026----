import { getSession, type SessionPayload } from "@/lib/auth";
import { unauthorized } from "@/lib/response";

/**
 * Use at the top of every protected route handler:
 *
 *   const guard = await requireAuth();
 *   if (guard.error) return guard.error;
 *   const session = guard.session;
 */
export async function requireAuth(): Promise<
  { session: SessionPayload; error: null } | { session: null; error: Response }
> {
  const session = await getSession();
  if (!session) {
    return { session: null, error: unauthorized("You must be signed in") };
  }
  return { session, error: null };
}
