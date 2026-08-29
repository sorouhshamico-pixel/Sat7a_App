import { NextRequest, NextResponse } from "next/server";
import { callBackend } from "@/lib/api/backend";
import { setCustomerSessionToken, setCustomerSessionUser } from "@/lib/customer-session";

interface VerifyResult {
  user: { id: string; name: string | null; phone: string | null };
  token: string;
}

// Single-step — OTP verify returns a fully-privileged 30-day token
// directly (see App\Domain\Authentication\Actions\VerifyOtpAction), unlike
// the admin side's mandatory MFA second step. This is the one place the
// customer session cookie is ever written.
export async function POST(request: NextRequest) {
  const body = await request.json();

  const { status, envelope } = await callBackend<VerifyResult>("auth/otp/verify", {
    method: "POST",
    body: { ...body, user_type: "customer" },
  });

  if (envelope.data) {
    await setCustomerSessionToken(envelope.data.token);
    await setCustomerSessionUser(envelope.data.user);
  }

  return NextResponse.json(
    {
      data: envelope.data ? { user: envelope.data.user } : null,
      meta: envelope.meta,
      errors: envelope.errors,
    },
    { status },
  );
}
