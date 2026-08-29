"use client";

import { Suspense, useEffect, useState } from "react";
import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { useMutation, useQuery } from "@tanstack/react-query";
import { apiGet, apiPost } from "@/lib/api/client";
import { ApiRequestError } from "@/lib/api/types";
import { Button } from "@/components/ui/button";
import { Card, CardTitle } from "@/components/ui/card";
import { Alert } from "@/components/ui/alert";
import { Spinner } from "@/components/ui/spinner";
import { SERVICE_TYPE_LABELS, VEHICLE_CATEGORY_LABELS } from "@/lib/service-types";
import type { PricingSnapshot } from "@/lib/types/pricing";
import type { VehicleItem } from "@/lib/types/vehicle";

function NewOrderForm() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const [quote, setQuote] = useState<PricingSnapshot | null>(null);
  const [quoteError, setQuoteError] = useState<string | null>(null);
  const [selectedVehicleId, setSelectedVehicleId] = useState("");
  const [notes, setNotes] = useState("");

  const pickupLat = Number(searchParams.get("pickup_lat"));
  const pickupLng = Number(searchParams.get("pickup_lng"));
  const pickupAddress = searchParams.get("pickup_address") ?? "";
  const dropoffLat = Number(searchParams.get("dropoff_lat"));
  const dropoffLng = Number(searchParams.get("dropoff_lng"));
  const dropoffAddress = searchParams.get("dropoff_address") ?? "";
  const serviceType = searchParams.get("service_type") ?? "standard_flatbed";
  const vehicleCategory = searchParams.get("vehicle_category") ?? "sedan";
  const hasRoute = Boolean(pickupAddress && dropoffAddress);

  const vehiclesQuery = useQuery({
    queryKey: ["customer-vehicles"],
    queryFn: () => apiGet<{ vehicles: VehicleItem[] }>("customers/me/vehicles"),
  });

  useEffect(() => {
    if (!hasRoute) return;

    (async () => {
      try {
        const routeResult = await apiPost<{ distance_meters: number }>("maps/route", {
          origin: { latitude: pickupLat, longitude: pickupLng },
          destination: { latitude: dropoffLat, longitude: dropoffLng },
        });
        const quoteResult = await apiPost<PricingSnapshot>("pricing/quote", {
          distance_meters: routeResult.data.distance_meters,
          service_type: serviceType,
          vehicle_category: vehicleCategory,
        });
        setQuote(quoteResult.data);
      } catch (err) {
        setQuoteError(err instanceof ApiRequestError ? err.message : "تعذّر حساب السعر.");
      }
    })();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [hasRoute]);

  const createOrderMutation = useMutation({
    mutationFn: () =>
      apiPost<{ order: { id: string } }>("customers/me/orders", {
        vehicle_id: selectedVehicleId,
        service_type: serviceType,
        vehicle_category: vehicleCategory,
        pickup_latitude: pickupLat,
        pickup_longitude: pickupLng,
        pickup_formatted_address: pickupAddress,
        dropoff_latitude: dropoffLat,
        dropoff_longitude: dropoffLng,
        dropoff_formatted_address: dropoffAddress,
        notes: notes || undefined,
      }),
    onSuccess: (result) => {
      router.push(`/orders/${result.data.order.id}`);
    },
  });

  if (!hasRoute) {
    return <Alert>لم يتم تحديد موقع الانطلاق والوجهة. الرجاء البدء من الصفحة الرئيسية.</Alert>;
  }

  const vehicles = vehiclesQuery.data?.data.vehicles ?? [];

  return (
    <div className="mx-auto flex w-full max-w-lg flex-col gap-4">
      <h1 className="text-xl font-bold text-gray-900">تأكيد الطلب</h1>

      <Card>
        <dl className="grid grid-cols-2 gap-y-2 text-sm">
          <dt className="text-gray-500">من</dt>
          <dd>{pickupAddress}</dd>
          <dt className="text-gray-500">إلى</dt>
          <dd>{dropoffAddress}</dd>
          <dt className="text-gray-500">نوع الخدمة</dt>
          <dd>{SERVICE_TYPE_LABELS[serviceType] ?? serviceType}</dd>
          <dt className="text-gray-500">فئة المركبة</dt>
          <dd>{VEHICLE_CATEGORY_LABELS[vehicleCategory] ?? vehicleCategory}</dd>
        </dl>
      </Card>

      {quoteError && <Alert>{quoteError}</Alert>}
      {quote && (
        <Card>
          <CardTitle>السعر المقدّر</CardTitle>
          <p className="text-2xl font-bold text-gray-900">{(quote.total / 100).toFixed(2)} ر.س</p>
        </Card>
      )}

      <Card>
        <CardTitle>المركبة</CardTitle>
        {vehiclesQuery.isLoading && <Spinner />}
        {vehicles.length === 0 && !vehiclesQuery.isLoading && (
          <p className="text-sm text-gray-500">
            لا توجد مركبات مسجّلة.{" "}
            <Link href="/vehicles" className="text-blue-600 underline">
              أضف مركبة أولاً
            </Link>
            .
          </p>
        )}
        {vehicles.length > 0 && (
          <select
            value={selectedVehicleId}
            onChange={(event) => setSelectedVehicleId(event.target.value)}
            className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
            required
          >
            <option value="">اختر مركبة</option>
            {vehicles.map((vehicle) => (
              <option key={vehicle.id} value={vehicle.id}>
                {vehicle.make} {vehicle.model} ({vehicle.year})
              </option>
            ))}
          </select>
        )}
        <textarea
          value={notes}
          onChange={(event) => setNotes(event.target.value)}
          placeholder="ملاحظات إضافية (اختياري)"
          className="mt-3 w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
          rows={2}
        />
      </Card>

      {createOrderMutation.isError && (
        <Alert>
          {createOrderMutation.error instanceof ApiRequestError
            ? createOrderMutation.error.message
            : "تعذّر إنشاء الطلب."}
        </Alert>
      )}

      <Button
        disabled={!selectedVehicleId || createOrderMutation.isPending}
        onClick={() => createOrderMutation.mutate()}
      >
        {createOrderMutation.isPending ? <Spinner /> : "تأكيد الطلب"}
      </Button>
    </div>
  );
}

export default function NewOrderPage() {
  return (
    <Suspense>
      <NewOrderForm />
    </Suspense>
  );
}
