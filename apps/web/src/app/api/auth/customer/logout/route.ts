import { NextResponse } from "next/server";
import { callBackend } from "@/lib/api/backend";
import {
  clearCustomerSessionToken,
  clearCustomerSessionUser,
  getCustomerSessionToken,
} from "@/lib/customer-session";

export async function POST() {
  const token = await getCustomerSessionToken();

  if (token) {
    await callBackend("auth/logout", { method: "POST", token });
  }

  await clearCustomerSessionToken();
  await clearCustomerSessionUser();

  return NextResponse.json({ data: { message: "Logged out." }, meta: {}, errors: null });
}
