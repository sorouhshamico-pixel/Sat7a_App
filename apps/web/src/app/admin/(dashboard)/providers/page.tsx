"use client";

import Link from "next/link";
import { useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { apiGet } from "@/lib/api/client";
import { Badge } from "@/components/ui/badge";
import { Spinner } from "@/components/ui/spinner";
import { Alert } from "@/components/ui/alert";
import { PROVIDER_STATUS_LABELS, providerStatusTone } from "@/lib/providers";
import type { ProviderListItem } from "@/lib/types/provider";

export default function ProvidersListPage() {
  const [status, setStatus] = useState("");

  const { data, isLoading, isError } = useQuery({
    queryKey: ["admin-providers", status],
    queryFn: () =>
      apiGet<{ providers: ProviderListItem[] }>("admin/providers", status ? { status } : undefined),
  });

  return (
    <div className="flex flex-col gap-4">
      <div className="flex items-center justify-between">
        <h1 className="text-xl font-bold text-gray-900">مزودو الخدمة</h1>

        <select
          value={status}
          onChange={(event) => setStatus(event.target.value)}
          className="rounded-md border border-gray-300 px-3 py-2 text-sm"
        >
          <option value="">كل الحالات</option>
          {Object.entries(PROVIDER_STATUS_LABELS).map(([value, label]) => (
            <option key={value} value={value}>
              {label}
            </option>
          ))}
        </select>
      </div>

      {isLoading && <Spinner />}
      {isError && <Alert>تعذّر تحميل مزودي الخدمة.</Alert>}

      {data && (
        <div className="overflow-x-auto rounded-lg border border-gray-200 bg-white">
          <table className="w-full text-sm">
            <thead className="bg-gray-50 text-right text-gray-600">
              <tr>
                <th className="px-4 py-2 font-medium">الاسم التجاري</th>
                <th className="px-4 py-2 font-medium">الهاتف</th>
                <th className="px-4 py-2 font-medium">التقييم</th>
                <th className="px-4 py-2 font-medium">الحالة</th>
                <th className="px-4 py-2 font-medium">تاريخ التسجيل</th>
              </tr>
            </thead>
            <tbody>
              {data.data.providers.map((provider) => (
                <tr key={provider.id} className="border-t border-gray-100 hover:bg-gray-50">
                  <td className="px-4 py-2">
                    <Link
                      href={`/admin/providers/${provider.id}`}
                      className="font-medium text-blue-600 hover:underline"
                    >
                      {provider.business_name}
                    </Link>
                  </td>
                  <td className="px-4 py-2 text-gray-600" dir="ltr">
                    {provider.contact_phone}
                  </td>
                  <td className="px-4 py-2 text-gray-600">{provider.rating ?? "—"}</td>
                  <td className="px-4 py-2">
                    <Badge tone={providerStatusTone(provider.status)}>
                      {PROVIDER_STATUS_LABELS[provider.status] ?? provider.status}
                    </Badge>
                  </td>
                  <td className="px-4 py-2 text-gray-600">
                    {new Date(provider.created_at).toLocaleString("ar-SA")}
                  </td>
                </tr>
              ))}
              {data.data.providers.length === 0 && (
                <tr>
                  <td colSpan={5} className="px-4 py-6 text-center text-gray-500">
                    لا يوجد مزودو خدمة مطابقون.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}
