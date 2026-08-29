"use client";

import { useEffect, useState } from "react";

// Chrome/Android fires this before showing its own install UI; capturing
// it lets us trigger that same native prompt from our own button instead
// of Chrome's address-bar icon. iOS Safari never fires it (no native
// install prompt exists there) — home-screen installation is manual via
// the Share sheet, so those users get text instructions instead. See
// docs/PWA.md §Install prompt and the official Next.js PWA guide this
// mirrors (next/dist/docs/01-app/02-guides/progressive-web-apps.md).
interface BeforeInstallPromptEvent extends Event {
  prompt: () => Promise<void>;
  userChoice: Promise<{ outcome: "accepted" | "dismissed" }>;
}

export function InstallPrompt({ appLabel }: { appLabel: string }) {
  const [deferredPrompt, setDeferredPrompt] = useState<BeforeInstallPromptEvent | null>(null);
  const [isStandalone, setIsStandalone] = useState(false);
  const [isIOS, setIsIOS] = useState(false);
  const [dismissed, setDismissed] = useState(false);

  useEffect(() => {
    queueMicrotask(() => {
      setIsStandalone(window.matchMedia("(display-mode: standalone)").matches);
      setIsIOS(/iPad|iPhone|iPod/.test(navigator.userAgent) && !("MSStream" in window));
    });

    function handleBeforeInstallPrompt(event: Event) {
      event.preventDefault();
      setDeferredPrompt(event as BeforeInstallPromptEvent);
    }

    window.addEventListener("beforeinstallprompt", handleBeforeInstallPrompt);
    return () => window.removeEventListener("beforeinstallprompt", handleBeforeInstallPrompt);
  }, []);

  async function handleInstallClick() {
    if (!deferredPrompt) return;
    await deferredPrompt.prompt();
    await deferredPrompt.userChoice;
    setDeferredPrompt(null);
  }

  if (isStandalone || dismissed) return null;
  if (!deferredPrompt && !isIOS) return null;

  return (
    <div className="flex items-center justify-between gap-3 rounded-md border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800">
      {deferredPrompt && (
        <>
          <span>ثبّت تطبيق {appLabel} على جهازك للوصول السريع.</span>
          <button
            onClick={handleInstallClick}
            className="shrink-0 rounded-md bg-blue-600 px-3 py-1.5 font-medium text-white hover:bg-blue-700"
          >
            تثبيت
          </button>
        </>
      )}

      {isIOS && !deferredPrompt && (
        <span>
          لتثبيت تطبيق {appLabel}: اضغط على زر المشاركة <span aria-hidden="true">⎋</span> ثم اختر
          «إضافة إلى الشاشة الرئيسية».
        </span>
      )}

      <button
        onClick={() => setDismissed(true)}
        className="shrink-0 text-blue-400 hover:text-blue-600"
        aria-label="إغلاق"
      >
        ×
      </button>
    </div>
  );
}
