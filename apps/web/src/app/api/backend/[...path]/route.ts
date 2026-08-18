import { NextRequest, NextResponse } from "next/server";
import { callBackend } from "@/lib/api/backend";
import { getSessionToken } from "@/lib/session";

// Generic authenticated proxy for every `/api/v1/...` call a signed-in
// admin page needs to make — GET for reads, POST for actions. Adding a new
// admin screen never needs a new route handler here; it just fetches
// `/api/backend/admin/whatever` and this forwards it with the session
// cookie's token attached server-side (see docs/OPERATIONS_COMMAND_CENTER.md
// §Authentication). The login/mfa/logout routes are separate because they
// each have their own cookie-handling rules.
async function proxy(request: NextRequest, path: string[]) {
  const token = await getSessionToken();

  if (!token) {
    return NextResponse.json(
      {
        data: null,
        meta: {},
        errors: [{ code: "UNAUTHENTICATED", message: "Authentication required." }],
      },
      { status: 401 },
    );
  }

  const body =
    request.method === "GET" || request.method === "DELETE"
      ? undefined
      : await request.json().catch(() => undefined);

  const { status, envelope } = await callBackend(path.join("/"), {
    method: request.method as "GET" | "POST" | "PATCH" | "PUT" | "DELETE",
    token,
    body,
    searchParams: request.nextUrl.searchParams,
  });

  return NextResponse.json(envelope, { status });
}

export async function GET(request: NextRequest, context: { params: Promise<{ path: string[] }> }) {
  return proxy(request, (await context.params).path);
}

export async function POST(request: NextRequest, context: { params: Promise<{ path: string[] }> }) {
  return proxy(request, (await context.params).path);
}
