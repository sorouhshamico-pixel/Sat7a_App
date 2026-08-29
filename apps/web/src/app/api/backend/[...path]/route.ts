import { NextRequest, NextResponse } from "next/server";
import { callBackend } from "@/lib/api/backend";
import { getSessionToken } from "@/lib/session";
import { getCustomerSessionToken } from "@/lib/customer-session";
import { getProviderSessionToken } from "@/lib/provider-session";

// Generic proxy for every `/api/v1/...` call any authenticated screen
// needs to make — GET/POST/PATCH/PUT/DELETE. Adding a new screen never
// needs a new route handler here; it just fetches `/api/backend/whatever`
// and this forwards it with the right session cookie's token attached
// server-side (or no token at all for the public prefixes below). The
// login/otp/mfa/logout routes are separate because they each have their
// own cookie-writing rules that happen *before* a session exists.
//
// Four tiers share this one file (see docs/PROVIDER_WEB_APP.md
// §Four-tier backend proxy): `admin/...` uses the admin cookie,
// `providers/...`/`drivers/...` use the provider-staff cookie,
// `customers/...` uses the customer cookie, and a small SHARED_PREFIXES
// list (reached by more than one account type) tries whichever of the
// three session cookies is actually present. PUBLIC_PREFIXES never
// require any of them — a guest builds a quote before authenticating (see
// docs/PRODUCT_REQUIREMENTS.md §Core customer journey).
const PUBLIC_PREFIXES = ["maps/", "pricing/quote", "cities", "health"];
const SHARED_PREFIXES = ["documents/", "notifications/"];

function isPublicPath(path: string): boolean {
  return PUBLIC_PREFIXES.some((prefix) => path === prefix || path.startsWith(prefix));
}

function isSharedPath(path: string): boolean {
  return SHARED_PREFIXES.some((prefix) => path.startsWith(prefix));
}

async function resolveToken(pathSegments: string[], path: string): Promise<string | null> {
  const top = pathSegments[0];

  if (top === "admin") return getSessionToken();
  if (top === "providers" || top === "drivers") return getProviderSessionToken();
  if (top === "customers") return getCustomerSessionToken();

  if (isSharedPath(path)) {
    return (
      (await getCustomerSessionToken()) ??
      (await getProviderSessionToken()) ??
      (await getSessionToken())
    );
  }

  return getCustomerSessionToken();
}

async function proxy(request: NextRequest, pathSegments: string[]) {
  const path = pathSegments.join("/");

  let token: string | null = null;

  if (!isPublicPath(path)) {
    token = await resolveToken(pathSegments, path);

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

  let body: unknown;

  if (request.method !== "GET" && request.method !== "DELETE") {
    const contentType = request.headers.get("content-type") ?? "";

    body = contentType.includes("multipart/form-data")
      ? await request.formData()
      : await request.json().catch(() => undefined);
  }

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

export async function PATCH(
  request: NextRequest,
  context: { params: Promise<{ path: string[] }> },
) {
  return proxy(request, (await context.params).path);
}

export async function PUT(request: NextRequest, context: { params: Promise<{ path: string[] }> }) {
  return proxy(request, (await context.params).path);
}

export async function DELETE(
  request: NextRequest,
  context: { params: Promise<{ path: string[] }> },
) {
  return proxy(request, (await context.params).path);
}
