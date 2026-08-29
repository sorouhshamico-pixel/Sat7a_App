import { getProviderSessionUser } from "@/lib/provider-session";
import { QueryProvider } from "@/components/query-provider";
import { ProviderShell } from "./provider-shell";

// Route group — every page under here shares this authenticated shell
// (sidebar + header); /provider/login lives outside the group and renders
// standalone (see src/app/provider/login/page.tsx), same structure as the
// admin app (src/app/admin/(dashboard)/layout.tsx).
export default async function ProviderDashboardLayout({ children }: { children: React.ReactNode }) {
  const user = await getProviderSessionUser();

  return (
    <QueryProvider>
      <ProviderShell user={user}>{children}</ProviderShell>
    </QueryProvider>
  );
}
