import { NextRequest } from "next/server";
import { requireAuth } from "@/middleware/auth.middleware";
import { requireSchoolScope } from "@/middleware/tenant.middleware";
import { prisma } from "@/lib/prisma";
import { paginated } from "@/lib/response";

// GET /api/dashboard/performance?page=1&limit=10&classId=&grade=
export async function GET(req: NextRequest) {
  const guard = await requireAuth();
  if (guard.error) return guard.error;

  const scope = requireSchoolScope(guard.session);
  if (scope.error) return scope.error;

  const page = Number(req.nextUrl.searchParams.get("page") ?? 1);
  const limit = Number(req.nextUrl.searchParams.get("limit") ?? 10);
  const classId = req.nextUrl.searchParams.get("classId") ?? undefined;
  const grade = req.nextUrl.searchParams.get("grade") ?? undefined;

  const where = {
    schoolId: scope.schoolId,
    status: "APPROVED" as const,
    ...(grade && { grade }),
    ...(classId && { student: { classId } }),
  };

  const [rows, total] = await Promise.all([
    prisma.result.findMany({
      where,
      include: {
        student: { select: { id: true, firstName: true, lastName: true, studentNumber: true, photo: true, class: { select: { name: true } } } },
      },
      orderBy: { marks: "desc" },
      skip: (page - 1) * limit,
      take: limit,
    }),
    prisma.result.count({ where }),
  ]);

  const data = rows.map((r) => ({
    studentId: r.student.id,
    name: `${r.student.firstName} ${r.student.lastName}`,
    studentNumber: r.student.studentNumber,
    photo: r.student.photo,
    class: r.student.class?.name ?? "—",
    grade: r.grade,
    percentage: Number(r.marks),
    status: r.status,
  }));

  return paginated(data, page, limit, total);
}
