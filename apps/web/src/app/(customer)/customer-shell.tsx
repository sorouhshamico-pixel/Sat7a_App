"use client";

import Link from "next/link";
import { usePathname, useRouter } from "next/navigation";
import { cn } from "@/lib/cn";
import type { CustomerSessionUser } from "@/lib/customer-session";

export function CustomerShell({
  user,
  children,
}: {
  user: CustomerSessionUser | null;
  children: React.ReactNode;
}) {
  const pathname = usePathname();
  const router = useRouter();

  async function handleLogout() {
    await fetch("/api/auth/customer/logout", { method: "POST" });
    router.push("/");
    router.refresh();
  }

  return (
    <div className="flex min-h-screen flex-col">
      <header className="flex items-center justify-between border-b border-gray-200 px-4 py-3 sm:px-6">
        <Link href="/" className="text-lg font-bold text-gray-900">
          منصة سطحات الرياض
        </Link>

        <nav className="flex items-center gap-4 text-sm">
          <Link
            href="/"
            className={cn(
              "font-medium",
              pathname === "/" ? "text-blue-600" : "text-gray-600 hover:text-gray-900",
            )}
          >
            الرئيسية
          </Link>

          {user ? (
            <>
              <Link
                href="/orders"
                className={cn(
                  "font-medium",
                  pathname.startsWith("/orders")
                    ? "text-blue-600"
                    : "text-gray-600 hover:text-gray-900",
                )}
              >
                طلباتي
              </Link>
              <Link
                href="/vehicles"
                className={cn(
                  "font-medium",
                  pathname.startsWith("/vehicles")
                    ? "text-blue-600"
                    : "text-gray-600 hover:text-gray-900",
                )}
              >
                مركباتي
              </Link>
              <button
                onClick={handleLogout}
                className="font-medium text-gray-600 hover:text-gray-900"
              >
                تسجيل الخروج
              </button>
            </>
          ) : (
            <Link href="/login" className="font-medium text-gray-600 hover:text-gray-900">
              تسجيل الدخول
            </Link>
          )}
        </nav>
      </header>

      <main className="flex flex-1 flex-col p-4 sm:p-6">{children}</main>
    </div>
  );
}
