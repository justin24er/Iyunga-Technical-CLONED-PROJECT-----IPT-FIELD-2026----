import { requireAuth } from "@/middleware/auth.middleware";
import { prisma } from "@/lib/prisma";
import { ok, notFound } from "@/lib/response";

export async function GET() {
  const guard = await requireAuth();
  if (guard.error) return guard.error;

  const user = await prisma.user.findUnique({
    where: { id: guard.session.userId },
    select: {
      id: true,
      name: true,
      email: true,
      phone: true,
      role: true,
      avatar: true,
      schoolId: true,
      school: { select: { id: true, name: true, logo: true, currency: true } },
    },
  });

  if (!user) return notFound("User not found");

  return ok(user);
}
