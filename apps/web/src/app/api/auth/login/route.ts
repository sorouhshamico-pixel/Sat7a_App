import { NextRequest, NextResponse } from "next/server";
import { callBackend } from "@/lib/api/backend";

interface LoginStageResult {
  stage: "mfa_setup_required" | "mfa_challenge_required";
  token: string;
}

// The first step of admin login is always password-only, and never issues
// a full session — see App\Domain\Authentication\Actions\AdminLoginAction.
// This route just forwards the credentials and returns the short-lived
// mfa-setup/mfa-challenge token straight to the client; it's never written
// to a cookie (see src/lib/session.ts).
export async function POST(request: NextRequest) {
  const body = await request.json();

  const { status, envelope } = await callBackend<LoginStageResult>("auth/admin/login", {
    method: "POST",
    body,
  });

  return NextResponse.json(envelope, { status });
}
