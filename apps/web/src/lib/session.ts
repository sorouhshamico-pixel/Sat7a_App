import "server-only";
import { cookies } from "next/headers";
import { SESSION_COOKIE } from "./session-constants";

// Holds the caller's current Sanctum token — server-side only, never read
// by client JS. During the admin login flow this cookie is set exactly
// once, after MFA setup/challenge succeeds, to the fully-privileged
// 8-hour session token (matching the backend's own token TTL — see
// App\Http\Controllers\Api\V1\Auth\TwoFactorController::issueFullAccessToken).
// The short-lived mfa-setup/mfa-challenge tokens used *during* login never
// touch this cookie — they stay in the login page's own component state
// (see docs/OPERATIONS_COMMAND_CENTER.md §Authentication).
const SESSION_TTL_SECONDS = 60 * 60 * 8;

export async function getSessionToken(): Promise<string | null> {
  const store = await cookies();

  return store.get(SESSION_COOKIE)?.value ?? null;
}

export async function setSessionToken(token: string): Promise<void> {
  const store = await cookies();

  store.set(SESSION_COOKIE, token, {
    httpOnly: true,
    secure: process.env.NODE_ENV === "production",
    sameSite: "lax",
    path: "/",
    maxAge: SESSION_TTL_SECONDS,
  });
}

export async function clearSessionToken(): Promise<void> {
  const store = await cookies();

  store.delete(SESSION_COOKIE);
}

export interface SessionUser {
  id: string;
  name: string;
  email: string | null;
}

// Display-only — never used for authorization, just so the admin layout
// can render "signed in as {name}" without a dedicated "current user"
// backend endpoint. Not httpOnly (client components read it too), but
// holds nothing sensitive.
const USER_COOKIE = "admin_user";

export async function setSessionUser(user: SessionUser): Promise<void> {
  const store = await cookies();

  store.set(USER_COOKIE, JSON.stringify(user), {
    httpOnly: false,
    secure: process.env.NODE_ENV === "production",
    sameSite: "lax",
    path: "/",
    maxAge: SESSION_TTL_SECONDS,
  });
}

export async function getSessionUser(): Promise<SessionUser | null> {
  const store = await cookies();
  const raw = store.get(USER_COOKIE)?.value;

  if (!raw) return null;

  try {
    return JSON.parse(raw) as SessionUser;
  } catch {
    return null;
  }
}

export async function clearSessionUser(): Promise<void> {
  const store = await cookies();

  store.delete(USER_COOKIE);
}

export { SESSION_COOKIE };
