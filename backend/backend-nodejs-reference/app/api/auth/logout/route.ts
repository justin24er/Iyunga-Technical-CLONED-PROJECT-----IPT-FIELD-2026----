import { destroySession, getSession } from "@/lib/auth";
import { ok } from "@/lib/response";
import { logAudit } from "@/lib/logger";

export async function POST() {
  const session = await getSession();
  await destroySession();

  if (session) {
    await logAudit({
      schoolId: session.schoolId,
      userId: session.userId,
      action: "LOGOUT",
      resource: "user",
      resourceId: session.userId,
    });
  }

  return ok({ message: "Signed out" });
}
