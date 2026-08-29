"use client";

import Link from "next/link";
import { usePathname, useRouter } from "next/navigation";
import { cn } from "@/lib/cn";
import type { ProviderSessionUser } from "@/lib/provider-session";

// Shown to every logged-in provider-staff account regardless of role
// (owner / fleet manager / driver) — the backend's own permission gates
// are the real boundary, and a denied action surfaces its 403 inline,
// same decision Phase 17 made for the admin app (see
// docs/OPERATIONS_COMMAND_CENTER.md §Not yet in this phase). A driver
// account simply sees empty lists on the office-side screens.
const NAV_ITEMS = [
  { href: "/provider", label: "لوحة التحكم" },
  { href: "/provider/trips", label: "رحلاتي" },
  { href: "/provider/fleet", label: "الأسطول" },
  { href: "/provider/drivers", label: "السائقون" },
  { href: "/provider/documents", label: "المستندات" },
  { href: "/provider/bank-account", label: "الحساب البنكي" },
  { href: "/provider/settlements", label: "التسويات" },
  { href: "/provider/reviews", label: "التقييمات" },
];

export function ProviderShell({
  user,
  children,
}: {
  user: ProviderSessionUser | null;
  children: React.ReactNode;
}) {
  const pathname = usePathname();
  const router = useRouter();

  async function handleLogout() {
    await fetch("/api/auth/provider/logout", { method: "POST" });
    router.push("/provider/login");
    router.refresh();
  }

  return (
    <div className="flex min-h-screen">
      <aside className="w-60 shrink-0 border-l border-gray-200 bg-gray-50 p-4">
        <div className="mb-6 px-2 text-lg font-bold text-gray-900">لوحة مزودي الخدمة</div>
        <nav className="flex flex-col gap-1">
          {NAV_ITEMS.map((item) => {
            const active =
              item.href === "/provider" ? pathname === "/provider" : pathname.startsWith(item.href);

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
          <span className="text-sm text-gray-600" dir="ltr">
            {user ? (user.name ?? user.phone) : ""}
          </span>
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
