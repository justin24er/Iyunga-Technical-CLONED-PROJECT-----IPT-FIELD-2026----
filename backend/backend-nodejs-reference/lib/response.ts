import { NextResponse } from "next/server";

export function ok<T>(data: T, status = 200) {
  return NextResponse.json({ success: true, data }, { status });
}

export function paginated<T>(
  data: T[],
  page: number,
  limit: number,
  total: number
) {
  return NextResponse.json({
    success: true,
    data,
    page,
    limit,
    total,
    totalPages: Math.max(1, Math.ceil(total / limit)),
  });
}

export function fail(message: string, status = 400, errors: unknown[] = []) {
  return NextResponse.json({ success: false, message, errors }, { status });
}

export function unauthorized(message = "Unauthorized") {
  return fail(message, 401);
}

export function forbidden(message = "You do not have permission to perform this action") {
  return fail(message, 403);
}

export function notFound(message = "Resource not found") {
  return fail(message, 404);
}

export function serverError(message = "Something went wrong") {
  return fail(message, 500);
}
