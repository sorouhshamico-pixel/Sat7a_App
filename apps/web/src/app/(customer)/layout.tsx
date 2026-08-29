import { getCustomerSessionUser } from "@/lib/customer-session";
import { QueryProvider } from "@/components/query-provider";
import { CustomerShell } from "./customer-shell";

export default async function CustomerLayout({ children }: { children: React.ReactNode }) {
  const user = await getCustomerSessionUser();

  return (
    <QueryProvider>
      <CustomerShell user={user}>{children}</CustomerShell>
    </QueryProvider>
  );
}
