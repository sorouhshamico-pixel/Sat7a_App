import { NextRequest, NextResponse } from "next/server";
import { callBackend } from "@/lib/api/backend";
import { setProviderSessionToken, setProviderSessionUser } from "@/lib/provider-session";

interface VerifyResult {
  user: { id: string; name: string | null; phone: string | null };
  token: string;
}

// Single-step, same shape as the customer OTP-verify route handler — see
// src/app/api/auth/customer/otp/verify/route.ts. An unrecognized phone
// fails here (App\Domain\Authentication\Actions\VerifyOtpAction) rather
// than provisioning a new account, since provider-staff accounts are
// always created in advance (see docs/PROVIDER_WEB_APP.md).
export async function POST(request: NextRequest) {
  const body = await request.json();

  const { status, envelope } = await callBackend<VerifyResult>("auth/otp/verify", {
    method: "POST",
    body: { ...body, user_type: "provider_staff" },
  });

  if (envelope.data) {
    await setProviderSessionToken(envelope.data.token);
    await setProviderSessionUser(envelope.data.user);
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
