import { NextRequest } from "next/server";
import { prisma } from "@/lib/prisma";
import { requireAuth } from "@/middleware/auth.middleware";
import { requirePermission } from "@/middleware/role.middleware";
import { requireSchoolScope } from "@/middleware/tenant.middleware";
import { createStudentSchema, paginationSchema } from "@/lib/validation";
import { paginated, ok, fail, serverError } from "@/lib/response";
import { logAudit } from "@/lib/logger";

// GET /api/students?page=1&limit=20&search=&classId=&gender=&status=
export async function GET(req: NextRequest) {
  const guard = await requireAuth();
  if (guard.error) return guard.error;

  const perm = requirePermission(guard.session, "students:read");
  if (perm) return perm;

  const scope = requireSchoolScope(guard.session);
  if (scope.error) return scope.error;

  const params = Object.fromEntries(req.nextUrl.searchParams);
  const { page, limit, search } = paginationSchema.parse(params);
  const classId = req.nextUrl.searchParams.get("classId") ?? undefined;
  const gender = req.nextUrl.searchParams.get("gender") ?? undefined;
  const status = req.nextUrl.searchParams.get("status") ?? undefined;

  const where = {
    schoolId: scope.schoolId, // ALWAYS from session, never from query params
    ...(classId && { classId }),
    ...(gender && { gender: gender as "MALE" | "FEMALE" | "OTHER" }),
    ...(status && { status: status as "ACTIVE" | "INACTIVE" | "GRADUATED" | "SUSPENDED" | "TRANSFERRED" }),
    ...(search && {
      OR: [
        { firstName: { contains: search, mode: "insensitive" as const } },
        { lastName: { contains: search, mode: "insensitive" as const } },
        { studentNumber: { contains: search, mode: "insensitive" as const } },
      ],
    }),
  };

  const [data, total] = await Promise.all([
    prisma.student.findMany({
      where,
      include: { class: { select: { id: true, name: true } } },
      orderBy: { createdAt: "desc" },
      skip: (page - 1) * limit,
      take: limit,
    }),
    prisma.student.count({ where }),
  ]);

  return paginated(data, page, limit, total);
}

// POST /api/students
export async function POST(req: NextRequest) {
  const guard = await requireAuth();
  if (guard.error) return guard.error;

  const perm = requirePermission(guard.session, "students:write");
  if (perm) return perm;

  const scope = requireSchoolScope(guard.session);
  if (scope.error) return scope.error;

  try {
    const body = await req.json();
    const parsed = createStudentSchema.safeParse(body);
    if (!parsed.success) return fail("Invalid input", 422, parsed.error.issues);

    const existing = await prisma.student.findFirst({
      where: { schoolId: scope.schoolId, studentNumber: parsed.data.studentNumber },
    });
    if (existing) return fail("A student with this student number already exists", 409);

    const student = await prisma.student.create({
      data: {
        ...parsed.data,
        schoolId: scope.schoolId, // trusted school scope, not from body
      },
    });

    await logAudit({
      schoolId: scope.schoolId,
      userId: guard.session.userId,
      action: "CREATE_STUDENT",
      resource: "student",
      resourceId: student.id,
    });

    return ok(student, 201);
  } catch (err) {
    console.error(err);
    return serverError("Failed to create student");
  }
}
