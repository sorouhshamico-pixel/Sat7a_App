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
import { CANCELLABLE_ORDER_STATUSES, orderStatusLabel, orderStatusTone } from "@/lib/orders";
import { DISPUTE_REASON_LABELS } from "@/lib/disputes";
import { SERVICE_TYPE_LABELS } from "@/lib/service-types";
import type { OrderDetail } from "@/lib/types/order";

const PAYABLE_STATUSES = new Set(["vehicle_delivered", "completed"]);
const TRACKABLE_STATUSES = new Set([
  "provider_assigned",
  "provider_en_route",
  "provider_arrived",
  "vehicle_loading",
  "trip_started",
  "in_transit",
]);
const TERMINAL_STATUSES = new Set([
  "completed",
  "cancelled_by_customer",
  "cancelled_by_provider",
  "cancelled_by_admin",
]);

interface Location {
  latitude: number;
  longitude: number;
  recorded_at: string | null;
  source: string;
}

interface Payment {
  id: string;
  status: string;
  amount: number;
}

export default function OrderDetailPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = use(params);
  const queryClient = useQueryClient();
  const [error, setError] = useState<string | null>(null);
  const [cancelReason, setCancelReason] = useState("");
  const [showCancelForm, setShowCancelForm] = useState(false);
  const [reviewRating, setReviewRating] = useState(5);
  const [reviewComment, setReviewComment] = useState("");
  const [disputeReason, setDisputeReason] = useState("service_quality");
  const [disputeDescription, setDisputeDescription] = useState("");
  const [showDisputeForm, setShowDisputeForm] = useState(false);

  const orderQuery = useQuery({
    queryKey: ["customer-order", id],
    queryFn: () => apiGet<{ order: OrderDetail }>(`customers/me/orders/${id}`),
  });

  const order = orderQuery.data?.data.order;

  const locationQuery = useQuery({
    queryKey: ["customer-order-location", id],
    queryFn: () => apiGet<{ current: Location | null }>(`customers/me/orders/${id}/location`),
    enabled: Boolean(order && TRACKABLE_STATUSES.has(order.status)),
    refetchInterval: 5000,
  });

  const paymentsQuery = useQuery({
    queryKey: ["customer-order-payments", id],
    queryFn: () => apiGet<{ payments: Payment[] }>(`customers/me/orders/${id}/payments`),
    enabled: Boolean(order && PAYABLE_STATUSES.has(order.status)),
  });

  const reviewQuery = useQuery({
    queryKey: ["customer-order-review", id],
    queryFn: () => apiGet<{ review: { id: string } }>(`customers/me/orders/${id}/review`),
    enabled: order?.status === "completed",
    retry: false,
  });

  function handleError(err: unknown) {
    setError(err instanceof ApiRequestError ? err.message : "حدث خطأ غير متوقع.");
  }

  function invalidateOrder() {
    queryClient.invalidateQueries({ queryKey: ["customer-order", id] });
  }

  const cancelMutation = useMutation({
    mutationFn: () => apiPost(`customers/me/orders/${id}/cancel`, { reason: cancelReason }),
    onSuccess: () => {
      setError(null);
      setShowCancelForm(false);
      invalidateOrder();
    },
    onError: handleError,
  });

  const payCashMutation = useMutation({
    mutationFn: () => apiPost(`customers/me/orders/${id}/payments`, { method: "cash" }),
    onSuccess: () => {
      setError(null);
      queryClient.invalidateQueries({ queryKey: ["customer-order-payments", id] });
    },
    onError: handleError,
  });

  const reviewMutation = useMutation({
    mutationFn: () =>
      apiPost(`customers/me/orders/${id}/review`, {
        rating: reviewRating,
        comment: reviewComment || undefined,
      }),
    onSuccess: () => {
      setError(null);
      queryClient.invalidateQueries({ queryKey: ["customer-order-review", id] });
    },
    onError: handleError,
  });

  const disputeMutation = useMutation({
    mutationFn: () =>
      apiPost(`customers/me/orders/${id}/dispute`, {
        reason: disputeReason,
        description: disputeDescription,
      }),
    onSuccess: () => {
      setError(null);
      setShowDisputeForm(false);
      setDisputeDescription("");
    },
    onError: handleError,
  });

  if (orderQuery.isLoading) return <Spinner />;
  if (orderQuery.isError || !order) return <Alert>تعذّر تحميل بيانات الطلب.</Alert>;

  const canCancel = CANCELLABLE_ORDER_STATUSES.has(order.status);
  const isPayable = PAYABLE_STATUSES.has(order.status);
  const hasSuccessfulPayment =
    paymentsQuery.data?.data.payments.some((p) => p.status === "captured") ?? false;
  const canDispute = TERMINAL_STATUSES.has(order.status);

  return (
    <div className="mx-auto flex w-full max-w-2xl flex-col gap-4">
      <div className="flex items-center justify-between">
        <h1 className="text-xl font-bold text-gray-900">طلب #{order.id.slice(0, 8)}</h1>
        <Badge tone={orderStatusTone(order.status)}>{orderStatusLabel(order.status)}</Badge>
      </div>

      {error && <Alert>{error}</Alert>}

      <Card>
        <CardTitle>تفاصيل الطلب</CardTitle>
        <dl className="grid grid-cols-2 gap-y-2 text-sm">
          <dt className="text-gray-500">من</dt>
          <dd>{order.pickup.formatted_address}</dd>
          <dt className="text-gray-500">إلى</dt>
          <dd>{order.dropoff.formatted_address}</dd>
          <dt className="text-gray-500">نوع الخدمة</dt>
          <dd>{SERVICE_TYPE_LABELS[order.service_type] ?? order.service_type}</dd>
          <dt className="text-gray-500">السعر</dt>
          <dd>{((order.final_price ?? order.quoted_price) / 100).toFixed(2)} ر.س</dd>
          {order.notes && (
            <>
              <dt className="text-gray-500">ملاحظات</dt>
              <dd>{order.notes}</dd>
            </>
          )}
        </dl>
      </Card>

      {order.assigned_provider && (
        <Card>
          <CardTitle>مزود الخدمة</CardTitle>
          <dl className="grid grid-cols-2 gap-y-2 text-sm">
            <dt className="text-gray-500">المزوّد</dt>
            <dd>{order.assigned_provider.business_name}</dd>
            <dt className="text-gray-500">السائق</dt>
            <dd>{order.assigned_driver?.name ?? "—"}</dd>
            <dt className="text-gray-500">هاتف السائق</dt>
            <dd dir="ltr">{order.assigned_driver?.phone ?? "—"}</dd>
          </dl>
        </Card>
      )}

      {TRACKABLE_STATUSES.has(order.status) && (
        <Card>
          <CardTitle>موقع السطحة</CardTitle>
          {locationQuery.isLoading && <Spinner />}
          {locationQuery.data?.data.current ? (
            <p className="text-sm text-gray-600">
              {locationQuery.data.data.current.recorded_at
                ? `آخر تحديث: ${new Date(locationQuery.data.data.current.recorded_at).toLocaleTimeString("ar-SA")}`
                : "موقع تقريبي لآخر معرفة به"}
              {" — "}({locationQuery.data.data.current.latitude.toFixed(5)},{" "}
              {locationQuery.data.data.current.longitude.toFixed(5)})
            </p>
          ) : (
            <p className="text-sm text-gray-500">لا يوجد موقع متاح بعد.</p>
          )}
        </Card>
      )}

      {(canCancel || isPayable) && (
        <Card>
          <CardTitle>إجراءات</CardTitle>
          <div className="flex flex-wrap gap-2">
            {canCancel && (
              <Button variant="danger" onClick={() => setShowCancelForm((v) => !v)}>
                إلغاء الطلب
              </Button>
            )}
            {isPayable && !hasSuccessfulPayment && (
              <Button disabled={payCashMutation.isPending} onClick={() => payCashMutation.mutate()}>
                {payCashMutation.isPending ? <Spinner /> : "الدفع نقداً"}
              </Button>
            )}
            {isPayable && hasSuccessfulPayment && <Badge tone="success">تم الدفع</Badge>}
          </div>

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
      )}

      {order.status === "completed" && !reviewQuery.data && (
        <Card>
          <CardTitle>قيّم الخدمة</CardTitle>
          <form
            onSubmit={(event) => {
              event.preventDefault();
              reviewMutation.mutate();
            }}
            className="flex flex-col gap-2"
          >
            <select
              value={reviewRating}
              onChange={(event) => setReviewRating(Number(event.target.value))}
              className="w-32 rounded-md border border-gray-300 px-3 py-2 text-sm"
            >
              {[5, 4, 3, 2, 1].map((n) => (
                <option key={n} value={n}>
                  {n} / 5
                </option>
              ))}
            </select>
            <Input
              placeholder="تعليق (اختياري)"
              value={reviewComment}
              onChange={(event) => setReviewComment(event.target.value)}
            />
            <Button type="submit" disabled={reviewMutation.isPending}>
              {reviewMutation.isPending ? <Spinner /> : "إرسال التقييم"}
            </Button>
          </form>
        </Card>
      )}

      {canDispute && (
        <Card>
          <CardTitle>هل لديك مشكلة في هذا الطلب؟</CardTitle>
          {!showDisputeForm && !disputeMutation.isSuccess && (
            <Button variant="secondary" onClick={() => setShowDisputeForm(true)}>
              فتح نزاع
            </Button>
          )}
          {disputeMutation.isSuccess && (
            <Alert tone="info">تم فتح النزاع، سيتواصل معك فريق الدعم قريباً.</Alert>
          )}
          {showDisputeForm && (
            <form
              onSubmit={(event) => {
                event.preventDefault();
                disputeMutation.mutate();
              }}
              className="flex flex-col gap-2"
            >
              <select
                value={disputeReason}
                onChange={(event) => setDisputeReason(event.target.value)}
                className="rounded-md border border-gray-300 px-3 py-2 text-sm"
              >
                {Object.entries(DISPUTE_REASON_LABELS).map(([value, label]) => (
                  <option key={value} value={value}>
                    {label}
                  </option>
                ))}
              </select>
              <Input
                placeholder="وصف المشكلة"
                value={disputeDescription}
                onChange={(event) => setDisputeDescription(event.target.value)}
                required
              />
              <Button type="submit" disabled={disputeMutation.isPending}>
                {disputeMutation.isPending ? <Spinner /> : "إرسال"}
              </Button>
            </form>
          )}
        </Card>
      )}
    </div>
  );
}
