import { NextResponse } from "next/server";
import { callBackend } from "@/lib/api/backend";
import { clearSessionToken, clearSessionUser, getSessionToken } from "@/lib/session";

export async function POST() {
  const token = await getSessionToken();

  if (token) {
    await callBackend("auth/logout", { method: "POST", token });
  }

  await clearSessionToken();
  await clearSessionUser();

  return NextResponse.json({ data: { message: "Logged out." }, meta: {}, errors: null });
}
