"use client";

import { FormEvent, useRef, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { apiGet, apiUpload } from "@/lib/api/client";
import { ApiRequestError } from "@/lib/api/types";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Alert } from "@/components/ui/alert";
import { Spinner } from "@/components/ui/spinner";
import { DOCUMENT_STATUS_LABELS, DOCUMENT_TYPE_LABELS, documentStatusTone } from "@/lib/providers";
import type { DocumentItem } from "@/lib/types/provider";

export default function DocumentsPage() {
  const queryClient = useQueryClient();
  const formRef = useRef<HTMLFormElement>(null);
  const [documentType, setDocumentType] = useState("commercial_registration");
  const [documentNumber, setDocumentNumber] = useState("");
  const [expiresAt, setExpiresAt] = useState("");
  const [file, setFile] = useState<File | null>(null);
  const [error, setError] = useState<string | null>(null);

  const documentsQuery = useQuery({
    queryKey: ["provider-documents"],
    queryFn: () => apiGet<{ documents: DocumentItem[] }>("providers/me/documents"),
  });

  const uploadMutation = useMutation({
    mutationFn: () => {
      if (!file) throw new Error("no file");

      const formData = new FormData();
      formData.set("document_type", documentType);
      if (documentNumber) formData.set("document_number", documentNumber);
      if (expiresAt) formData.set("expires_at", expiresAt);
      formData.set("file", file);

      return apiUpload("providers/me/documents", formData);
    },
    onSuccess: () => {
      setError(null);
      setDocumentNumber("");
      setExpiresAt("");
      setFile(null);
      formRef.current?.reset();
      queryClient.invalidateQueries({ queryKey: ["provider-documents"] });
    },
    onError: (err) => setError(err instanceof ApiRequestError ? err.message : "تعذّر رفع المستند."),
  });

  const documents = documentsQuery.data?.data.documents ?? [];

  return (
    <div className="flex flex-col gap-4">
      <h1 className="text-xl font-bold text-gray-900">المستندات</h1>

      <Card>
        <CardTitle>رفع مستند جديد</CardTitle>
        {error && (
          <div className="mb-3">
            <Alert>{error}</Alert>
          </div>
        )}
        <form
          ref={formRef}
          onSubmit={(event: FormEvent) => {
            event.preventDefault();
            if (!file) {
              setError("الرجاء اختيار ملف.");
              return;
            }
            uploadMutation.mutate();
          }}
          className="flex flex-col gap-3"
        >
          <label className="flex flex-col gap-1 text-sm text-gray-600">
            نوع المستند
            <select
              value={documentType}
              onChange={(event) => setDocumentType(event.target.value)}
              className="rounded-md border border-gray-300 px-3 py-2 text-sm"
            >
              {Object.entries(DOCUMENT_TYPE_LABELS).map(([value, label]) => (
                <option key={value} value={value}>
                  {label}
                </option>
              ))}
            </select>
          </label>
          <Input
            placeholder="رقم المستند (اختياري)"
            value={documentNumber}
            onChange={(event) => setDocumentNumber(event.target.value)}
          />
          <label className="flex flex-col gap-1 text-sm text-gray-600">
            تاريخ الانتهاء (اختياري)
            <Input
              type="date"
              value={expiresAt}
              onChange={(event) => setExpiresAt(event.target.value)}
            />
          </label>
          <Input
            type="file"
            accept=".pdf,.jpg,.jpeg,.png"
            onChange={(event) => setFile(event.target.files?.[0] ?? null)}
            required
          />
          <Button type="submit" disabled={uploadMutation.isPending}>
            {uploadMutation.isPending ? <Spinner /> : "رفع المستند"}
          </Button>
        </form>
      </Card>

      <Card>
        <CardTitle>المستندات المرفوعة</CardTitle>
        {documentsQuery.isLoading && <Spinner />}
        {documents.length === 0 && !documentsQuery.isLoading && (
          <p className="text-sm text-gray-500">لا توجد مستندات مرفوعة بعد.</p>
        )}
        <div className="flex flex-col gap-3">
          {documents.map((document) => (
            <div
              key={document.id}
              className="flex items-center justify-between border-t border-gray-100 pt-3 first:border-t-0 first:pt-0"
            >
              <div>
                <p className="text-sm font-medium text-gray-900">
                  {DOCUMENT_TYPE_LABELS[document.document_type] ?? document.document_type}
                </p>
                <p className="text-xs text-gray-500">{document.original_filename}</p>
                {document.rejection_reason && (
                  <p className="text-xs text-red-600">السبب: {document.rejection_reason}</p>
                )}
              </div>
              <Badge tone={documentStatusTone(document.verification_status)}>
                {DOCUMENT_STATUS_LABELS[document.verification_status] ??
                  document.verification_status}
              </Badge>
            </div>
          ))}
        </div>
      </Card>
    </div>
  );
}
