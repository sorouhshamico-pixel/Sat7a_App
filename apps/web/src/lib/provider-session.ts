import "server-only";
import { cookies } from "next/headers";
import { PROVIDER_SESSION_COOKIE } from "./provider-session-constants";

// Provider-side staff (owner, fleet manager, driver) all authenticate via
// the same single-step phone+OTP flow as customers — a fully-privileged
// 30-day Sanctum token comes back directly from OTP verify (see
// App\Domain\Authentication\Actions\VerifyOtpAction). Unlike customers,
// though, an unrecognized phone is a hard failure here, never a silent
// signup — provider-staff accounts are always provisioned in advance by
// their provider owner (Phase 3/4 fleet/driver management) or an admin,
// never self-registered through this login form (see docs/PROVIDER_WEB_APP.md).
const PROVIDER_SESSION_TTL_SECONDS = 60 * 60 * 24 * 30;

export async function getProviderSessionToken(): Promise<string | null> {
  const store = await cookies();

  return store.get(PROVIDER_SESSION_COOKIE)?.value ?? null;
}

export async function setProviderSessionToken(token: string): Promise<void> {
  const store = await cookies();

  store.set(PROVIDER_SESSION_COOKIE, token, {
    httpOnly: true,
    secure: process.env.NODE_ENV === "production",
    sameSite: "lax",
    path: "/",
    maxAge: PROVIDER_SESSION_TTL_SECONDS,
  });
}

export async function clearProviderSessionToken(): Promise<void> {
  const store = await cookies();

  store.delete(PROVIDER_SESSION_COOKIE);
}

export interface ProviderSessionUser {
  id: string;
  name: string | null;
  phone: string | null;
}

const PROVIDER_USER_COOKIE = "provider_user";

export async function setProviderSessionUser(user: ProviderSessionUser): Promise<void> {
  const store = await cookies();

  store.set(PROVIDER_USER_COOKIE, JSON.stringify(user), {
    httpOnly: false,
    secure: process.env.NODE_ENV === "production",
    sameSite: "lax",
    path: "/",
    maxAge: PROVIDER_SESSION_TTL_SECONDS,
  });
}

export async function getProviderSessionUser(): Promise<ProviderSessionUser | null> {
  const store = await cookies();
  const raw = store.get(PROVIDER_USER_COOKIE)?.value;

  if (!raw) return null;

  try {
    return JSON.parse(raw) as ProviderSessionUser;
  } catch {
    return null;
  }
}

export async function clearProviderSessionUser(): Promise<void> {
  const store = await cookies();

  store.delete(PROVIDER_USER_COOKIE);
}

export { PROVIDER_SESSION_COOKIE };
