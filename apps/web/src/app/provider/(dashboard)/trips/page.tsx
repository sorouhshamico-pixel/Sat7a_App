"use client";

import { useEffect, useRef, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { apiGet, apiPost } from "@/lib/api/client";
import { ApiRequestError } from "@/lib/api/types";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardTitle } from "@/components/ui/card";
import { Alert } from "@/components/ui/alert";
import { Spinner } from "@/components/ui/spinner";
import { orderStatusLabel, orderStatusTone } from "@/lib/orders";
import { SERVICE_TYPE_LABELS } from "@/lib/service-types";
import { TRIP_NEXT_STATUS, TRIP_NEXT_STATUS_LABEL } from "@/lib/trips";
import type { DriverDispatchOffer } from "@/lib/types/dispatch-offer";
import type { OrderDetail } from "@/lib/types/order";

// There is no GET endpoint for a driver to fetch their own active order
// (only accept-offer and advance-status, each of which returns the full
// order) — a real backend gap, not a UI shortcut (see
// docs/PROVIDER_WEB_APP.md §Driver "my active order" gap). This app
// caches the last-known active order client-side so a page refresh
// doesn't lose it; it will NOT reappear on a different browser/device.
const ACTIVE_ORDER_STORAGE_KEY = "riyadh_tow_active_order";

function loadCachedOrder(): OrderDetail | null {
  if (typeof window === "undefined") return null;

  try {
    const raw = window.localStorage.getItem(ACTIVE_ORDER_STORAGE_KEY);
    return raw ? (JSON.parse(raw) as OrderDetail) : null;
  } catch {
    return null;
  }
}

function cacheOrder(order: OrderDetail | null) {
  try {
    if (order) {
      window.localStorage.setItem(ACTIVE_ORDER_STORAGE_KEY, JSON.stringify(order));
    } else {
      window.localStorage.removeItem(ACTIVE_ORDER_STORAGE_KEY);
    }
  } catch {
    // localStorage unavailable (private mode, etc.) — cache is best-effort.
  }
}

function LocationSharingToggle() {
  const [sharing, setSharing] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [lastSentAt, setLastSentAt] = useState<string | null>(null);
  const intervalRef = useRef<ReturnType<typeof setInterval> | null>(null);

  function sendPing() {
    if (!navigator.geolocation) {
      setError("المتصفح لا يدعم تحديد الموقع.");
      setSharing(false);
      return;
    }

    navigator.geolocation.getCurrentPosition(
      (position) => {
        apiPost("drivers/me/location", {
          latitude: position.coords.latitude,
          longitude: position.coords.longitude,
          heading: position.coords.heading ? Math.round(position.coords.heading) : undefined,
          speed_kmh: position.coords.speed ? Math.round(position.coords.speed * 3.6) : undefined,
        })
          .then(() => setLastSentAt(new Date().toLocaleTimeString("ar-SA")))
          .catch((err) =>
            setError(err instanceof ApiRequestError ? err.message : "تعذّر إرسال الموقع."),
          );
      },
      () => setError("تعذّر الوصول إلى الموقع — تأكد من صلاحيات الموقع في المتصفح."),
      { enableHighAccuracy: false, maximumAge: 8000, timeout: 10000 },
    );
  }

  useEffect(() => {
    if (!sharing) {
      if (intervalRef.current) clearInterval(intervalRef.current);
      return;
    }

    queueMicrotask(() => setError(null));
    queueMicrotask(sendPing);
    // Well under the backend's 60/min rate limit for this endpoint (see
    // App\Providers\AppServiceProvider's 'location-ping' limiter).
    intervalRef.current = setInterval(sendPing, 10000);

    return () => {
      if (intervalRef.current) clearInterval(intervalRef.current);
    };
  }, [sharing]);

  return (
    <Card>
      <CardTitle>مشاركة الموقع</CardTitle>
      {error && (
        <div className="mb-2">
          <Alert>{error}</Alert>
        </div>
      )}
      <div className="flex items-center justify-between">
        <p className="text-sm text-gray-600">
          {sharing
            ? lastSentAt
              ? `آخر إرسال: ${lastSentAt}`
              : "جارٍ الإرسال..."
            : "الموقع غير مُشارك حالياً."}
        </p>
        <Button variant={sharing ? "danger" : "primary"} onClick={() => setSharing((v) => !v)}>
          {sharing ? "إيقاف المشاركة" : "بدء المشاركة"}
        </Button>
      </div>
    </Card>
  );
}

function ActiveOrderCard({
  order,
  onUpdated,
  onCleared,
}: {
  order: OrderDetail;
  onUpdated: (order: OrderDetail) => void;
  onCleared: () => void;
}) {
  const [error, setError] = useState<string | null>(null);

  const advanceMutation = useMutation({
    mutationFn: (status: string) =>
      apiPost<{ order: OrderDetail }>(`drivers/me/orders/${order.id}/status`, { status }),
    onSuccess: (result) => {
      setError(null);
      onUpdated(result.data.order);
    },
    onError: (err) =>
      setError(err instanceof ApiRequestError ? err.message : "تعذّر تحديث حالة الرحلة."),
  });

  const nextStatus = TRIP_NEXT_STATUS[order.status];

  return (
    <Card>
      <div className="mb-3 flex items-center justify-between">
        <h2 className="text-base font-semibold text-gray-900">
          الرحلة الحالية #{order.id.slice(0, 8)}
        </h2>
        <Badge tone={orderStatusTone(order.status)}>{orderStatusLabel(order.status)}</Badge>
      </div>

      {error && (
        <div className="mb-3">
          <Alert>{error}</Alert>
        </div>
      )}

      <dl className="grid grid-cols-2 gap-y-2 text-sm">
        <dt className="text-gray-500">من</dt>
        <dd>{order.pickup.formatted_address}</dd>
        <dt className="text-gray-500">إلى</dt>
        <dd>{order.dropoff.formatted_address}</dd>
        <dt className="text-gray-500">نوع الخدمة</dt>
        <dd>{SERVICE_TYPE_LABELS[order.service_type] ?? order.service_type}</dd>
      </dl>

      <div className="mt-4 flex gap-2">
        {nextStatus && (
          <Button
            disabled={advanceMutation.isPending}
            onClick={() => advanceMutation.mutate(nextStatus)}
          >
            {advanceMutation.isPending ? <Spinner /> : TRIP_NEXT_STATUS_LABEL[nextStatus]}
          </Button>
        )}
        {order.status === "completed" && (
          <Button variant="secondary" onClick={onCleared}>
            إخفاء
          </Button>
        )}
      </div>
    </Card>
  );
}

export default function TripsPage() {
  const [activeOrder, setActiveOrder] = useState<OrderDetail | null>(null);
  const [error, setError] = useState<string | null>(null);
  const queryClient = useQueryClient();

  useEffect(() => {
    queueMicrotask(() => setActiveOrder(loadCachedOrder()));
  }, []);

  function updateActiveOrder(order: OrderDetail | null) {
    setActiveOrder(order);
    cacheOrder(order);
  }

  const offersQuery = useQuery({
    queryKey: ["driver-dispatch-offers"],
    queryFn: () => apiGet<{ offers: DriverDispatchOffer[] }>("drivers/me/dispatch-offers"),
    refetchInterval: 10000,
    enabled: !activeOrder || activeOrder.status === "completed",
  });

  const acceptMutation = useMutation({
    mutationFn: (offerId: string) =>
      apiPost<{ order: OrderDetail }>(`drivers/me/dispatch-offers/${offerId}/accept`),
    onSuccess: (result) => {
      setError(null);
      updateActiveOrder(result.data.order);
      queryClient.invalidateQueries({ queryKey: ["driver-dispatch-offers"] });
    },
    onError: (err) => setError(err instanceof ApiRequestError ? err.message : "تعذّر قبول الطلب."),
  });

  const rejectMutation = useMutation({
    mutationFn: (offerId: string) => apiPost(`drivers/me/dispatch-offers/${offerId}/reject`),
    onSuccess: () => {
      setError(null);
      queryClient.invalidateQueries({ queryKey: ["driver-dispatch-offers"] });
    },
    onError: (err) => setError(err instanceof ApiRequestError ? err.message : "تعذّر رفض الطلب."),
  });

  const offers = offersQuery.data?.data.offers ?? [];
  const showOffers = !activeOrder || activeOrder.status === "completed";

  return (
    <div className="flex flex-col gap-4">
      <h1 className="text-xl font-bold text-gray-900">رحلاتي</h1>

      {error && <Alert>{error}</Alert>}

      <LocationSharingToggle />

      {activeOrder && (
        <ActiveOrderCard
          order={activeOrder}
          onUpdated={updateActiveOrder}
          onCleared={() => updateActiveOrder(null)}
        />
      )}

      {showOffers && (
        <Card>
          <CardTitle>عروض التوصيل المتاحة</CardTitle>
          {offersQuery.isLoading && <Spinner />}
          {offers.length === 0 && !offersQuery.isLoading && (
            <p className="text-sm text-gray-500">لا توجد عروض توصيل حالياً.</p>
          )}
          <div className="flex flex-col gap-3">
            {offers.map((offer) => (
              <div
                key={offer.id}
                className="border-t border-gray-100 pt-3 first:border-t-0 first:pt-0"
              >
                <p className="text-sm font-medium text-gray-900">
                  {SERVICE_TYPE_LABELS[offer.order.service_type] ?? offer.order.service_type}
                </p>
                <p className="text-xs text-gray-500">من: {offer.order.pickup_formatted_address}</p>
                <p className="text-xs text-gray-500">
                  إلى: {offer.order.dropoff_formatted_address}
                </p>
                <p className="text-xs text-gray-500">
                  المسافة: {(offer.distance_meters / 1000).toFixed(1)} كم
                </p>
                <div className="mt-2 flex gap-2">
                  <Button
                    disabled={acceptMutation.isPending || rejectMutation.isPending}
                    onClick={() => acceptMutation.mutate(offer.id)}
                  >
                    قبول
                  </Button>
                  <Button
                    variant="secondary"
                    disabled={acceptMutation.isPending || rejectMutation.isPending}
                    onClick={() => rejectMutation.mutate(offer.id)}
                  >
                    رفض
                  </Button>
                </div>
              </div>
            ))}
          </div>
        </Card>
      )}
    </div>
  );
}
