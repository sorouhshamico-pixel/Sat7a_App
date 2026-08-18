import { NextRequest, NextResponse } from "next/server";
import { callBackend } from "@/lib/api/backend";
import { setSessionToken, setSessionUser } from "@/lib/session";

interface UserSummary {
  id: string;
  name: string;
  email: string | null;
}

interface ChallengeResult {
  user: UserSummary;
  token: string;
}

// Returning-user MFA challenge — writes the session cookie on success, the
// same as mfa/confirm (see that route and src/lib/session.ts).
export async function POST(request: NextRequest) {
  const { token, code } = (await request.json()) as {
    token: string;
    code: string;
  };

  const { status, envelope } = await callBackend<ChallengeResult>("auth/admin/mfa/challenge", {
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
      data: envelope.data ? { user: envelope.data.user } : null,
      meta: envelope.meta,
      errors: envelope.errors,
    },
    { status },
  );
}
