"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import { apiPost } from "@/lib/api/client";
import { ApiRequestError } from "@/lib/api/types";
import { AddressSearch, type SelectedAddress } from "@/components/address-search";
import { InstallPrompt } from "@/components/install-prompt";
import { Button } from "@/components/ui/button";
import { Card, CardTitle } from "@/components/ui/card";
import { Alert } from "@/components/ui/alert";
import { Spinner } from "@/components/ui/spinner";
import { SERVICE_TYPE_LABELS, VEHICLE_CATEGORY_LABELS } from "@/lib/service-types";
import type { PricingSnapshot } from "@/lib/types/pricing";

export default function HomePage() {
  const router = useRouter();
  const [pickup, setPickup] = useState<SelectedAddress | null>(null);
  const [dropoff, setDropoff] = useState<SelectedAddress | null>(null);
  const [serviceType, setServiceType] = useState("standard_flatbed");
  const [vehicleCategory, setVehicleCategory] = useState("sedan");
  const [quote, setQuote] = useState<PricingSnapshot | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function handleGetQuote() {
    if (!pickup || !dropoff) {
      setError("الرجاء اختيار موقع الانطلاق والوجهة.");
      return;
    }

    setLoading(true);
    setError(null);
    setQuote(null);

    try {
      const routeResult = await apiPost<{ distance_meters: number }>("maps/route", {
        origin: { latitude: pickup.latitude, longitude: pickup.longitude },
        destination: { latitude: dropoff.latitude, longitude: dropoff.longitude },
      });

      const quoteResult = await apiPost<PricingSnapshot>("pricing/quote", {
        distance_meters: routeResult.data.distance_meters,
        service_type: serviceType,
        vehicle_category: vehicleCategory,
      });

      setQuote(quoteResult.data);
    } catch (err) {
      setError(err instanceof ApiRequestError ? err.message : "تعذّر حساب السعر.");
    } finally {
      setLoading(false);
    }
  }

  function handleBookNow() {
    if (!pickup || !dropoff) return;

    const params = new URLSearchParams({
      pickup_lat: String(pickup.latitude),
      pickup_lng: String(pickup.longitude),
      pickup_address: pickup.formatted_address,
      dropoff_lat: String(dropoff.latitude),
      dropoff_lng: String(dropoff.longitude),
      dropoff_address: dropoff.formatted_address,
      service_type: serviceType,
      vehicle_category: vehicleCategory,
    });

    router.push(`/orders/new?${params.toString()}`);
  }

  return (
    <div className="mx-auto flex w-full max-w-lg flex-1 flex-col justify-center gap-4 py-8">
      <InstallPrompt appLabel="سطحات الرياض" />

      <div className="text-center">
        <h1 className="text-2xl font-bold text-gray-900">اطلب سطحة في الرياض خلال دقائق</h1>
        <p className="mt-1 text-sm text-gray-600">احصل على سعر فوري، ثم احجز طلبك.</p>
      </div>

      <Card>
        <div className="flex flex-col gap-3">
          <AddressSearch
            label="موقع الانطلاق"
            placeholder="من أين تريد النقل؟"
            onSelect={setPickup}
          />
          <AddressSearch label="الوجهة" placeholder="إلى أين؟" onSelect={setDropoff} />

          <div className="grid grid-cols-2 gap-3">
            <label className="flex flex-col gap-1 text-sm text-gray-600">
              نوع الخدمة
              <select
                value={serviceType}
                onChange={(event) => setServiceType(event.target.value)}
                className="rounded-md border border-gray-300 px-3 py-2 text-sm"
              >
                {Object.entries(SERVICE_TYPE_LABELS).map(([value, label]) => (
                  <option key={value} value={value}>
                    {label}
                  </option>
                ))}
              </select>
            </label>

            <label className="flex flex-col gap-1 text-sm text-gray-600">
              فئة المركبة
              <select
                value={vehicleCategory}
                onChange={(event) => setVehicleCategory(event.target.value)}
                className="rounded-md border border-gray-300 px-3 py-2 text-sm"
              >
                {Object.entries(VEHICLE_CATEGORY_LABELS).map(([value, label]) => (
                  <option key={value} value={value}>
                    {label}
                  </option>
                ))}
              </select>
            </label>
          </div>

          {error && <Alert>{error}</Alert>}

          <Button onClick={handleGetQuote} disabled={loading || !pickup || !dropoff}>
            {loading ? <Spinner /> : "احصل على السعر"}
          </Button>
        </div>
      </Card>

      {quote && (
        <Card>
          <CardTitle>السعر المقدّر</CardTitle>
          <p className="text-3xl font-bold text-gray-900">{(quote.total / 100).toFixed(2)} ر.س</p>
          <p className="mt-1 text-xs text-gray-500">
            شامل ضريبة القيمة المضافة ({(quote.vat_percentage * 100).toFixed(0)}%)
          </p>
          <Button className="mt-4 w-full" onClick={handleBookNow}>
            احجز الآن
          </Button>
        </Card>
      )}
    </div>
  );
}
