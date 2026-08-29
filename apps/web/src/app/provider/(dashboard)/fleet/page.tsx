"use client";

import { FormEvent, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { apiGet, apiPatch, apiPost } from "@/lib/api/client";
import { ApiRequestError } from "@/lib/api/types";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Alert } from "@/components/ui/alert";
import { Spinner } from "@/components/ui/spinner";
import { SERVICE_TYPE_LABELS } from "@/lib/service-types";
import { TOW_TRUCK_NEXT_STATUSES, TOW_TRUCK_STATUS_LABELS, towTruckStatusTone } from "@/lib/fleet";
import type { TowTruckItem } from "@/lib/types/fleet";
import type { DriverItem } from "@/lib/types/driver";

const CURRENT_YEAR = new Date().getFullYear();

function TruckRow({ truck }: { truck: TowTruckItem }) {
  const queryClient = useQueryClient();
  const [error, setError] = useState<string | null>(null);

  const driversQuery = useQuery({
    queryKey: ["provider-drivers"],
    queryFn: () => apiGet<{ drivers: DriverItem[] }>("providers/me/drivers"),
  });

  function invalidate() {
    queryClient.invalidateQueries({ queryKey: ["provider-fleet"] });
  }

  const assignMutation = useMutation({
    mutationFn: (driverId: string | null) =>
      apiPatch(`providers/me/fleet/${truck.id}/driver`, { driver_id: driverId }),
    onSuccess: () => {
      setError(null);
      invalidate();
    },
    onError: (err) =>
      setError(err instanceof ApiRequestError ? err.message : "تعذّر تعيين السائق."),
  });

  const statusMutation = useMutation({
    mutationFn: (status: string) => apiPatch(`providers/me/fleet/${truck.id}/status`, { status }),
    onSuccess: () => {
      setError(null);
      invalidate();
    },
    onError: (err) =>
      setError(err instanceof ApiRequestError ? err.message : "تعذّر تحديث الحالة."),
  });

  const nextStatuses = TOW_TRUCK_NEXT_STATUSES[truck.status] ?? [];
  const drivers = driversQuery.data?.data.drivers ?? [];

  return (
    <div className="border-t border-gray-100 py-3 first:border-t-0 first:pt-0">
      <div className="flex items-center justify-between">
        <div>
          <p className="text-sm font-medium text-gray-900">
            {truck.manufacturer} {truck.model} ({truck.year})
          </p>
          <p className="text-xs text-gray-500" dir="ltr">
            {truck.plate_number}
          </p>
        </div>
        <Badge tone={towTruckStatusTone(truck.status)}>
          {TOW_TRUCK_STATUS_LABELS[truck.status] ?? truck.status}
        </Badge>
      </div>

      <p className="mt-1 text-xs text-gray-500">
        {truck.service_capabilities.map((c) => SERVICE_TYPE_LABELS[c] ?? c).join("، ")}
      </p>

      {error && (
        <div className="mt-2">
          <Alert>{error}</Alert>
        </div>
      )}

      <div className="mt-2 flex flex-wrap items-center gap-2">
        <select
          value={truck.driver?.id ?? ""}
          onChange={(event) => assignMutation.mutate(event.target.value || null)}
          disabled={assignMutation.isPending}
          className="rounded-md border border-gray-300 px-2 py-1 text-xs"
        >
          <option value="">بدون سائق</option>
          {drivers.map((driver) => (
            <option key={driver.id} value={driver.id}>
              {driver.name}
            </option>
          ))}
        </select>

        {nextStatuses.map((status) => (
          <Button
            key={status}
            variant="secondary"
            disabled={statusMutation.isPending}
            onClick={() => statusMutation.mutate(status)}
          >
            {TOW_TRUCK_STATUS_LABELS[status] ?? status}
          </Button>
        ))}
      </div>
    </div>
  );
}

function AddTruckForm({ onDone }: { onDone: () => void }) {
  const queryClient = useQueryClient();
  const [manufacturer, setManufacturer] = useState("");
  const [model, setModel] = useState("");
  const [year, setYear] = useState(String(CURRENT_YEAR));
  const [plateNumber, setPlateNumber] = useState("");
  const [capacity, setCapacity] = useState("");
  const [capabilities, setCapabilities] = useState<string[]>([]);
  const [error, setError] = useState<string | null>(null);

  const addMutation = useMutation({
    mutationFn: () =>
      apiPost("providers/me/fleet", {
        manufacturer,
        model,
        year: Number(year),
        plate_number: plateNumber,
        capacity: capacity || null,
        service_capabilities: capabilities,
      }),
    onSuccess: () => {
      setError(null);
      queryClient.invalidateQueries({ queryKey: ["provider-fleet"] });
      onDone();
    },
    onError: (err) =>
      setError(err instanceof ApiRequestError ? err.message : "تعذّر إضافة المركبة."),
  });

  function toggleCapability(value: string) {
    setCapabilities((prev) =>
      prev.includes(value) ? prev.filter((c) => c !== value) : [...prev, value],
    );
  }

  return (
    <form
      onSubmit={(event: FormEvent) => {
        event.preventDefault();
        if (capabilities.length === 0) {
          setError("الرجاء اختيار نوع خدمة واحد على الأقل.");
          return;
        }
        addMutation.mutate();
      }}
      className="mt-4 flex flex-col gap-3 border-t border-gray-100 pt-4"
    >
      {error && <Alert>{error}</Alert>}
      <div className="grid grid-cols-2 gap-3">
        <Input
          placeholder="الشركة المصنعة"
          value={manufacturer}
          onChange={(event) => setManufacturer(event.target.value)}
          required
        />
        <Input
          placeholder="الطراز"
          value={model}
          onChange={(event) => setModel(event.target.value)}
          required
        />
        <Input
          type="number"
          placeholder="سنة الصنع"
          value={year}
          onChange={(event) => setYear(event.target.value)}
          min={1980}
          max={CURRENT_YEAR + 1}
          required
        />
        <Input
          placeholder="رقم اللوحة"
          value={plateNumber}
          onChange={(event) => setPlateNumber(event.target.value)}
          dir="ltr"
          required
        />
        <Input
          placeholder="السعة (اختياري)"
          value={capacity}
          onChange={(event) => setCapacity(event.target.value)}
        />
      </div>

      <div>
        <p className="mb-1 text-sm text-gray-600">أنواع الخدمة</p>
        <div className="flex flex-wrap gap-2">
          {Object.entries(SERVICE_TYPE_LABELS).map(([value, label]) => (
            <label key={value} className="flex items-center gap-1 text-xs text-gray-700">
              <input
                type="checkbox"
                checked={capabilities.includes(value)}
                onChange={() => toggleCapability(value)}
              />
              {label}
            </label>
          ))}
        </div>
      </div>

      <Button type="submit" disabled={addMutation.isPending}>
        {addMutation.isPending ? <Spinner /> : "إضافة المركبة"}
      </Button>
    </form>
  );
}

export default function FleetPage() {
  const [showAddForm, setShowAddForm] = useState(false);

  const fleetQuery = useQuery({
    queryKey: ["provider-fleet"],
    queryFn: () => apiGet<{ tow_trucks: TowTruckItem[] }>("providers/me/fleet"),
  });

  if (fleetQuery.isLoading) return <Spinner />;
  if (fleetQuery.isError) return <Alert>تعذّر تحميل الأسطول.</Alert>;

  const trucks = fleetQuery.data?.data.tow_trucks ?? [];

  return (
    <div className="flex flex-col gap-4">
      <div className="flex items-center justify-between">
        <h1 className="text-xl font-bold text-gray-900">الأسطول</h1>
        {!showAddForm && <Button onClick={() => setShowAddForm(true)}>إضافة مركبة</Button>}
      </div>

      <Card>
        {trucks.length === 0 && <p className="text-sm text-gray-500">لا توجد مركبات مضافة بعد.</p>}
        {trucks.map((truck) => (
          <TruckRow key={truck.id} truck={truck} />
        ))}

        {showAddForm && <AddTruckForm onDone={() => setShowAddForm(false)} />}
      </Card>
    </div>
  );
}
