import { NextResponse } from "next/server";
import { callBackend } from "@/lib/api/backend";
import {
  clearProviderSessionToken,
  clearProviderSessionUser,
  getProviderSessionToken,
} from "@/lib/provider-session";

export async function POST() {
  const token = await getProviderSessionToken();

  if (token) {
    await callBackend("auth/logout", { method: "POST", token });
  }

  await clearProviderSessionToken();
  await clearProviderSessionUser();

  return NextResponse.json({ data: { message: "Logged out." }, meta: {}, errors: null });
}
