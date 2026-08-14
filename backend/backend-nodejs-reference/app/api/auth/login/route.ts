import { NextRequest } from "next/server";
import { prisma } from "@/lib/prisma";
import { verifyPassword, createSession } from "@/lib/auth";
import { loginSchema } from "@/lib/validation";
import { ok, fail, serverError } from "@/lib/response";
import { logAudit } from "@/lib/logger";

export async function POST(req: NextRequest) {
  try {
    const body = await req.json();
    const parsed = loginSchema.safeParse(body);

    if (!parsed.success) {
      return fail("Invalid input", 422, parsed.error.issues);
    }

    const { email, password } = parsed.data;

    const user = await prisma.user.findUnique({ where: { email } });

    // Generic message so we don't leak whether the email exists
    if (!user || user.status !== "ACTIVE") {
      return fail("Invalid email or password", 401);
    }

    const validPassword = await verifyPassword(password, user.passwordHash);
    if (!validPassword) {
      return fail("Invalid email or password", 401);
    }

    await createSession({
      userId: user.id,
      schoolId: user.schoolId,
      role: user.role,
      email: user.email,
      name: user.name,
    });

    await prisma.user.update({
      where: { id: user.id },
      data: { lastLogin: new Date() },
    });

    await logAudit({
      schoolId: user.schoolId,
      userId: user.id,
      action: "LOGIN",
      resource: "user",
      resourceId: user.id,
      ipAddress: req.headers.get("x-forwarded-for"),
    });

    return ok({
      id: user.id,
      name: user.name,
      email: user.email,
      role: user.role,
      schoolId: user.schoolId,
      avatar: user.avatar,
    });
  } catch (err) {
    console.error(err);
    return serverError("Failed to sign in");
  }
}
