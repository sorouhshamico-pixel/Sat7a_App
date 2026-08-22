"use client";

import Link from "next/link";
import { usePathname, useRouter } from "next/navigation";
import { cn } from "@/lib/cn";
import type { SessionUser } from "@/lib/session";

const NAV_ITEMS = [
  { href: "/admin", label: "لوحة التحكم" },
  { href: "/admin/orders", label: "الطلبات" },
  { href: "/admin/disputes", label: "النزاعات" },
  { href: "/admin/providers", label: "مزودو الخدمة" },
  { href: "/admin/payments", label: "المدفوعات" },
  { href: "/admin/settlements", label: "التسويات" },
];

export function AdminShell({
  user,
  children,
}: {
  user: SessionUser | null;
  children: React.ReactNode;
}) {
  const pathname = usePathname();
  const router = useRouter();

  async function handleLogout() {
    await fetch("/api/auth/logout", { method: "POST" });
    router.push("/admin/login");
    router.refresh();
  }

  return (
    <div className="flex min-h-screen">
      <aside className="w-60 shrink-0 border-l border-gray-200 bg-gray-50 p-4">
        <div className="mb-6 px-2 text-lg font-bold text-gray-900">مركز العمليات</div>
        <nav className="flex flex-col gap-1">
          {NAV_ITEMS.map((item) => {
            const active =
              item.href === "/admin" ? pathname === "/admin" : pathname.startsWith(item.href);

            return (
              <Link
                key={item.href}
                href={item.href}
                className={cn(
                  "rounded-md px-3 py-2 text-sm font-medium",
                  active ? "bg-blue-600 text-white" : "text-gray-700 hover:bg-gray-100",
                )}
              >
                {item.label}
              </Link>
            );
          })}
        </nav>
      </aside>

      <div className="flex flex-1 flex-col">
        <header className="flex items-center justify-between border-b border-gray-200 px-6 py-3">
          <span className="text-sm text-gray-600">{user ? `مرحباً، ${user.name}` : ""}</span>
          <button
            onClick={handleLogout}
            className="text-sm font-medium text-gray-600 hover:text-gray-900"
          >
            تسجيل الخروج
          </button>
        </header>

        <main className="flex-1 p-6">{children}</main>
      </div>
    </div>
  );
}
