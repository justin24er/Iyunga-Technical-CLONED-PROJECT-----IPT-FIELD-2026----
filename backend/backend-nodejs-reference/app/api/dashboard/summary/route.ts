import { requireAuth } from "@/middleware/auth.middleware";
import { requireSchoolScope } from "@/middleware/tenant.middleware";
import { prisma } from "@/lib/prisma";
import { ok } from "@/lib/response";

export async function GET() {
  const guard = await requireAuth();
  if (guard.error) return guard.error;

  const scope = requireSchoolScope(guard.session);
  if (scope.error) return scope.error;

  const schoolId = scope.schoolId;

  const [totalStudents, totalTeachers, totalSubjects, revenueAgg, outstandingAgg, upcomingEvents] =
    await Promise.all([
      prisma.student.count({ where: { schoolId, status: "ACTIVE" } }),
      prisma.teacher.count({ where: { schoolId, status: "ACTIVE" } }),
      prisma.subject.count({ where: { schoolId, status: "ACTIVE" } }),
      prisma.payment.aggregate({ where: { schoolId }, _sum: { amount: true } }),
      prisma.invoice.aggregate({
        where: { schoolId, status: { in: ["UNPAID", "PARTIAL", "OVERDUE"] } },
        _sum: { totalAmount: true, paidAmount: true },
      }),
      prisma.event.findMany({
        where: { schoolId, date: { gte: new Date() } },
        orderBy: { date: "asc" },
        take: 5,
      }),
    ]);

  // Attendance percentage over the last 30 days
  const since = new Date();
  since.setDate(since.getDate() - 30);
  const [present, totalMarked] = await Promise.all([
    prisma.attendance.count({ where: { schoolId, date: { gte: since }, status: "PRESENT" } }),
    prisma.attendance.count({ where: { schoolId, date: { gte: since } } }),
  ]);

  const outstandingFees =
    Number(outstandingAgg._sum.totalAmount ?? 0) - Number(outstandingAgg._sum.paidAmount ?? 0);

  return ok({
    totalStudents,
    totalTeachers,
    totalSubjects,
    totalRevenue: Number(revenueAgg._sum.amount ?? 0),
    outstandingFees,
    attendancePercentage: totalMarked > 0 ? Math.round((present / totalMarked) * 1000) / 10 : 0,
    upcomingEvents,
  });
}
