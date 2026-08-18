import { cn } from "@/lib/cn";

export function Alert({
  tone = "danger",
  children,
}: {
  tone?: "danger" | "info";
  children: React.ReactNode;
}) {
  return (
    <div
      role="alert"
      className={cn(
        "rounded-md border px-4 py-3 text-sm",
        tone === "danger"
          ? "border-red-200 bg-red-50 text-red-800"
          : "border-blue-200 bg-blue-50 text-blue-800",
      )}
    >
      {children}
    </div>
  );
}
