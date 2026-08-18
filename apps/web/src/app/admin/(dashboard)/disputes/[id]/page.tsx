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
import { DISPUTE_REASON_LABELS, DISPUTE_STATUS_LABELS, disputeStatusTone } from "@/lib/disputes";
import type { DisputeListItem } from "@/lib/types/dispute";

export default function DisputeDetailPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = use(params);
  const queryClient = useQueryClient();
  const [error, setError] = useState<string | null>(null);
  const [resolutionNotes, setResolutionNotes] = useState("");

  const disputeQuery = useQuery({
    queryKey: ["admin-dispute", id],
    queryFn: () => apiGet<DisputeListItem>(`admin/disputes/${id}`),
  });

  const advanceMutation = useMutation({
    mutationFn: (status: string) =>
      apiPost(`admin/disputes/${id}/status`, {
        status,
        resolution_notes: resolutionNotes || undefined,
      }),
    onSuccess: () => {
      setError(null);
      queryClient.invalidateQueries({ queryKey: ["admin-dispute", id] });
    },
    onError: (err) => setError(err instanceof ApiRequestError ? err.message : "حدث خطأ غير متوقع."),
  });

  if (disputeQuery.isLoading) return <Spinner />;
  if (disputeQuery.isError || !disputeQuery.data) return <Alert>تعذّر تحميل النزاع.</Alert>;

  const dispute = disputeQuery.data.data;

  return (
    <div className="flex flex-col gap-4">
      <div className="flex items-center justify-between">
        <h1 className="text-xl font-bold text-gray-900">نزاع #{dispute.id.slice(0, 8)}</h1>
        <Badge tone={disputeStatusTone(dispute.status)}>
          {DISPUTE_STATUS_LABELS[dispute.status] ?? dispute.status}
        </Badge>
      </div>

      {error && <Alert>{error}</Alert>}

      <Card>
        <CardTitle>التفاصيل</CardTitle>
        <dl className="grid grid-cols-2 gap-y-2 text-sm">
          <dt className="text-gray-500">السبب</dt>
          <dd>{DISPUTE_REASON_LABELS[dispute.reason] ?? dispute.reason}</dd>
          <dt className="text-gray-500">الوصف</dt>
          <dd>{dispute.description}</dd>
          {dispute.resolution_notes && (
            <>
              <dt className="text-gray-500">ملاحظات الحل</dt>
              <dd>{dispute.resolution_notes}</dd>
            </>
          )}
        </dl>
      </Card>

      {dispute.status !== "resolved" && dispute.status !== "rejected" && (
        <Card>
          <CardTitle>الإجراء</CardTitle>

          {dispute.status === "open" && (
            <Button
              disabled={advanceMutation.isPending}
              onClick={() => advanceMutation.mutate("under_review")}
            >
              {advanceMutation.isPending ? <Spinner /> : "بدء المراجعة"}
            </Button>
          )}

          {dispute.status === "under_review" && (
            <div className="flex flex-col gap-2">
              <Input
                placeholder="ملاحظات الحل (مطلوبة)"
                value={resolutionNotes}
                onChange={(event) => setResolutionNotes(event.target.value)}
                required
              />
              <div className="flex gap-2">
                <Button
                  disabled={advanceMutation.isPending}
                  onClick={() => advanceMutation.mutate("resolved")}
                >
                  {advanceMutation.isPending ? <Spinner /> : "حل النزاع"}
                </Button>
                <Button
                  variant="danger"
                  disabled={advanceMutation.isPending}
                  onClick={() => advanceMutation.mutate("rejected")}
                >
                  رفض النزاع
                </Button>
              </div>
            </div>
          )}
        </Card>
      )}
    </div>
  );
}
