import { PrismaClient } from "@prisma/client";
import bcrypt from "bcryptjs";

const prisma = new PrismaClient();

// DEMO CREDENTIALS — for local development only. Change or remove in production.
const DEMO_PASSWORD = "Password123!";

async function main() {
  const passwordHash = await bcrypt.hash(DEMO_PASSWORD, 12);

  // ---- Super Admin (no school) ----
  await prisma.user.upsert({
    where: { email: "superadmin@example.com" },
    update: {},
    create: {
      name: "Platform Super Admin",
      email: "superadmin@example.com",
      passwordHash,
      role: "SUPER_ADMIN",
      status: "ACTIVE",
    },
  });

  // ---- School 1: Academia International School ----
  const school1 = await prisma.school.upsert({
    where: { code: "AIS" },
    update: {},
    create: {
      name: "Academia International School",
      code: "AIS",
      email: "info@academia-intl.example",
      city: "Dar es Salaam",
      region: "Dar es Salaam",
      country: "Tanzania",
      currency: "TZS",
      status: "ACTIVE",
    },
  });

  const year1 = await prisma.academicYear.create({
    data: {
      schoolId: school1.id,
      name: "2025/2026",
      startDate: new Date("2025-09-01"),
      endDate: new Date("2026-06-30"),
      isCurrent: true,
    },
  });

  const roles: { email: string; name: string; role: "SCHOOL_ADMIN" | "HEADMASTER" | "ACCOUNTANT" | "TEACHER" | "STUDENT" | "PARENT" }[] = [
    { email: "admin@example.com", name: "Grace Mwangi", role: "SCHOOL_ADMIN" },
    { email: "headmaster@example.com", name: "Daniel Kessy", role: "HEADMASTER" },
    { email: "accountant@example.com", name: "Neema Mushi", role: "ACCOUNTANT" },
    { email: "teacher@example.com", name: "Jack Snyder", role: "TEACHER" },
    { email: "student@example.com", name: "Frances Swann", role: "STUDENT" },
    { email: "parent@example.com", name: "Peter Swann", role: "PARENT" },
  ];

  for (const r of roles) {
    await prisma.user.upsert({
      where: { email: r.email },
      update: {},
      create: {
        schoolId: school1.id,
        name: r.name,
        email: r.email,
        passwordHash,
        role: r.role,
        status: "ACTIVE",
      },
    });
  }

  const classA = await prisma.class.create({
    data: {
      schoolId: school1.id,
      name: "Form 1A",
      level: "Form 1",
      academicYearId: year1.id,
      capacity: 40,
    },
  });

  await prisma.subject.createMany({
    data: [
      { schoolId: school1.id, name: "Mathematics", code: "MATH" },
      { schoolId: school1.id, name: "English", code: "ENG" },
      { schoolId: school1.id, name: "Biology", code: "BIO" },
    ],
  });

  // ---- School 2: Mbeya Modern School ----
  await prisma.school.upsert({
    where: { code: "MMS" },
    update: {},
    create: {
      name: "Mbeya Modern School",
      code: "MMS",
      email: "info@mbeyamodern.example",
      city: "Mbeya",
      region: "Mbeya",
      country: "Tanzania",
      currency: "TZS",
      status: "ACTIVE",
    },
  });

  console.log("Seed complete.");
  console.log(`Demo login password for all seeded users: ${DEMO_PASSWORD}`);
  console.log("Class created:", classA.name);
}

main()
  .catch((e) => {
    console.error(e);
    process.exit(1);
  })
  .finally(async () => {
    await prisma.$disconnect();
  });
