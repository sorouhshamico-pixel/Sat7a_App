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
import type { DriverItem } from "@/lib/types/driver";

function DriverRow({ driver }: { driver: DriverItem }) {
  const queryClient = useQueryClient();
  const [error, setError] = useState<string | null>(null);

  const toggleMutation = useMutation({
    mutationFn: (isAvailable: boolean) =>
      apiPatch(`providers/me/drivers/${driver.id}/availability`, { is_available: isAvailable }),
    onSuccess: () => {
      setError(null);
      queryClient.invalidateQueries({ queryKey: ["provider-drivers"] });
    },
    onError: (err) =>
      setError(err instanceof ApiRequestError ? err.message : "تعذّر تحديث الحالة."),
  });

  return (
    <div className="border-t border-gray-100 py-3 first:border-t-0 first:pt-0">
      <div className="flex items-center justify-between">
        <div>
          <p className="text-sm font-medium text-gray-900">{driver.name}</p>
          <p className="text-xs text-gray-500" dir="ltr">
            {driver.phone}
          </p>
        </div>
        <Badge tone={driver.is_available ? "success" : "neutral"}>
          {driver.is_available ? "متاح" : "غير متاح"}
        </Badge>
      </div>

      {error && (
        <div className="mt-2">
          <Alert>{error}</Alert>
        </div>
      )}

      <div className="mt-2">
        <Button
          variant="secondary"
          disabled={toggleMutation.isPending}
          onClick={() => toggleMutation.mutate(!driver.is_available)}
        >
          {toggleMutation.isPending ? (
            <Spinner />
          ) : driver.is_available ? (
            "تعيين كغير متاح"
          ) : (
            "تعيين كمتاح"
          )}
        </Button>
      </div>
    </div>
  );
}

function AddDriverForm({ onDone }: { onDone: () => void }) {
  const queryClient = useQueryClient();
  const [name, setName] = useState("");
  const [phone, setPhone] = useState("");
  const [nationality, setNationality] = useState("");
  const [licenseNumber, setLicenseNumber] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [message, setMessage] = useState<string | null>(null);

  const addMutation = useMutation({
    mutationFn: () =>
      apiPost<{ message: string }>("providers/me/drivers", {
        name,
        phone,
        nationality: nationality || null,
        license_number: licenseNumber || null,
      }),
    onSuccess: (result) => {
      setError(null);
      setMessage(result.data.message);
      queryClient.invalidateQueries({ queryKey: ["provider-drivers"] });
    },
    onError: (err) =>
      setError(err instanceof ApiRequestError ? err.message : "تعذّر إضافة السائق."),
  });

  if (message) {
    return (
      <div className="mt-4 flex flex-col gap-3 border-t border-gray-100 pt-4">
        <Alert tone="info">{message}</Alert>
        <Button variant="secondary" onClick={onDone}>
          تم
        </Button>
      </div>
    );
  }

  return (
    <form
      onSubmit={(event: FormEvent) => {
        event.preventDefault();
        addMutation.mutate();
      }}
      className="mt-4 flex flex-col gap-3 border-t border-gray-100 pt-4"
    >
      {error && <Alert>{error}</Alert>}
      <Input
        placeholder="الاسم"
        value={name}
        onChange={(event) => setName(event.target.value)}
        required
      />
      <Input
        placeholder="+9665XXXXXXXX"
        value={phone}
        onChange={(event) => setPhone(event.target.value)}
        dir="ltr"
        pattern="^\+[1-9]\d{6,14}$"
        required
      />
      <Input
        placeholder="الجنسية (اختياري)"
        value={nationality}
        onChange={(event) => setNationality(event.target.value)}
      />
      <Input
        placeholder="رقم الرخصة (اختياري)"
        value={licenseNumber}
        onChange={(event) => setLicenseNumber(event.target.value)}
      />
      <Button type="submit" disabled={addMutation.isPending}>
        {addMutation.isPending ? <Spinner /> : "إضافة سائق"}
      </Button>
    </form>
  );
}

export default function DriversPage() {
  const [showAddForm, setShowAddForm] = useState(false);

  const driversQuery = useQuery({
    queryKey: ["provider-drivers"],
    queryFn: () => apiGet<{ drivers: DriverItem[] }>("providers/me/drivers"),
  });

  if (driversQuery.isLoading) return <Spinner />;
  if (driversQuery.isError) return <Alert>تعذّر تحميل قائمة السائقين.</Alert>;

  const drivers = driversQuery.data?.data.drivers ?? [];

  return (
    <div className="flex flex-col gap-4">
      <div className="flex items-center justify-between">
        <h1 className="text-xl font-bold text-gray-900">السائقون</h1>
        {!showAddForm && <Button onClick={() => setShowAddForm(true)}>إضافة سائق</Button>}
      </div>

      <Card>
        {drivers.length === 0 && (
          <p className="text-sm text-gray-500">لا يوجد سائقون مضافون بعد.</p>
        )}
        {drivers.map((driver) => (
          <DriverRow key={driver.id} driver={driver} />
        ))}

        {showAddForm && (
          <AddDriverForm
            onDone={() => {
              setShowAddForm(false);
            }}
          />
        )}
      </Card>
    </div>
  );
}
