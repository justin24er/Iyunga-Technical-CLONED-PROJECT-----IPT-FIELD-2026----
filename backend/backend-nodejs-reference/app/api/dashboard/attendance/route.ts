import { NextRequest } from "next/server";
import { requireAuth } from "@/middleware/auth.middleware";
import { requireSchoolScope } from "@/middleware/tenant.middleware";
import { prisma } from "@/lib/prisma";
import { ok } from "@/lib/response";

// GET /api/dashboard/attendance?range=10  (days)
export async function GET(req: NextRequest) {
  const guard = await requireAuth();
  if (guard.error) return guard.error;

  const scope = requireSchoolScope(guard.session);
  if (scope.error) return scope.error;

  const days = Number(req.nextUrl.searchParams.get("range") ?? 10);
  const since = new Date();
  since.setDate(since.getDate() - days);

  const rows = await prisma.attendance.groupBy({
    by: ["date", "status"],
    where: { schoolId: scope.schoolId, date: { gte: since } },
    _count: { _all: true },
    orderBy: { date: "asc" },
  });

  const byDate = new Map<string, { date: string; present: number; absent: number }>();
  for (const row of rows) {
    const key = row.date.toISOString().slice(0, 10);
    if (!byDate.has(key)) byDate.set(key, { date: key, present: 0, absent: 0 });
    const entry = byDate.get(key)!;
    if (row.status === "PRESENT") entry.present += row._count._all;
    if (row.status === "ABSENT") entry.absent += row._count._all;
  }

  const series = Array.from(byDate.values()).map((d) => ({
    ...d,
    percentage:
      d.present + d.absent > 0
        ? Math.round((d.present / (d.present + d.absent)) * 1000) / 10
        : 0,
  }));

  return ok(series);
}
