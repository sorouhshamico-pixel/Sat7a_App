import { NextRequest, NextResponse } from "next/server";
import { callBackend } from "@/lib/api/backend";

interface SetupResult {
  secret: string;
  otpauth_url: string;
}

// `token` is the short-lived mfa-setup-scoped token from the login step,
// held only in the login page's own component state (see
// src/app/api/auth/login/route.ts) — never a cookie.
export async function POST(request: NextRequest) {
  const { token } = (await request.json()) as { token: string };

  const { status, envelope } = await callBackend<SetupResult>("auth/admin/mfa/setup", {
    method: "POST",
    token,
  });

  return NextResponse.json(envelope, { status });
}
