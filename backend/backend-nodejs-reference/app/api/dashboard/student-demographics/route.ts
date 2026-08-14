import { requireAuth } from "@/middleware/auth.middleware";
import { requireSchoolScope } from "@/middleware/tenant.middleware";
import { prisma } from "@/lib/prisma";
import { ok } from "@/lib/response";

export async function GET() {
  const guard = await requireAuth();
  if (guard.error) return guard.error;

  const scope = requireSchoolScope(guard.session);
  if (scope.error) return scope.error;

  const rows = await prisma.student.groupBy({
    by: ["gender"],
    where: { schoolId: scope.schoolId, status: "ACTIVE" },
    _count: { _all: true },
  });

  const girls = rows.find((r) => r.gender === "FEMALE")?._count._all ?? 0;
  const boys = rows.find((r) => r.gender === "MALE")?._count._all ?? 0;
  const other = rows.find((r) => r.gender === "OTHER")?._count._all ?? 0;

  return ok({ total: girls + boys + other, girls, boys, other });
}
