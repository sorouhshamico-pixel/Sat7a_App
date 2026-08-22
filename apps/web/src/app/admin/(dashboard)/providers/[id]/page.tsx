"use client";

import Link from "next/link";
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
  DOCUMENT_STATUS_LABELS,
  DOCUMENT_TYPE_LABELS,
  PROVIDER_STATUS_LABELS,
  documentStatusTone,
  providerStatusTone,
} from "@/lib/providers";
import type {
  BankAccount,
  DocumentItem,
  ProviderBalance,
  ProviderListItem,
} from "@/lib/types/provider";
import type { ReviewItem } from "@/lib/types/review";

interface ProviderShowResponse {
  provider: ProviderListItem;
  documents: DocumentItem[];
}

export default function ProviderDetailPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = use(params);
  const queryClient = useQueryClient();
  const [error, setError] = useState<string | null>(null);
  const [rejectReason, setRejectReason] = useState("");
  const [suspendReason, setSuspendReason] = useState("");
  const [showRejectForm, setShowRejectForm] = useState(false);
  const [showSuspendForm, setShowSuspendForm] = useState(false);
  const [documentRejectingId, setDocumentRejectingId] = useState<string | null>(null);
  const [documentRejectReason, setDocumentRejectReason] = useState("");
  const [periodStart, setPeriodStart] = useState("");
  const [periodEnd, setPeriodEnd] = useState("");

  const providerQuery = useQuery({
    queryKey: ["admin-provider", id],
    queryFn: () => apiGet<ProviderShowResponse>(`admin/providers/${id}`),
  });

  const balanceQuery = useQuery({
    queryKey: ["admin-provider-balance", id],
    queryFn: () => apiGet<{ balance: ProviderBalance }>(`admin/providers/${id}/balance`),
  });

  const bankAccountQuery = useQuery({
    queryKey: ["admin-provider-bank-account", id],
    queryFn: () => apiGet<{ bank_account: BankAccount }>(`admin/providers/${id}/bank-account`),
    retry: false,
  });

  const reviewsQuery = useQuery({
    queryKey: ["admin-provider-reviews", id],
    queryFn: () => apiGet<{ reviews: ReviewItem[] }>(`admin/providers/${id}/reviews`),
  });

  function invalidateProvider() {
    queryClient.invalidateQueries({ queryKey: ["admin-provider", id] });
  }

  function handleError(err: unknown) {
    setError(err instanceof ApiRequestError ? err.message : "حدث خطأ غير متوقع.");
  }

  const approveMutation = useMutation({
    mutationFn: () => apiPost(`admin/providers/${id}/approve`),
    onSuccess: () => {
      setError(null);
      invalidateProvider();
    },
    onError: handleError,
  });

  const rejectMutation = useMutation({
    mutationFn: () => apiPost(`admin/providers/${id}/reject`, { reason: rejectReason }),
    onSuccess: () => {
      setError(null);
      setShowRejectForm(false);
      setRejectReason("");
      invalidateProvider();
    },
    onError: handleError,
  });

  const suspendMutation = useMutation({
    mutationFn: () => apiPost(`admin/providers/${id}/suspend`, { reason: suspendReason }),
    onSuccess: () => {
      setError(null);
      setShowSuspendForm(false);
      setSuspendReason("");
      invalidateProvider();
    },
    onError: handleError,
  });

  const verifyDocumentMutation = useMutation({
    mutationFn: (documentId: string) => apiPost(`admin/documents/${documentId}/verify`),
    onSuccess: () => {
      setError(null);
      invalidateProvider();
    },
    onError: handleError,
  });

  const rejectDocumentMutation = useMutation({
    mutationFn: (documentId: string) =>
      apiPost(`admin/documents/${documentId}/reject`, { reason: documentRejectReason }),
    onSuccess: () => {
      setError(null);
      setDocumentRejectingId(null);
      setDocumentRejectReason("");
      invalidateProvider();
    },
    onError: handleError,
  });

  const verifyBankAccountMutation = useMutation({
    mutationFn: () => apiPost(`admin/providers/${id}/bank-account/verify`),
    onSuccess: () => {
      setError(null);
      queryClient.invalidateQueries({ queryKey: ["admin-provider-bank-account", id] });
    },
    onError: handleError,
  });

  const generateSettlementMutation = useMutation({
    mutationFn: () =>
      apiPost(`admin/providers/${id}/settlements`, {
        period_start: periodStart,
        period_end: periodEnd,
      }),
    onSuccess: () => {
      setError(null);
      setPeriodStart("");
      setPeriodEnd("");
      queryClient.invalidateQueries({ queryKey: ["admin-provider-balance", id] });
    },
    onError: handleError,
  });

  if (providerQuery.isLoading) return <Spinner />;
  if (providerQuery.isError || !providerQuery.data)
    return <Alert>تعذّر تحميل بيانات مزود الخدمة.</Alert>;

  const { provider, documents } = providerQuery.data.data;
  const canApproveOrReject = provider.status === "pending" || provider.status === "under_review";
  const canSuspend = provider.status === "approved";

  return (
    <div className="flex flex-col gap-4">
      <div className="flex items-center justify-between">
        <h1 className="text-xl font-bold text-gray-900">{provider.business_name}</h1>
        <Badge tone={providerStatusTone(provider.status)}>
          {PROVIDER_STATUS_LABELS[provider.status] ?? provider.status}
        </Badge>
      </div>

      {error && <Alert>{error}</Alert>}

      <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <Card>
          <CardTitle>بيانات مزود الخدمة</CardTitle>
          <dl className="grid grid-cols-2 gap-y-2 text-sm">
            <dt className="text-gray-500">السجل التجاري</dt>
            <dd>{provider.commercial_registration_number ?? "—"}</dd>
            <dt className="text-gray-500">الرقم الضريبي</dt>
            <dd>{provider.tax_number ?? "—"}</dd>
            <dt className="text-gray-500">هاتف التواصل</dt>
            <dd dir="ltr">{provider.contact_phone}</dd>
            <dt className="text-gray-500">البريد الإلكتروني</dt>
            <dd>{provider.contact_email ?? "—"}</dd>
            <dt className="text-gray-500">التقييم</dt>
            <dd>{provider.rating ?? "—"}</dd>
            {provider.rejection_reason && (
              <>
                <dt className="text-gray-500">سبب الرفض</dt>
                <dd>{provider.rejection_reason}</dd>
              </>
            )}
            {provider.suspension_reason && (
              <>
                <dt className="text-gray-500">سبب الإيقاف</dt>
                <dd>{provider.suspension_reason}</dd>
              </>
            )}
          </dl>
        </Card>

        <Card>
          <CardTitle>الرصيد</CardTitle>
          {balanceQuery.isLoading && <Spinner />}
          {balanceQuery.data && (
            <dl className="grid grid-cols-2 gap-y-2 text-sm">
              <dt className="text-gray-500">قيد الانتظار</dt>
              <dd>{balanceQuery.data.data.balance.pending_balance} هللة</dd>
              <dt className="text-gray-500">متاح للتسوية</dt>
              <dd>{balanceQuery.data.data.balance.available_balance} هللة</dd>
              <dt className="text-gray-500">مُسوّى</dt>
              <dd>{balanceQuery.data.data.balance.settled_balance} هللة</dd>
              <dt className="text-gray-500">الإجمالي المستحق</dt>
              <dd>{balanceQuery.data.data.balance.total_payable} هللة</dd>
            </dl>
          )}
        </Card>
      </div>

      <Card>
        <CardTitle>إجراءات الامتثال</CardTitle>
        <div className="flex flex-wrap gap-2">
          <Button
            disabled={!canApproveOrReject || approveMutation.isPending}
            onClick={() => approveMutation.mutate()}
          >
            {approveMutation.isPending ? <Spinner /> : "اعتماد"}
          </Button>
          <Button
            variant="secondary"
            disabled={!canApproveOrReject}
            onClick={() => setShowRejectForm((v) => !v)}
          >
            رفض
          </Button>
          <Button
            variant="danger"
            disabled={!canSuspend}
            onClick={() => setShowSuspendForm((v) => !v)}
          >
            إيقاف
          </Button>
        </div>

        {showRejectForm && (
          <form
            onSubmit={(event) => {
              event.preventDefault();
              rejectMutation.mutate();
            }}
            className="mt-4 flex flex-col gap-2 border-t border-gray-100 pt-4"
          >
            <Input
              placeholder="سبب الرفض"
              value={rejectReason}
              onChange={(event) => setRejectReason(event.target.value)}
              required
            />
            <Button type="submit" variant="secondary" disabled={rejectMutation.isPending}>
              {rejectMutation.isPending ? <Spinner /> : "تأكيد الرفض"}
            </Button>
          </form>
        )}

        {showSuspendForm && (
          <form
            onSubmit={(event) => {
              event.preventDefault();
              suspendMutation.mutate();
            }}
            className="mt-4 flex flex-col gap-2 border-t border-gray-100 pt-4"
          >
            <Input
              placeholder="سبب الإيقاف"
              value={suspendReason}
              onChange={(event) => setSuspendReason(event.target.value)}
              required
            />
            <Button type="submit" variant="danger" disabled={suspendMutation.isPending}>
              {suspendMutation.isPending ? <Spinner /> : "تأكيد الإيقاف"}
            </Button>
          </form>
        )}
      </Card>

      <Card>
        <CardTitle>المستندات</CardTitle>
        {documents.length === 0 && <p className="text-sm text-gray-500">لا توجد مستندات مرفوعة.</p>}
        <div className="flex flex-col gap-3">
          {documents.map((document) => (
            <div key={document.id} className="rounded-md border border-gray-100 p-3">
              <div className="flex items-center justify-between">
                <span className="text-sm font-medium">
                  {DOCUMENT_TYPE_LABELS[document.document_type] ?? document.document_type}
                </span>
                <Badge tone={documentStatusTone(document.verification_status)}>
                  {DOCUMENT_STATUS_LABELS[document.verification_status] ??
                    document.verification_status}
                </Badge>
              </div>
              <p className="mt-1 text-xs text-gray-500">{document.original_filename}</p>
              {document.rejection_reason && (
                <p className="mt-1 text-xs text-red-700">سبب الرفض: {document.rejection_reason}</p>
              )}

              {document.verification_status === "pending" && (
                <div className="mt-2 flex flex-wrap items-center gap-2">
                  <Button
                    disabled={verifyDocumentMutation.isPending}
                    onClick={() => verifyDocumentMutation.mutate(document.id)}
                  >
                    توثيق
                  </Button>
                  <Button
                    variant="secondary"
                    onClick={() =>
                      setDocumentRejectingId(
                        documentRejectingId === document.id ? null : document.id,
                      )
                    }
                  >
                    رفض
                  </Button>
                </div>
              )}

              {documentRejectingId === document.id && (
                <form
                  onSubmit={(event) => {
                    event.preventDefault();
                    rejectDocumentMutation.mutate(document.id);
                  }}
                  className="mt-2 flex gap-2"
                >
                  <Input
                    placeholder="سبب الرفض"
                    value={documentRejectReason}
                    onChange={(event) => setDocumentRejectReason(event.target.value)}
                    required
                  />
                  <Button
                    type="submit"
                    variant="secondary"
                    disabled={rejectDocumentMutation.isPending}
                  >
                    {rejectDocumentMutation.isPending ? <Spinner /> : "تأكيد"}
                  </Button>
                </form>
              )}
            </div>
          ))}
        </div>
      </Card>

      <Card>
        <CardTitle>الحساب البنكي</CardTitle>
        {bankAccountQuery.isLoading && <Spinner />}
        {bankAccountQuery.isError && (
          <p className="text-sm text-gray-500">لا يوجد حساب بنكي مسجّل.</p>
        )}
        {bankAccountQuery.data && (
          <div className="flex flex-col gap-2 text-sm">
            <dl className="grid grid-cols-2 gap-y-2">
              <dt className="text-gray-500">صاحب الحساب</dt>
              <dd>{bankAccountQuery.data.data.bank_account.account_holder_name}</dd>
              <dt className="text-gray-500">البنك</dt>
              <dd>{bankAccountQuery.data.data.bank_account.bank_name}</dd>
              <dt className="text-gray-500">IBAN</dt>
              <dd dir="ltr">{bankAccountQuery.data.data.bank_account.iban}</dd>
              <dt className="text-gray-500">الحالة</dt>
              <dd>
                <Badge
                  tone={bankAccountQuery.data.data.bank_account.verified ? "success" : "warning"}
                >
                  {bankAccountQuery.data.data.bank_account.verified ? "موثّق" : "غير موثّق"}
                </Badge>
              </dd>
            </dl>
            {!bankAccountQuery.data.data.bank_account.verified && (
              <Button
                disabled={verifyBankAccountMutation.isPending}
                onClick={() => verifyBankAccountMutation.mutate()}
              >
                {verifyBankAccountMutation.isPending ? <Spinner /> : "توثيق الحساب البنكي"}
              </Button>
            )}
          </div>
        )}
      </Card>

      <Card>
        <CardTitle>إنشاء دفعة تسوية</CardTitle>
        <form
          onSubmit={(event) => {
            event.preventDefault();
            generateSettlementMutation.mutate();
          }}
          className="flex flex-wrap items-end gap-2"
        >
          <label className="flex flex-col gap-1 text-sm text-gray-600">
            من تاريخ
            <Input
              type="date"
              value={periodStart}
              onChange={(event) => setPeriodStart(event.target.value)}
              required
            />
          </label>
          <label className="flex flex-col gap-1 text-sm text-gray-600">
            إلى تاريخ
            <Input
              type="date"
              value={periodEnd}
              onChange={(event) => setPeriodEnd(event.target.value)}
              required
            />
          </label>
          <Button type="submit" disabled={generateSettlementMutation.isPending}>
            {generateSettlementMutation.isPending ? <Spinner /> : "إنشاء"}
          </Button>
        </form>
        {generateSettlementMutation.isSuccess && (
          <p className="mt-2 text-sm text-green-700">
            تم إنشاء دفعة التسوية. راجعها في{" "}
            <Link href="/admin/settlements" className="underline">
              صفحة التسويات
            </Link>
            .
          </p>
        )}
      </Card>

      <Card>
        <CardTitle>التقييمات</CardTitle>
        {reviewsQuery.isLoading && <Spinner />}
        {reviewsQuery.data && reviewsQuery.data.data.reviews.length === 0 && (
          <p className="text-sm text-gray-500">لا توجد تقييمات بعد.</p>
        )}
        {reviewsQuery.data && reviewsQuery.data.data.reviews.length > 0 && (
          <ul className="flex flex-col gap-2">
            {reviewsQuery.data.data.reviews.map((review) => (
              <li key={review.id} className="rounded-md border border-gray-100 p-2 text-sm">
                <span className="font-medium">{review.rating} / 5</span>
                {review.comment && <p className="mt-1 text-gray-600">{review.comment}</p>}
              </li>
            ))}
          </ul>
        )}
      </Card>
    </div>
  );
}
