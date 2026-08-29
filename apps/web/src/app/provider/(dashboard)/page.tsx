"use client";

import { FormEvent, useEffect, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { apiGet, apiPatch } from "@/lib/api/client";
import { ApiRequestError } from "@/lib/api/types";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Alert } from "@/components/ui/alert";
import { Spinner } from "@/components/ui/spinner";
import { PROVIDER_STATUS_LABELS, providerStatusTone } from "@/lib/providers";
import type { ProviderListItem } from "@/lib/types/provider";
import type { FleetSummary } from "@/lib/types/fleet";

export default function ProviderDashboardPage() {
  const queryClient = useQueryClient();
  const [editing, setEditing] = useState(false);
  const [businessName, setBusinessName] = useState("");
  const [contactEmail, setContactEmail] = useState("");
  const [crNumber, setCrNumber] = useState("");
  const [taxNumber, setTaxNumber] = useState("");
  const [error, setError] = useState<string | null>(null);

  const providerQuery = useQuery({
    queryKey: ["provider-me"],
    queryFn: () => apiGet<{ provider: ProviderListItem }>("providers/me"),
  });

  const summaryQuery = useQuery({
    queryKey: ["provider-fleet-summary"],
    queryFn: () => apiGet<{ summary: FleetSummary }>("providers/me/fleet/summary"),
  });

  const provider = providerQuery.data?.data.provider;

  useEffect(() => {
    if (!provider) return;
    // Deferred to a microtask — a bare setState call directly in an
    // effect body trips react-hooks/set-state-in-effect (see
    // docs/CUSTOMER_WEB_APP.md's address-search fix for the same class of
    // issue in Phase 19).
    queueMicrotask(() => {
      setBusinessName(provider.business_name);
      setContactEmail(provider.contact_email ?? "");
      setCrNumber(provider.commercial_registration_number ?? "");
      setTaxNumber(provider.tax_number ?? "");
    });
  }, [provider]);

  const updateMutation = useMutation({
    mutationFn: () =>
      apiPatch<{ provider: ProviderListItem }>("providers/me", {
        business_name: businessName,
        contact_email: contactEmail || null,
        commercial_registration_number: crNumber || null,
        tax_number: taxNumber || null,
      }),
    onSuccess: () => {
      setError(null);
      setEditing(false);
      queryClient.invalidateQueries({ queryKey: ["provider-me"] });
    },
    onError: (err) =>
      setError(err instanceof ApiRequestError ? err.message : "تعذّر حفظ البيانات."),
  });

  if (providerQuery.isLoading) return <Spinner />;
  if (providerQuery.isError || !provider) return <Alert>تعذّر تحميل بيانات المزود.</Alert>;

  const summary = summaryQuery.data?.data.summary;

  return (
    <div className="flex flex-col gap-4">
      <div className="flex items-center justify-between">
        <h1 className="text-xl font-bold text-gray-900">{provider.business_name}</h1>
        <Badge tone={providerStatusTone(provider.status)}>
          {PROVIDER_STATUS_LABELS[provider.status] ?? provider.status}
        </Badge>
      </div>

      {provider.status === "rejected" && provider.rejection_reason && (
        <Alert>سبب الرفض: {provider.rejection_reason}</Alert>
      )}
      {provider.status === "suspended" && provider.suspension_reason && (
        <Alert>سبب الإيقاف: {provider.suspension_reason}</Alert>
      )}

      {summary && (
        <div className="grid grid-cols-3 gap-4">
          <Card>
            <p className="text-xs text-gray-500">إجمالي المركبات</p>
            <p className="mt-1 text-2xl font-bold text-gray-900">{summary.total_tow_trucks}</p>
          </Card>
          <Card>
            <p className="text-xs text-gray-500">إجمالي السائقين</p>
            <p className="mt-1 text-2xl font-bold text-gray-900">{summary.total_drivers}</p>
          </Card>
          <Card>
            <p className="text-xs text-gray-500">السائقون المتاحون</p>
            <p className="mt-1 text-2xl font-bold text-gray-900">{summary.available_drivers}</p>
          </Card>
        </div>
      )}

      <Card>
        <CardTitle>بيانات المنشأة</CardTitle>
        {!editing && (
          <div className="mb-3 flex justify-end">
            <Button variant="secondary" onClick={() => setEditing(true)}>
              تعديل
            </Button>
          </div>
        )}

        {error && (
          <div className="mb-3">
            <Alert>{error}</Alert>
          </div>
        )}

        {!editing ? (
          <dl className="grid grid-cols-2 gap-y-2 text-sm">
            <dt className="text-gray-500">اسم المنشأة</dt>
            <dd>{provider.business_name}</dd>
            <dt className="text-gray-500">رقم الجوال</dt>
            <dd dir="ltr">{provider.contact_phone}</dd>
            <dt className="text-gray-500">البريد الإلكتروني</dt>
            <dd>{provider.contact_email ?? "—"}</dd>
            <dt className="text-gray-500">السجل التجاري</dt>
            <dd>{provider.commercial_registration_number ?? "—"}</dd>
            <dt className="text-gray-500">الرقم الضريبي</dt>
            <dd>{provider.tax_number ?? "—"}</dd>
            <dt className="text-gray-500">التقييم</dt>
            <dd>{provider.rating ?? "—"}</dd>
          </dl>
        ) : (
          <form
            onSubmit={(event: FormEvent) => {
              event.preventDefault();
              updateMutation.mutate();
            }}
            className="flex flex-col gap-3"
          >
            <label className="flex flex-col gap-1 text-sm text-gray-600">
              اسم المنشأة
              <Input
                value={businessName}
                onChange={(event) => setBusinessName(event.target.value)}
                required
              />
            </label>
            <label className="flex flex-col gap-1 text-sm text-gray-600">
              البريد الإلكتروني
              <Input
                type="email"
                value={contactEmail}
                onChange={(event) => setContactEmail(event.target.value)}
              />
            </label>
            <label className="flex flex-col gap-1 text-sm text-gray-600">
              السجل التجاري
              <Input value={crNumber} onChange={(event) => setCrNumber(event.target.value)} />
            </label>
            <label className="flex flex-col gap-1 text-sm text-gray-600">
              الرقم الضريبي
              <Input value={taxNumber} onChange={(event) => setTaxNumber(event.target.value)} />
            </label>
            <div className="flex gap-2">
              <Button type="submit" disabled={updateMutation.isPending}>
                {updateMutation.isPending ? <Spinner /> : "حفظ"}
              </Button>
              <Button type="button" variant="secondary" onClick={() => setEditing(false)}>
                إلغاء
              </Button>
            </div>
          </form>
        )}
      </Card>
    </div>
  );
}
