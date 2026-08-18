"use client";

import Link from "next/link";
import { useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { apiGet } from "@/lib/api/client";
import { Badge } from "@/components/ui/badge";
import { Spinner } from "@/components/ui/spinner";
import { Alert } from "@/components/ui/alert";
import { DISPUTE_REASON_LABELS, DISPUTE_STATUS_LABELS, disputeStatusTone } from "@/lib/disputes";
import type { DisputeListItem } from "@/lib/types/dispute";

export default function DisputesListPage() {
  const [status, setStatus] = useState("");

  const { data, isLoading, isError } = useQuery({
    queryKey: ["admin-disputes", status],
    queryFn: () =>
      apiGet<{ disputes: DisputeListItem[] }>("admin/disputes", status ? { status } : undefined),
  });

  return (
    <div className="flex flex-col gap-4">
      <div className="flex items-center justify-between">
        <h1 className="text-xl font-bold text-gray-900">النزاعات</h1>

        <select
          value={status}
          onChange={(event) => setStatus(event.target.value)}
          className="rounded-md border border-gray-300 px-3 py-2 text-sm"
        >
          <option value="">كل الحالات</option>
          {Object.entries(DISPUTE_STATUS_LABELS).map(([value, label]) => (
            <option key={value} value={value}>
              {label}
            </option>
          ))}
        </select>
      </div>

      {isLoading && <Spinner />}
      {isError && <Alert>تعذّر تحميل النزاعات.</Alert>}

      {data && (
        <div className="overflow-x-auto rounded-lg border border-gray-200 bg-white">
          <table className="w-full text-sm">
            <thead className="bg-gray-50 text-right text-gray-600">
              <tr>
                <th className="px-4 py-2 font-medium">النزاع</th>
                <th className="px-4 py-2 font-medium">السبب</th>
                <th className="px-4 py-2 font-medium">الحالة</th>
                <th className="px-4 py-2 font-medium">تاريخ الإنشاء</th>
              </tr>
            </thead>
            <tbody>
              {data.data.disputes.map((dispute) => (
                <tr key={dispute.id} className="border-t border-gray-100 hover:bg-gray-50">
                  <td className="px-4 py-2">
                    <Link
                      href={`/admin/disputes/${dispute.id}`}
                      className="font-medium text-blue-600 hover:underline"
                    >
                      {dispute.id.slice(0, 8)}
                    </Link>
                  </td>
                  <td className="px-4 py-2">
                    {DISPUTE_REASON_LABELS[dispute.reason] ?? dispute.reason}
                  </td>
                  <td className="px-4 py-2">
                    <Badge tone={disputeStatusTone(dispute.status)}>
                      {DISPUTE_STATUS_LABELS[dispute.status] ?? dispute.status}
                    </Badge>
                  </td>
                  <td className="px-4 py-2 text-gray-600">
                    {new Date(dispute.created_at).toLocaleString("ar-SA")}
                  </td>
                </tr>
              ))}
              {data.data.disputes.length === 0 && (
                <tr>
                  <td colSpan={4} className="px-4 py-6 text-center text-gray-500">
                    لا توجد نزاعات مطابقة.
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
