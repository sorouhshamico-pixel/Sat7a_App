"use client";

import { FormEvent, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { apiGet, apiPost } from "@/lib/api/client";
import { ApiRequestError } from "@/lib/api/types";
import { Button } from "@/components/ui/button";
import { Card, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Alert } from "@/components/ui/alert";
import { Spinner } from "@/components/ui/spinner";
import type { VehicleItem } from "@/lib/types/vehicle";

export default function VehiclesPage() {
  const queryClient = useQueryClient();
  const [error, setError] = useState<string | null>(null);
  const [showAddForm, setShowAddForm] = useState(false);

  const vehiclesQuery = useQuery({
    queryKey: ["customer-vehicles"],
    queryFn: () => apiGet<{ vehicles: VehicleItem[] }>("customers/me/vehicles"),
  });

  const addVehicleMutation = useMutation({
    mutationFn: (data: { make: string; model: string; year: number; plate_number?: string }) =>
      apiPost("customers/me/vehicles", data),
    onSuccess: () => {
      setError(null);
      setShowAddForm(false);
      queryClient.invalidateQueries({ queryKey: ["customer-vehicles"] });
    },
    onError: (err) => setError(err instanceof ApiRequestError ? err.message : "حدث خطأ غير متوقع."),
  });

  function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    addVehicleMutation.mutate({
      make: form.get("make") as string,
      model: form.get("model") as string,
      year: Number(form.get("year")),
      plate_number: (form.get("plate_number") as string) || undefined,
    });
  }

  return (
    <div className="mx-auto flex w-full max-w-2xl flex-col gap-4">
      <div className="flex items-center justify-between">
        <h1 className="text-xl font-bold text-gray-900">مركباتي</h1>
        <Button onClick={() => setShowAddForm((v) => !v)}>إضافة مركبة</Button>
      </div>

      {error && <Alert>{error}</Alert>}

      {showAddForm && (
        <Card>
          <CardTitle>مركبة جديدة</CardTitle>
          <form onSubmit={handleSubmit} className="flex flex-col gap-3">
            <div className="grid grid-cols-2 gap-3">
              <Input name="make" placeholder="الصانع (مثال: تويوتا)" required />
              <Input name="model" placeholder="الموديل (مثال: كامري)" required />
            </div>
            <div className="grid grid-cols-2 gap-3">
              <Input
                name="year"
                type="number"
                placeholder="سنة الصنع"
                min={1980}
                max={new Date().getFullYear() + 1}
                required
              />
              <Input name="plate_number" placeholder="رقم اللوحة (اختياري)" />
            </div>
            <Button type="submit" disabled={addVehicleMutation.isPending}>
              {addVehicleMutation.isPending ? <Spinner /> : "حفظ"}
            </Button>
          </form>
        </Card>
      )}

      {vehiclesQuery.isLoading && <Spinner />}
      {vehiclesQuery.isError && <Alert>تعذّر تحميل المركبات.</Alert>}

      {vehiclesQuery.data && vehiclesQuery.data.data.vehicles.length === 0 && !showAddForm && (
        <p className="text-sm text-gray-500">
          لا توجد مركبات بعد. أضف مركبتك الأولى لتتمكن من طلب سطحة.
        </p>
      )}

      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
        {vehiclesQuery.data?.data.vehicles.map((vehicle) => (
          <Card key={vehicle.id}>
            <p className="font-medium text-gray-900">
              {vehicle.make} {vehicle.model} ({vehicle.year})
            </p>
            {vehicle.plate_number && (
              <p className="text-sm text-gray-500" dir="ltr">
                {vehicle.plate_number}
              </p>
            )}
          </Card>
        ))}
      </div>
    </div>
  );
}
