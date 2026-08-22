// Mirrors App\Http\Resources\Api\V1\PaymentResource (apps/backend).
export interface PaymentListItem {
  id: string;
  order_id: string | null;
  status: string;
  method: string;
  amount: number;
  currency: string;
  card_brand: string | null;
  card_last_four: string | null;
  failure_reason: string | null;
  refunded_amount: number;
  authorized_at: string | null;
  captured_at: string | null;
  failed_at: string | null;
  cancelled_at: string | null;
  created_at: string;
}

// Mirrors App\Http\Resources\Api\V1\RefundResource.
export interface RefundItem {
  id: string;
  amount: number;
  reason: string | null;
  status: string;
  failure_reason: string | null;
  created_at: string;
}
