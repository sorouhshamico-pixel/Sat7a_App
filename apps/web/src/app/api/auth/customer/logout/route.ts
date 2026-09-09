import { NextResponse } from "next/server";
import { callBackend } from "@/lib/api/backend";
import {
  clearCustomerSessionToken,
  clearCustomerSessionUser,
  getCustomerSessionToken,
} from "@/lib/customer-session";

export async function POST() {
  const token = await getCustomerSessionToken();

  if (token) {
    // Best-effort remote revocation — a fully unreachable backend (not
    // just an already-invalid token, which comes back as an ordinary
    // non-throwing 401 here) must never block clearing the local cookie.
    // A caller relying on this to actually end a stuck session (see
    // src/components/query-provider.tsx's redirect-on-expiry) would
    // otherwise stay trapped exactly where it started.
    await callBackend("auth/logout", { method: "POST", token }).catch(() => null);
  }

  await clearCustomerSessionToken();
  await clearCustomerSessionUser();

  return NextResponse.json({ data: { message: "Logged out." }, meta: {}, errors: null });
}
