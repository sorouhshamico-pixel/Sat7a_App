"use client";

import Link from "next/link";
import { useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { apiGet } from "@/lib/api/client";
import { Badge } from "@/components/ui/badge";
import { Card } from "@/components/ui/card";
import { Spinner } from "@/components/ui/spinner";
import { Alert } from "@/components/ui/alert";
import { Pagination } from "@/components/pagination";
import { SETTLEMENT_STATUS_LABELS, settlementStatusTone } from "@/lib/settlements";
import type { SettlementBatchItem } from "@/lib/types/settlement";
import type { ProviderBalance } from "@/lib/types/provider";

const PAGE_SIZE = 20;

export default function ProviderSettlementsPage() {
  const [page, setPage] = useState(1);

  const balanceQuery = useQuery({
    queryKey: ["provider-balance"],
    queryFn: () => apiGet<{ balance: ProviderBalance }>("providers/me/balance"),
  });

  const settlementsQuery = useQuery({
    queryKey: ["provider-settlements", page],
    queryFn: () =>
      apiGet<{ settlements: SettlementBatchItem[] }>("providers/me/settlements", {
        page: String(page),
        per_page: String(PAGE_SIZE),
      }),
  });

  const balance = balanceQuery.data?.data.balance;
  const settlements = settlementsQuery.data?.data.settlements ?? [];

  return (
    <div className="flex flex-col gap-4">
      <h1 className="text-xl font-bold text-gray-900">الرصيد والتسويات</h1>

      {balanceQuery.isLoading && <Spinner />}
      {balance && (
        <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
          <Card>
            <p className="text-xs text-gray-500">قيد الانتظار</p>
            <p className="mt-1 text-lg font-bold text-gray-900">
              {(balance.pending_balance / 100).toFixed(2)} ر.س
            </p>
          </Card>
          <Card>
            <p className="text-xs text-gray-500">متاح للتسوية</p>
            <p className="mt-1 text-lg font-bold text-gray-900">
              {(balance.available_balance / 100).toFixed(2)} ر.س
            </p>
          </Card>
          <Card>
            <p className="text-xs text-gray-500">تم تسويته</p>
            <p className="mt-1 text-lg font-bold text-gray-900">
              {(balance.settled_balance / 100).toFixed(2)} ر.س
            </p>
          </Card>
          <Card>
            <p className="text-xs text-gray-500">إجمالي المستحق</p>
            <p className="mt-1 text-lg font-bold text-gray-900">
              {(balance.total_payable / 100).toFixed(2)} ر.س
            </p>
          </Card>
        </div>
      )}

      {settlementsQuery.isLoading && <Spinner />}
      {settlementsQuery.isError && <Alert>تعذّر تحميل التسويات.</Alert>}

      {settlementsQuery.data && (
        <div className="overflow-x-auto rounded-lg border border-gray-200 bg-white">
          <table className="w-full text-sm">
            <thead className="bg-gray-50 text-right text-gray-600">
              <tr>
                <th className="px-4 py-2 font-medium">الدفعة</th>
                <th className="px-4 py-2 font-medium">الفترة</th>
                <th className="px-4 py-2 font-medium">الصافي</th>
                <th className="px-4 py-2 font-medium">الحالة</th>
                <th className="px-4 py-2 font-medium">تاريخ الإنشاء</th>
              </tr>
            </thead>
            <tbody>
              {settlements.map((batch) => (
                <tr key={batch.id} className="border-t border-gray-100 hover:bg-gray-50">
                  <td className="px-4 py-2">
                    <Link
                      href={`/provider/settlements/${batch.id}`}
                      className="font-medium text-blue-600 hover:underline"
                    >
                      {batch.id.slice(0, 8)}
                    </Link>
                  </td>
                  <td className="px-4 py-2 text-gray-600">
                    {batch.period_start} — {batch.period_end}
                  </td>
                  <td className="px-4 py-2">{(batch.net / 100).toFixed(2)} ر.س</td>
                  <td className="px-4 py-2">
                    <Badge tone={settlementStatusTone(batch.status)}>
                      {SETTLEMENT_STATUS_LABELS[batch.status] ?? batch.status}
                    </Badge>
                  </td>
                  <td className="px-4 py-2 text-gray-600">
                    {new Date(batch.created_at).toLocaleString("ar-SA")}
                  </td>
                </tr>
              ))}
              {settlements.length === 0 && (
                <tr>
                  <td colSpan={5} className="px-4 py-6 text-center text-gray-500">
                    لا توجد تسويات بعد.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      )}

      {settlementsQuery.data && (
        <Pagination
          page={page}
          onPageChange={setPage}
          total={Number(settlementsQuery.data.meta.total ?? 0)}
          itemCount={settlements.length}
          pageSize={PAGE_SIZE}
        />
      )}
    </div>
  );
}
