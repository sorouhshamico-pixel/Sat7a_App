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
  PAYMENT_METHOD_LABELS,
  PAYMENT_STATUS_LABELS,
  REFUND_STATUS_LABELS,
  paymentStatusTone,
} from "@/lib/payments";
import type { PaymentListItem, RefundItem } from "@/lib/types/payment";

const REFUNDABLE_STATUSES = new Set(["captured", "partially_refunded"]);

export default function PaymentDetailPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = use(params);
  const queryClient = useQueryClient();
  const [error, setError] = useState<string | null>(null);
  const [amount, setAmount] = useState("");
  const [reason, setReason] = useState("");

  const paymentQuery = useQuery({
    queryKey: ["admin-payment", id],
    queryFn: () =>
      apiGet<{ payment: PaymentListItem; refunds: RefundItem[] }>(`admin/payments/${id}`),
  });

  const refundMutation = useMutation({
    mutationFn: () =>
      apiPost(`admin/payments/${id}/refund`, {
        amount: Number(amount),
        reason: reason || undefined,
      }),
    onSuccess: () => {
      setError(null);
      setAmount("");
      setReason("");
      queryClient.invalidateQueries({ queryKey: ["admin-payment", id] });
    },
    onError: (err) => setError(err instanceof ApiRequestError ? err.message : "حدث خطأ غير متوقع."),
  });

  if (paymentQuery.isLoading) return <Spinner />;
  if (paymentQuery.isError || !paymentQuery.data) return <Alert>تعذّر تحميل بيانات الدفعة.</Alert>;

  const { payment, refunds } = paymentQuery.data.data;
  const canRefund = REFUNDABLE_STATUSES.has(payment.status);

  return (
    <div className="flex flex-col gap-4">
      <div className="flex items-center justify-between">
        <h1 className="text-xl font-bold text-gray-900">دفعة #{payment.id.slice(0, 8)}</h1>
        <Badge tone={paymentStatusTone(payment.status)}>
          {PAYMENT_STATUS_LABELS[payment.status] ?? payment.status}
        </Badge>
      </div>

      {error && <Alert>{error}</Alert>}

      <Card>
        <CardTitle>التفاصيل</CardTitle>
        <dl className="grid grid-cols-2 gap-y-2 text-sm">
          <dt className="text-gray-500">الطريقة</dt>
          <dd>{PAYMENT_METHOD_LABELS[payment.method] ?? payment.method}</dd>
          <dt className="text-gray-500">المبلغ</dt>
          <dd>{payment.amount} هللة</dd>
          <dt className="text-gray-500">المسترد</dt>
          <dd>{payment.refunded_amount} هللة</dd>
          {payment.card_brand && (
            <>
              <dt className="text-gray-500">البطاقة</dt>
              <dd dir="ltr">
                {payment.card_brand} •••• {payment.card_last_four}
              </dd>
            </>
          )}
          {payment.failure_reason && (
            <>
              <dt className="text-gray-500">سبب الفشل</dt>
              <dd>{payment.failure_reason}</dd>
            </>
          )}
        </dl>
      </Card>

      {canRefund && (
        <Card>
          <CardTitle>استرداد</CardTitle>
          <form
            onSubmit={(event) => {
              event.preventDefault();
              refundMutation.mutate();
            }}
            className="flex flex-wrap items-end gap-2"
          >
            <label className="flex flex-col gap-1 text-sm text-gray-600">
              المبلغ (هللة)
              <Input
                type="number"
                min={1}
                value={amount}
                onChange={(event) => setAmount(event.target.value)}
                required
              />
            </label>
            <label className="flex flex-1 flex-col gap-1 text-sm text-gray-600">
              السبب (اختياري)
              <Input value={reason} onChange={(event) => setReason(event.target.value)} />
            </label>
            <Button type="submit" variant="danger" disabled={refundMutation.isPending}>
              {refundMutation.isPending ? <Spinner /> : "استرداد"}
            </Button>
          </form>
        </Card>
      )}

      <Card>
        <CardTitle>سجل الاسترداد</CardTitle>
        {refunds.length === 0 && <p className="text-sm text-gray-500">لا توجد عمليات استرداد.</p>}
        {refunds.length > 0 && (
          <table className="w-full text-sm">
            <thead className="text-right text-gray-500">
              <tr>
                <th className="py-1 font-medium">المبلغ</th>
                <th className="py-1 font-medium">السبب</th>
                <th className="py-1 font-medium">الحالة</th>
                <th className="py-1 font-medium">التاريخ</th>
              </tr>
            </thead>
            <tbody>
              {refunds.map((refund) => (
                <tr key={refund.id} className="border-t border-gray-100">
                  <td className="py-1">{refund.amount} هللة</td>
                  <td className="py-1">{refund.reason ?? "—"}</td>
                  <td className="py-1">{REFUND_STATUS_LABELS[refund.status] ?? refund.status}</td>
                  <td className="py-1 text-gray-500">
                    {new Date(refund.created_at).toLocaleString("ar-SA")}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </Card>
    </div>
  );
}
