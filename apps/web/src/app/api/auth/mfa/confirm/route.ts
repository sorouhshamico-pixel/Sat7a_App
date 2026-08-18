import { NextRequest, NextResponse } from "next/server";
import { callBackend } from "@/lib/api/backend";
import { setSessionToken, setSessionUser } from "@/lib/session";

interface UserSummary {
  id: string;
  name: string;
  email: string | null;
}

interface ConfirmResult {
  recovery_codes: string[];
  user: UserSummary;
  token: string;
}

// First-time MFA enrollment. On success this is the moment a real,
// fully-privileged session exists — the only place besides mfa/challenge
// that ever writes the session cookie (see src/lib/session.ts).
export async function POST(request: NextRequest) {
  const { token, code } = (await request.json()) as {
    token: string;
    code: string;
  };

  const { status, envelope } = await callBackend<ConfirmResult>("auth/admin/mfa/confirm", {
    method: "POST",
    token,
    body: { code },
  });

  if (envelope.data) {
    await setSessionToken(envelope.data.token);
    await setSessionUser(envelope.data.user);
  }

  return NextResponse.json(
    {
      data: envelope.data
        ? { recovery_codes: envelope.data.recovery_codes, user: envelope.data.user }
        : null,
      meta: envelope.meta,
      errors: envelope.errors,
    },
    { status },
  );
}
