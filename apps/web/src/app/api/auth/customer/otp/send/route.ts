import { NextRequest, NextResponse } from "next/server";
import { callBackend } from "@/lib/api/backend";

// Public — no cookie involved yet. Forwards straight to Laravel, which
// itself returns an identical response whether or not the phone is
// already registered (see App\Http\Controllers\Api\V1\Auth\OtpController).
export async function POST(request: NextRequest) {
  const body = await request.json();

  const { status, envelope } = await callBackend("auth/otp/send", {
    method: "POST",
    body: { ...body, user_type: "customer" },
  });

  return NextResponse.json(envelope, { status });
}
