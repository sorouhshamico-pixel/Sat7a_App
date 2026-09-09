import { getSessionUser } from "@/lib/session";
import { QueryProvider } from "@/components/query-provider";
import { AdminShell } from "./admin-shell";

// Route group — every page under here shares this authenticated shell
// (sidebar + header); /admin/login lives outside the group and renders
// standalone (see src/app/admin/login/page.tsx).
export default async function DashboardLayout({ children }: { children: React.ReactNode }) {
  const user = await getSessionUser();

  return (
    <QueryProvider loginPath="/admin/login" logoutPath="/api/auth/logout">
      <AdminShell user={user}>{children}</AdminShell>
    </QueryProvider>
  );
}
