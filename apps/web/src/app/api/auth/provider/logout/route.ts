import { NextResponse } from "next/server";
import { callBackend } from "@/lib/api/backend";
import {
  clearProviderSessionToken,
  clearProviderSessionUser,
  getProviderSessionToken,
} from "@/lib/provider-session";

export async function POST() {
  const token = await getProviderSessionToken();

  if (token) {
    // Best-effort remote revocation — a fully unreachable backend (not
    // just an already-invalid token, which comes back as an ordinary
    // non-throwing 401 here) must never block clearing the local cookie.
    // A caller relying on this to actually end a stuck session (see
    // src/components/query-provider.tsx's redirect-on-expiry) would
    // otherwise stay trapped exactly where it started.
    await callBackend("auth/logout", { method: "POST", token }).catch(() => null);
  }

  await clearProviderSessionToken();
  await clearProviderSessionUser();

  return NextResponse.json({ data: { message: "Logged out." }, meta: {}, errors: null });
}
