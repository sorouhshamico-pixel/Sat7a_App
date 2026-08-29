import { NextRequest, NextResponse } from "next/server";
import { callBackend } from "@/lib/api/backend";
import { getSessionToken } from "@/lib/session";
import { getCustomerSessionToken } from "@/lib/customer-session";

// Generic proxy for every `/api/v1/...` call an admin *or* customer page
// needs to make — GET for reads, POST for actions. Adding a new screen on
// either side never needs a new route handler here; it just fetches
// `/api/backend/whatever` and this forwards it with the right session
// cookie's token attached server-side, or no token at all for the handful
// of genuinely public endpoints (see docs/OPERATIONS_COMMAND_CENTER.md and
// docs/CUSTOMER_WEB_APP.md §Authentication). The login/otp/mfa/logout
// routes are separate because they each have their own cookie-writing
// rules that happen *before* a session exists.
//
// `admin/...` paths always use the admin cookie; everything else uses the
// customer cookie, except the public prefixes below, which never require
// either — a guest builds a quote before authenticating (see
// docs/PRODUCT_REQUIREMENTS.md §Core customer journey).
const PUBLIC_PREFIXES = ["maps/", "pricing/quote", "cities", "health"];

function isPublicPath(path: string): boolean {
  return PUBLIC_PREFIXES.some((prefix) => path === prefix || path.startsWith(prefix));
}

async function proxy(request: NextRequest, pathSegments: string[]) {
  const path = pathSegments.join("/");
  const isAdminPath = pathSegments[0] === "admin";

  let token: string | null = null;

  if (!isPublicPath(path)) {
    token = isAdminPath ? await getSessionToken() : await getCustomerSessionToken();

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
  }

  const body =
    request.method === "GET" || request.method === "DELETE"
      ? undefined
      : await request.json().catch(() => undefined);

  const { status, envelope } = await callBackend(path, {
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
