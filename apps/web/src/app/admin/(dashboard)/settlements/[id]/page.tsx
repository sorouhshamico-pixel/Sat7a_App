"use client";

import { use, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { apiGet, apiPost } from "@/lib/api/client";
import { ApiRequestError } from "@/lib/api/types";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Alert } from "@/components/ui/alert";
import { Spinner } from "@/components/ui/spinner";
import {
  SETTLEMENT_NEXT_STATUS,
  SETTLEMENT_STATUS_LABELS,
  settlementStatusTone,
} from "@/lib/settlements";
import type { SettlementBatchItem } from "@/lib/types/settlement";

export default function SettlementDetailPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = use(params);
  const queryClient = useQueryClient();
  const [error, setError] = useState<string | null>(null);
  const [reference, setReference] = useState("");
  const [failureReason, setFailureReason] = useState("");

  const settlementQuery = useQuery({
    queryKey: ["admin-settlement", id],
    queryFn: () => apiGet<{ settlement: SettlementBatchItem }>(`admin/settlements/${id}`),
  });

  const advanceMutation = useMutation({
    mutationFn: (status: string) =>
      apiPost(`admin/settlements/${id}/status`, {
        status,
        reference: reference || undefined,
        failure_reason: failureReason || undefined,
      }),
    onSuccess: () => {
      setError(null);
      queryClient.invalidateQueries({ queryKey: ["admin-settlement", id] });
    },
    onError: (err) => setError(err instanceof ApiRequestError ? err.message : "حدث خطأ غير متوقع."),
  });

  if (settlementQuery.isLoading) return <Spinner />;
  if (settlementQuery.isError || !settlementQuery.data)
    return <Alert>تعذّر تحميل بيانات التسوية.</Alert>;

  const batch = settlementQuery.data.data.settlement;
  const nextStatuses = SETTLEMENT_NEXT_STATUS[batch.status] ?? [];

  return (
    <div className="flex flex-col gap-4">
      <div className="flex items-center justify-between">
        <h1 className="text-xl font-bold text-gray-900">تسوية #{batch.id.slice(0, 8)}</h1>
        <Badge tone={settlementStatusTone(batch.status)}>
          {SETTLEMENT_STATUS_LABELS[batch.status] ?? batch.status}
        </Badge>
      </div>

      {error && <Alert>{error}</Alert>}

      <Card>
        <CardTitle>التفاصيل</CardTitle>
        <dl className="grid grid-cols-2 gap-y-2 text-sm">
          <dt className="text-gray-500">الفترة</dt>
          <dd>
            {batch.period_start} — {batch.period_end}
          </dd>
          <dt className="text-gray-500">الإجمالي</dt>
          <dd>{batch.gross} هللة</dd>
          <dt className="text-gray-500">العمولة</dt>
          <dd>{batch.commission} هللة</dd>
          <dt className="text-gray-500">الخصومات</dt>
          <dd>{batch.deductions} هللة</dd>
          <dt className="text-gray-500">الصافي</dt>
          <dd>{batch.net} هللة</dd>
          {batch.reference && (
            <>
              <dt className="text-gray-500">المرجع</dt>
              <dd>{batch.reference}</dd>
            </>
          )}
          {batch.failure_reason && (
            <>
              <dt className="text-gray-500">سبب الفشل</dt>
              <dd>{batch.failure_reason}</dd>
            </>
          )}
        </dl>
      </Card>

      {nextStatuses.length > 0 && (
        <Card>
          <CardTitle>تقدّم الحالة</CardTitle>
          <div className="flex flex-col gap-2">
            {nextStatuses.includes("paid") && (
              <Input
                placeholder="مرجع التحويل (اختياري)"
                value={reference}
                onChange={(event) => setReference(event.target.value)}
              />
            )}
            {nextStatuses.includes("failed") && (
              <Input
                placeholder="سبب الفشل (اختياري)"
                value={failureReason}
                onChange={(event) => setFailureReason(event.target.value)}
              />
            )}
            <div className="flex flex-wrap gap-2">
              {nextStatuses.map((status) => (
                <Button
                  key={status}
                  variant={status === "cancelled" || status === "failed" ? "danger" : "primary"}
                  disabled={advanceMutation.isPending}
                  onClick={() => advanceMutation.mutate(status)}
                >
                  {advanceMutation.isPending ? (
                    <Spinner />
                  ) : (
                    (SETTLEMENT_STATUS_LABELS[status] ?? status)
                  )}
                </Button>
              ))}
            </div>
          </div>
        </Card>
      )}
    </div>
  );
}
