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
  CANCELLABLE_ORDER_STATUSES,
  DISPATCHABLE_ORDER_STATUSES,
  orderStatusLabel,
  orderStatusTone,
} from "@/lib/orders";
import type { DispatchOffer, OrderDetail } from "@/lib/types/order";

const OFFER_STATUS_LABELS: Record<string, string> = {
  pending: "بانتظار الرد",
  accepted: "مقبول",
  rejected: "مرفوض",
  expired: "منتهي",
  superseded: "تم استبداله",
};

export default function OrderDetailPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = use(params);
  const queryClient = useQueryClient();
  const [actionError, setActionError] = useState<string | null>(null);
  const [towTruckId, setTowTruckId] = useState("");
  const [assignReason, setAssignReason] = useState("");
  const [cancelReason, setCancelReason] = useState("");
  const [showCancelForm, setShowCancelForm] = useState(false);
  const [showAssignForm, setShowAssignForm] = useState(false);

  const orderQuery = useQuery({
    queryKey: ["admin-order", id],
    queryFn: () => apiGet<OrderDetail>(`admin/orders/${id}`),
  });

  const offersQuery = useQuery({
    queryKey: ["admin-order-offers", id],
    queryFn: () => apiGet<{ offers: DispatchOffer[] }>(`admin/orders/${id}/dispatch-offers`),
  });

  function invalidate() {
    queryClient.invalidateQueries({ queryKey: ["admin-order", id] });
    queryClient.invalidateQueries({ queryKey: ["admin-order-offers", id] });
  }

  function handleError(error: unknown) {
    setActionError(error instanceof ApiRequestError ? error.message : "حدث خطأ غير متوقع.");
  }

  const retryMutation = useMutation({
    mutationFn: () => apiPost(`admin/orders/${id}/dispatch/retry`),
    onSuccess: () => {
      setActionError(null);
      invalidate();
    },
    onError: handleError,
  });

  const assignMutation = useMutation({
    mutationFn: () =>
      apiPost(`admin/orders/${id}/dispatch/assign`, {
        tow_truck_id: towTruckId,
        reason: assignReason || undefined,
      }),
    onSuccess: () => {
      setActionError(null);
      setShowAssignForm(false);
      setTowTruckId("");
      setAssignReason("");
      invalidate();
    },
    onError: handleError,
  });

  const cancelMutation = useMutation({
    mutationFn: () => apiPost(`admin/orders/${id}/cancel`, { reason: cancelReason }),
    onSuccess: () => {
      setActionError(null);
      setShowCancelForm(false);
      setCancelReason("");
      invalidate();
    },
    onError: handleError,
  });

  if (orderQuery.isLoading) return <Spinner />;
  if (orderQuery.isError || !orderQuery.data) return <Alert>تعذّر تحميل بيانات الطلب.</Alert>;

  const order = orderQuery.data.data;
  const canDispatch = DISPATCHABLE_ORDER_STATUSES.has(order.status);
  const canCancel = CANCELLABLE_ORDER_STATUSES.has(order.status);

  return (
    <div className="flex flex-col gap-4">
      <div className="flex items-center justify-between">
        <h1 className="text-xl font-bold text-gray-900">طلب #{order.id.slice(0, 8)}</h1>
        <Badge tone={orderStatusTone(order.status)}>{orderStatusLabel(order.status)}</Badge>
      </div>

      {actionError && <Alert>{actionError}</Alert>}

      <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <Card>
          <CardTitle>تفاصيل الطلب</CardTitle>
          <dl className="grid grid-cols-2 gap-y-2 text-sm">
            <dt className="text-gray-500">نوع الخدمة</dt>
            <dd>{order.service_type}</dd>
            <dt className="text-gray-500">من</dt>
            <dd>{order.pickup.formatted_address}</dd>
            <dt className="text-gray-500">إلى</dt>
            <dd>{order.dropoff.formatted_address}</dd>
            <dt className="text-gray-500">السعر المقدّر</dt>
            <dd>{order.quoted_price} هللة</dd>
            <dt className="text-gray-500">السعر النهائي</dt>
            <dd>{order.final_price ?? "—"}</dd>
            <dt className="text-gray-500">ملاحظات</dt>
            <dd>{order.notes ?? "—"}</dd>
            {order.cancelled_by && (
              <>
                <dt className="text-gray-500">سبب الإلغاء</dt>
                <dd>{order.cancellation_reason ?? "—"}</dd>
              </>
            )}
          </dl>
        </Card>

        <Card>
          <CardTitle>المزوّد والسائق</CardTitle>
          {order.assigned_provider ? (
            <dl className="grid grid-cols-2 gap-y-2 text-sm">
              <dt className="text-gray-500">المزوّد</dt>
              <dd>{order.assigned_provider.business_name}</dd>
              <dt className="text-gray-500">السائق</dt>
              <dd>{order.assigned_driver?.name ?? "—"}</dd>
              <dt className="text-gray-500">هاتف السائق</dt>
              <dd dir="ltr">{order.assigned_driver?.phone ?? "—"}</dd>
              <dt className="text-gray-500">السطحة</dt>
              <dd>{order.assigned_tow_truck?.plate_number ?? "—"}</dd>
            </dl>
          ) : (
            <p className="text-sm text-gray-500">لم يتم تعيين مزوّد بعد.</p>
          )}
        </Card>
      </div>

      <Card>
        <CardTitle>إجراءات التوزيع</CardTitle>
        <div className="flex flex-wrap gap-2">
          <Button
            variant="secondary"
            disabled={!canDispatch || retryMutation.isPending}
            onClick={() => retryMutation.mutate()}
          >
            {retryMutation.isPending ? <Spinner /> : "إعادة محاولة التوزيع التلقائي"}
          </Button>
          <Button
            variant="secondary"
            disabled={!canDispatch}
            onClick={() => setShowAssignForm((v) => !v)}
          >
            تعيين سطحة يدوياً
          </Button>
          <Button
            variant="danger"
            disabled={!canCancel}
            onClick={() => setShowCancelForm((v) => !v)}
          >
            إلغاء الطلب
          </Button>
        </div>

        {showAssignForm && (
          <form
            onSubmit={(event) => {
              event.preventDefault();
              assignMutation.mutate();
            }}
            className="mt-4 flex flex-col gap-2 border-t border-gray-100 pt-4"
          >
            <Input
              placeholder="معرّف السطحة (Tow Truck ID)"
              value={towTruckId}
              onChange={(event) => setTowTruckId(event.target.value)}
              required
            />
            <Input
              placeholder="سبب التعيين اليدوي (اختياري)"
              value={assignReason}
              onChange={(event) => setAssignReason(event.target.value)}
            />
            <Button type="submit" disabled={assignMutation.isPending}>
              {assignMutation.isPending ? <Spinner /> : "تأكيد التعيين"}
            </Button>
          </form>
        )}

        {showCancelForm && (
          <form
            onSubmit={(event) => {
              event.preventDefault();
              cancelMutation.mutate();
            }}
            className="mt-4 flex flex-col gap-2 border-t border-gray-100 pt-4"
          >
            <Input
              placeholder="سبب الإلغاء"
              value={cancelReason}
              onChange={(event) => setCancelReason(event.target.value)}
              required
            />
            <Button type="submit" variant="danger" disabled={cancelMutation.isPending}>
              {cancelMutation.isPending ? <Spinner /> : "تأكيد الإلغاء"}
            </Button>
          </form>
        )}
      </Card>

      <Card>
        <CardTitle>عروض التوزيع</CardTitle>
        {offersQuery.isLoading && <Spinner />}
        {offersQuery.data && offersQuery.data.data.offers.length === 0 && (
          <p className="text-sm text-gray-500">لا توجد عروض توزيع بعد.</p>
        )}
        {offersQuery.data && offersQuery.data.data.offers.length > 0 && (
          <table className="w-full text-sm">
            <thead className="text-right text-gray-500">
              <tr>
                <th className="py-1 font-medium">الموجة</th>
                <th className="py-1 font-medium">الحالة</th>
                <th className="py-1 font-medium">المسافة</th>
                <th className="py-1 font-medium">تنتهي في</th>
              </tr>
            </thead>
            <tbody>
              {offersQuery.data.data.offers.map((offer) => (
                <tr key={offer.id} className="border-t border-gray-100">
                  <td className="py-1">{offer.wave}</td>
                  <td className="py-1">{OFFER_STATUS_LABELS[offer.status] ?? offer.status}</td>
                  <td className="py-1">{(offer.distance_meters / 1000).toFixed(1)} كم</td>
                  <td className="py-1 text-gray-500">
                    {new Date(offer.expires_at).toLocaleString("ar-SA")}
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
