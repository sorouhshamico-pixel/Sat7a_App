import { NextRequest, NextResponse } from "next/server";
import { backendUrl } from "@/lib/api/backend";
import { getSessionToken } from "@/lib/session";
import { getCustomerSessionToken } from "@/lib/customer-session";
import { getProviderSessionToken } from "@/lib/provider-session";

// Documents are reachable by more than one account type — a provider
// viewing their own upload, an admin/staff member verifying it — access is
// checked fresh on every request inside
// App\Http\Controllers\Api\V1\DocumentController::canAccess, matching the
// `documents/` entry in SHARED_PREFIXES over in
// api/backend/[...path]/route.ts. This route is separate from that generic
// proxy because the backend endpoint returns raw file bytes, not a JSON
// envelope — the generic proxy's callBackend() always calls
// `response.json()`, which throws on a PDF/image body.
export async function GET(
  request: NextRequest,
  context: { params: Promise<{ id: string }> },
) {
  const { id } = await context.params;

  const token =
    (await getCustomerSessionToken()) ??
    (await getProviderSessionToken()) ??
    (await getSessionToken());

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

  const response = await fetch(backendUrl(`documents/${id}/download`), {
    headers: {
      Accept: "application/json",
      Authorization: `Bearer ${token}`,
    },
    cache: "no-store",
  });

  const headers = new Headers({ "Cache-Control": "no-store" });
  const contentType = response.headers.get("content-type");
  const contentDisposition = response.headers.get("content-disposition");
  if (contentType) headers.set("Content-Type", contentType);
  if (contentDisposition) headers.set("Content-Disposition", contentDisposition);

  return new NextResponse(response.body, { status: response.status, headers });
}
