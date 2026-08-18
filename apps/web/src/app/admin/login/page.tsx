"use client";

import { FormEvent, useState } from "react";
import { useRouter } from "next/navigation";
import QRCode from "qrcode";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Card, CardTitle } from "@/components/ui/card";
import { Alert } from "@/components/ui/alert";
import { Spinner } from "@/components/ui/spinner";

type Step =
  | { kind: "credentials" }
  | { kind: "mfa_setup"; mfaToken: string; secret: string; otpauthUrl: string }
  | { kind: "mfa_challenge"; mfaToken: string }
  | { kind: "recovery_codes"; codes: string[] };

async function postJson<T>(
  path: string,
  body: unknown,
): Promise<{ ok: boolean; data: T | null; message: string | null }> {
  const response = await fetch(path, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(body),
  });
  const envelope = await response.json();

  if (!response.ok || envelope.errors) {
    return {
      ok: false,
      data: null,
      message: envelope.errors?.[0]?.message ?? "حدث خطأ غير متوقع.",
    };
  }

  return { ok: true, data: envelope.data as T, message: null };
}

export default function AdminLoginPage() {
  const router = useRouter();
  const [step, setStep] = useState<Step>({ kind: "credentials" });
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [qrDataUrl, setQrDataUrl] = useState<string | null>(null);

  async function handleCredentials(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setLoading(true);
    setError(null);

    const form = new FormData(event.currentTarget);
    const result = await postJson<{ stage: string; token: string }>("/api/auth/login", {
      email: form.get("email"),
      password: form.get("password"),
    });

    setLoading(false);

    if (!result.ok || !result.data) {
      setError(result.message);
      return;
    }

    if (result.data.stage === "mfa_setup_required") {
      const setup = await postJson<{ secret: string; otpauth_url: string }>("/api/auth/mfa/setup", {
        token: result.data.token,
      });

      if (!setup.ok || !setup.data) {
        setError(setup.message);
        return;
      }

      const dataUrl = await QRCode.toDataURL(setup.data.otpauth_url);
      setQrDataUrl(dataUrl);
      setStep({
        kind: "mfa_setup",
        mfaToken: result.data.token,
        secret: setup.data.secret,
        otpauthUrl: setup.data.otpauth_url,
      });
    } else {
      setStep({ kind: "mfa_challenge", mfaToken: result.data.token });
    }
  }

  async function handleMfaSetupConfirm(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (step.kind !== "mfa_setup") return;
    setLoading(true);
    setError(null);

    const form = new FormData(event.currentTarget);
    const result = await postJson<{ recovery_codes: string[] }>("/api/auth/mfa/confirm", {
      token: step.mfaToken,
      code: form.get("code"),
    });

    setLoading(false);

    if (!result.ok || !result.data) {
      setError(result.message);
      return;
    }

    setStep({ kind: "recovery_codes", codes: result.data.recovery_codes });
  }

  async function handleMfaChallenge(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (step.kind !== "mfa_challenge") return;
    setLoading(true);
    setError(null);

    const form = new FormData(event.currentTarget);
    const result = await postJson("/api/auth/mfa/challenge", {
      token: step.mfaToken,
      code: form.get("code"),
    });

    setLoading(false);

    if (!result.ok) {
      setError(result.message);
      return;
    }

    router.push("/admin");
    router.refresh();
  }

  return (
    <div className="flex min-h-screen items-center justify-center bg-gray-50 p-4">
      <Card className="w-full max-w-sm">
        <CardTitle>تسجيل دخول الإدارة</CardTitle>

        {error && (
          <div className="mb-3">
            <Alert>{error}</Alert>
          </div>
        )}

        {step.kind === "credentials" && (
          <form onSubmit={handleCredentials} className="flex flex-col gap-3">
            <Input
              name="email"
              type="email"
              placeholder="البريد الإلكتروني"
              required
              autoComplete="username"
            />
            <Input
              name="password"
              type="password"
              placeholder="كلمة المرور"
              required
              autoComplete="current-password"
            />
            <Button type="submit" disabled={loading}>
              {loading ? <Spinner /> : "دخول"}
            </Button>
          </form>
        )}

        {step.kind === "mfa_setup" && (
          <form onSubmit={handleMfaSetupConfirm} className="flex flex-col gap-3">
            <p className="text-sm text-gray-600">
              امسح رمز QR باستخدام تطبيق المصادقة (مثل Google Authenticator)، أو أدخل هذا الرمز
              يدوياً:
            </p>
            {qrDataUrl && (
              // eslint-disable-next-line @next/next/no-img-element
              <img
                src={qrDataUrl}
                alt="QR code لإعداد المصادقة الثنائية"
                className="mx-auto h-40 w-40"
              />
            )}
            <code className="rounded bg-gray-100 p-2 text-center text-xs" dir="ltr">
              {step.secret}
            </code>
            <Input
              name="code"
              placeholder="رمز التحقق المكوّن من 6 أرقام"
              required
              maxLength={20}
              autoComplete="one-time-code"
            />
            <Button type="submit" disabled={loading}>
              {loading ? <Spinner /> : "تأكيد"}
            </Button>
          </form>
        )}

        {step.kind === "mfa_challenge" && (
          <form onSubmit={handleMfaChallenge} className="flex flex-col gap-3">
            <p className="text-sm text-gray-600">أدخل رمز التحقق من تطبيق المصادقة.</p>
            <Input
              name="code"
              placeholder="رمز التحقق"
              required
              maxLength={20}
              autoComplete="one-time-code"
              autoFocus
            />
            <Button type="submit" disabled={loading}>
              {loading ? <Spinner /> : "تحقق"}
            </Button>
          </form>
        )}

        {step.kind === "recovery_codes" && (
          <div className="flex flex-col gap-3">
            <Alert tone="info">
              احفظ رموز الاسترداد التالية في مكان آمن — لن تظهر مرة أخرى. استخدمها إذا فقدت الوصول
              إلى تطبيق المصادقة.
            </Alert>
            <ul
              className="grid grid-cols-2 gap-2 rounded bg-gray-100 p-3 font-mono text-sm"
              dir="ltr"
            >
              {step.codes.map((code) => (
                <li key={code}>{code}</li>
              ))}
            </ul>
            <Button
              onClick={() => {
                router.push("/admin");
                router.refresh();
              }}
            >
              المتابعة إلى لوحة التحكم
            </Button>
          </div>
        )}
      </Card>
    </div>
  );
}
