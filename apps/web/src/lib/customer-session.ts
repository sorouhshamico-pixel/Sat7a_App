import "server-only";
import { cookies } from "next/headers";
import { CUSTOMER_SESSION_COOKIE } from "./customer-session-constants";

// Customer auth is single-step — phone + OTP verify returns a fully
// privileged, 30-day Sanctum token directly (see
// App\Domain\Authentication\Actions\VerifyOtpAction), unlike the admin
// side's two-step password+MFA flow. So unlike src/lib/session.ts, there's
// no short-lived intermediate token to keep out of a cookie — the OTP
// verify route handler writes this cookie the moment it succeeds.
const CUSTOMER_SESSION_TTL_SECONDS = 60 * 60 * 24 * 30;

export async function getCustomerSessionToken(): Promise<string | null> {
  const store = await cookies();

  return store.get(CUSTOMER_SESSION_COOKIE)?.value ?? null;
}

export async function setCustomerSessionToken(token: string): Promise<void> {
  const store = await cookies();

  store.set(CUSTOMER_SESSION_COOKIE, token, {
    httpOnly: true,
    secure: process.env.NODE_ENV === "production",
    sameSite: "lax",
    path: "/",
    maxAge: CUSTOMER_SESSION_TTL_SECONDS,
  });
}

export async function clearCustomerSessionToken(): Promise<void> {
  const store = await cookies();

  store.delete(CUSTOMER_SESSION_COOKIE);
}

export interface CustomerSessionUser {
  id: string;
  name: string | null;
  phone: string | null;
}

const CUSTOMER_USER_COOKIE = "customer_user";

export async function setCustomerSessionUser(user: CustomerSessionUser): Promise<void> {
  const store = await cookies();

  store.set(CUSTOMER_USER_COOKIE, JSON.stringify(user), {
    httpOnly: false,
    secure: process.env.NODE_ENV === "production",
    sameSite: "lax",
    path: "/",
    maxAge: CUSTOMER_SESSION_TTL_SECONDS,
  });
}

export async function getCustomerSessionUser(): Promise<CustomerSessionUser | null> {
  const store = await cookies();
  const raw = store.get(CUSTOMER_USER_COOKIE)?.value;

  if (!raw) return null;

  try {
    return JSON.parse(raw) as CustomerSessionUser;
  } catch {
    return null;
  }
}

export async function clearCustomerSessionUser(): Promise<void> {
  const store = await cookies();

  store.delete(CUSTOMER_USER_COOKIE);
}

export { CUSTOMER_SESSION_COOKIE };
