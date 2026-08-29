import { NextRequest, NextResponse } from "next/server";
import { callBackend } from "@/lib/api/backend";

// Public — no cookie involved yet. Forwards straight to Laravel with
// user_type: "provider_staff", which covers owner/fleet-manager/driver
// alike (see App\Domain\Users\Enums\UserType).
export async function POST(request: NextRequest) {
  const body = await request.json();

  const { status, envelope } = await callBackend("auth/otp/send", {
    method: "POST",
    body: { ...body, user_type: "provider_staff" },
  });

  return NextResponse.json(envelope, { status });
}
