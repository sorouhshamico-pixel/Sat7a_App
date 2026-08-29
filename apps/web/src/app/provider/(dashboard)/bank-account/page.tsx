"use client";

import { FormEvent, useEffect, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { apiGet, apiPut } from "@/lib/api/client";
import { ApiRequestError } from "@/lib/api/types";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Alert } from "@/components/ui/alert";
import { Spinner } from "@/components/ui/spinner";
import type { BankAccount } from "@/lib/types/provider";

export default function BankAccountPage() {
  const queryClient = useQueryClient();
  const [accountHolderName, setAccountHolderName] = useState("");
  const [iban, setIban] = useState("");
  const [bankName, setBankName] = useState("");
  const [error, setError] = useState<string | null>(null);

  const bankAccountQuery = useQuery({
    queryKey: ["provider-bank-account"],
    queryFn: () => apiGet<{ bank_account: BankAccount | null }>("providers/me/bank-account"),
  });

  const bankAccount = bankAccountQuery.data?.data.bank_account ?? null;

  useEffect(() => {
    if (!bankAccount) return;
    // Deferred to a microtask — see the identical fix in
    // src/app/provider/(dashboard)/page.tsx.
    queueMicrotask(() => {
      setAccountHolderName(bankAccount.account_holder_name);
      setIban(bankAccount.iban);
      setBankName(bankAccount.bank_name);
    });
  }, [bankAccount]);

  const saveMutation = useMutation({
    mutationFn: () =>
      apiPut<{ bank_account: BankAccount }>("providers/me/bank-account", {
        account_holder_name: accountHolderName,
        iban,
        bank_name: bankName,
      }),
    onSuccess: () => {
      setError(null);
      queryClient.invalidateQueries({ queryKey: ["provider-bank-account"] });
    },
    onError: (err) =>
      setError(err instanceof ApiRequestError ? err.message : "تعذّر حفظ الحساب البنكي."),
  });

  if (bankAccountQuery.isLoading) return <Spinner />;

  return (
    <div className="flex flex-col gap-4">
      <h1 className="text-xl font-bold text-gray-900">الحساب البنكي</h1>

      {bankAccount && (
        <div>
          <Badge tone={bankAccount.verified ? "success" : "warning"}>
            {bankAccount.verified ? "موثّق" : "بانتظار التوثيق"}
          </Badge>
        </div>
      )}

      <Card>
        <CardTitle>{bankAccount ? "تحديث بيانات الحساب" : "إضافة حساب بنكي"}</CardTitle>
        {error && (
          <div className="mb-3">
            <Alert>{error}</Alert>
          </div>
        )}
        <form
          onSubmit={(event: FormEvent) => {
            event.preventDefault();
            saveMutation.mutate();
          }}
          className="flex flex-col gap-3"
        >
          <label className="flex flex-col gap-1 text-sm text-gray-600">
            اسم صاحب الحساب
            <Input
              value={accountHolderName}
              onChange={(event) => setAccountHolderName(event.target.value)}
              required
            />
          </label>
          <label className="flex flex-col gap-1 text-sm text-gray-600">
            رقم الآيبان (IBAN)
            <Input
              value={iban}
              onChange={(event) => setIban(event.target.value)}
              dir="ltr"
              pattern="^SA\d{22}$"
              placeholder="SA0000000000000000000000"
              required
            />
          </label>
          <label className="flex flex-col gap-1 text-sm text-gray-600">
            اسم البنك
            <Input
              value={bankName}
              onChange={(event) => setBankName(event.target.value)}
              required
            />
          </label>
          <Button type="submit" disabled={saveMutation.isPending}>
            {saveMutation.isPending ? <Spinner /> : "حفظ"}
          </Button>
        </form>
      </Card>
    </div>
  );
}
