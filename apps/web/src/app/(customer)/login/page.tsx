"use client";

import { FormEvent, Suspense, useState } from "react";
import { useRouter, useSearchParams } from "next/navigation";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Card, CardTitle } from "@/components/ui/card";
import { Alert } from "@/components/ui/alert";
import { Spinner } from "@/components/ui/spinner";

type Step = { kind: "phone" } | { kind: "code"; phone: string };

async function postJson(path: string, body: unknown) {
  const response = await fetch(path, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(body),
  });
  const envelope = await response.json();

  if (!response.ok || envelope.errors) {
    return { ok: false as const, message: envelope.errors?.[0]?.message ?? "حدث خطأ غير متوقع." };
  }

  return { ok: true as const, message: null };
}

function LoginForm() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const [step, setStep] = useState<Step>({ kind: "phone" });
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function handleSendOtp(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setLoading(true);
    setError(null);

    const phone = new FormData(event.currentTarget).get("phone") as string;
    const result = await postJson("/api/auth/customer/otp/send", { phone });

    setLoading(false);

    if (!result.ok) {
      setError(result.message);
      return;
    }

    setStep({ kind: "code", phone });
  }

  async function handleVerify(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (step.kind !== "code") return;
    setLoading(true);
    setError(null);

    const code = new FormData(event.currentTarget).get("code") as string;
    const result = await postJson("/api/auth/customer/otp/verify", { phone: step.phone, code });

    setLoading(false);

    if (!result.ok) {
      setError(result.message);
      return;
    }

    router.push(searchParams.get("next") || "/orders");
    router.refresh();
  }

  return (
    <div className="flex flex-1 items-center justify-center">
      <Card className="w-full max-w-sm">
        <CardTitle>تسجيل الدخول</CardTitle>

        {error && (
          <div className="mb-3">
            <Alert>{error}</Alert>
          </div>
        )}

        {step.kind === "phone" && (
          <form onSubmit={handleSendOtp} className="flex flex-col gap-3">
            <label className="flex flex-col gap-1 text-sm text-gray-600">
              رقم الجوال
              <Input
                name="phone"
                type="tel"
                placeholder="+9665XXXXXXXX"
                dir="ltr"
                pattern="^\+[1-9]\d{6,14}$"
                required
                autoFocus
              />
            </label>
            <Button type="submit" disabled={loading}>
              {loading ? <Spinner /> : "إرسال رمز التحقق"}
            </Button>
          </form>
        )}

        {step.kind === "code" && (
          <form onSubmit={handleVerify} className="flex flex-col gap-3">
            <p className="text-sm text-gray-600">
              تم إرسال رمز التحقق إلى <span dir="ltr">{step.phone}</span>
            </p>
            <Input
              name="code"
              placeholder="رمز التحقق"
              maxLength={6}
              required
              autoFocus
              autoComplete="one-time-code"
            />
            <Button type="submit" disabled={loading}>
              {loading ? <Spinner /> : "تحقق"}
            </Button>
            <button
              type="button"
              className="text-sm text-gray-500 hover:text-gray-700"
              onClick={() => setStep({ kind: "phone" })}
            >
              تغيير رقم الجوال
            </button>
          </form>
        )}
      </Card>
    </div>
  );
}

export default function CustomerLoginPage() {
  return (
    <Suspense>
      <LoginForm />
    </Suspense>
  );
}
