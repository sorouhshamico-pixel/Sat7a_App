"use client";

import { use } from "react";
import { useQuery } from "@tanstack/react-query";
import { apiGet } from "@/lib/api/client";
import { Badge } from "@/components/ui/badge";
import { Card, CardTitle } from "@/components/ui/card";
import { Alert } from "@/components/ui/alert";
import { Spinner } from "@/components/ui/spinner";
import { SETTLEMENT_STATUS_LABELS, settlementStatusTone } from "@/lib/settlements";
import type { SettlementBatchItem } from "@/lib/types/settlement";

// Read-only — advancing a settlement's status (approve/mark paid/etc.) is
// a finance-staff action, done from the admin app (see
// docs/FINANCE_COMPLIANCE_ADMIN.md); a provider only ever views progress.
export default function ProviderSettlementDetailPage({
  params,
}: {
  params: Promise<{ id: string }>;
}) {
  const { id } = use(params);

  const settlementQuery = useQuery({
    queryKey: ["provider-settlement", id],
    queryFn: () => apiGet<{ settlement: SettlementBatchItem }>(`providers/me/settlements/${id}`),
  });

  if (settlementQuery.isLoading) return <Spinner />;
  if (settlementQuery.isError || !settlementQuery.data)
    return <Alert>تعذّر تحميل بيانات التسوية.</Alert>;

  const batch = settlementQuery.data.data.settlement;

  return (
    <div className="flex flex-col gap-4">
      <div className="flex items-center justify-between">
        <h1 className="text-xl font-bold text-gray-900">تسوية #{batch.id.slice(0, 8)}</h1>
        <Badge tone={settlementStatusTone(batch.status)}>
          {SETTLEMENT_STATUS_LABELS[batch.status] ?? batch.status}
        </Badge>
      </div>

      {batch.status === "failed" && batch.failure_reason && (
        <Alert>سبب الفشل: {batch.failure_reason}</Alert>
      )}

      <Card>
        <CardTitle>تفاصيل التسوية</CardTitle>
        <dl className="grid grid-cols-2 gap-y-2 text-sm">
          <dt className="text-gray-500">الفترة</dt>
          <dd>
            {batch.period_start} — {batch.period_end}
          </dd>
          <dt className="text-gray-500">الإجمالي (قبل الخصم)</dt>
          <dd>{(batch.gross / 100).toFixed(2)} ر.س</dd>
          <dt className="text-gray-500">عمولة المنصة</dt>
          <dd>{(batch.commission / 100).toFixed(2)} ر.س</dd>
          <dt className="text-gray-500">خصومات أخرى</dt>
          <dd>{(batch.deductions / 100).toFixed(2)} ر.س</dd>
          <dt className="text-gray-500">الصافي</dt>
          <dd className="font-semibold">{(batch.net / 100).toFixed(2)} ر.س</dd>
          {batch.reference && (
            <>
              <dt className="text-gray-500">المرجع</dt>
              <dd dir="ltr">{batch.reference}</dd>
            </>
          )}
          {batch.paid_at && (
            <>
              <dt className="text-gray-500">تاريخ الدفع</dt>
              <dd>{new Date(batch.paid_at).toLocaleString("ar-SA")}</dd>
            </>
          )}
        </dl>
      </Card>
    </div>
  );
}
