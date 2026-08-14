import { prisma } from "./prisma";

interface AuditParams {
  schoolId: string | null;
  userId: string | null;
  action: string; // e.g. "CREATE_STUDENT", "DELETE_PAYMENT"
  resource: string; // e.g. "student", "payment"
  resourceId?: string;
  ipAddress?: string | null;
  metadata?: Record<string, unknown>;
}

export async function logAudit(params: AuditParams) {
  try {
    await prisma.auditLog.create({
      data: {
        schoolId: params.schoolId,
        userId: params.userId,
        action: params.action,
        resource: params.resource,
        resourceId: params.resourceId,
        ipAddress: params.ipAddress ?? undefined,
        metadata: params.metadata ?? undefined,
      },
    });
  } catch (err) {
    // Audit logging must never break the primary request
    console.error("Failed to write audit log", err);
  }
}
