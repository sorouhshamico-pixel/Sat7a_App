// Mirrors App\Http\Resources\Api\V1\SettlementBatchResource (apps/backend).
export interface SettlementBatchItem {
  id: string;
  provider_id: string | null;
  period_start: string;
  period_end: string;
  gross: number;
  commission: number;
  deductions: number;
  net: number;
  status: string;
  approved_by: string | null;
  paid_at: string | null;
  reference: string | null;
  failure_reason: string | null;
  created_at: string;
  updated_at: string;
}
