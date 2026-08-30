"use client";

import Link from "next/link";
import { useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { apiGet } from "@/lib/api/client";
import { Badge } from "@/components/ui/badge";
import { Spinner } from "@/components/ui/spinner";
import { Alert } from "@/components/ui/alert";
import { Pagination } from "@/components/pagination";
import { SETTLEMENT_STATUS_LABELS, settlementStatusTone } from "@/lib/settlements";
import type { SettlementBatchItem } from "@/lib/types/settlement";

const PAGE_SIZE = 20;

export default function SettlementsListPage() {
  const [status, setStatus] = useState("");
  const [page, setPage] = useState(1);

  const { data, isLoading, isError } = useQuery({
    queryKey: ["admin-settlements", status, page],
    queryFn: () =>
      apiGet<{ settlements: SettlementBatchItem[] }>("admin/settlements", {
        ...(status ? { status } : {}),
        page: String(page),
        per_page: String(PAGE_SIZE),
      }),
  });

  return (
    <div className="flex flex-col gap-4">
      <div className="flex items-center justify-between">
        <h1 className="text-xl font-bold text-gray-900">التسويات</h1>

        <select
          value={status}
          onChange={(event) => {
            setStatus(event.target.value);
            setPage(1);
          }}
          className="rounded-md border border-gray-300 px-3 py-2 text-sm"
        >
          <option value="">كل الحالات</option>
          {Object.entries(SETTLEMENT_STATUS_LABELS).map(([value, label]) => (
            <option key={value} value={value}>
              {label}
            </option>
          ))}
        </select>
      </div>

      {isLoading && <Spinner />}
      {isError && <Alert>تعذّر تحميل التسويات.</Alert>}

      {data && (
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
              {data.data.settlements.map((batch) => (
                <tr key={batch.id} className="border-t border-gray-100 hover:bg-gray-50">
                  <td className="px-4 py-2">
                    <Link
                      href={`/admin/settlements/${batch.id}`}
                      className="font-medium text-blue-600 hover:underline"
                    >
                      {batch.id.slice(0, 8)}
                    </Link>
                  </td>
                  <td className="px-4 py-2 text-gray-600">
                    {batch.period_start} — {batch.period_end}
                  </td>
                  <td className="px-4 py-2">{batch.net} هللة</td>
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
              {data.data.settlements.length === 0 && (
                <tr>
                  <td colSpan={5} className="px-4 py-6 text-center text-gray-500">
                    لا توجد تسويات مطابقة.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      )}

      {data && (
        <Pagination
          page={page}
          onPageChange={setPage}
          total={Number(data.meta.total ?? 0)}
          itemCount={data.data.settlements.length}
          pageSize={PAGE_SIZE}
        />
      )}
    </div>
  );
}
