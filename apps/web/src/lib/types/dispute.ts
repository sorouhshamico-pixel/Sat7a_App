// Mirrors App\Http\Resources\Api\V1\DisputeResource (apps/backend).
export interface DisputeListItem {
  id: string;
  order_id: string | null;
  reason: string;
  description: string;
  status: string;
  assigned_to: string | null;
  resolution_notes: string | null;
  resolved_by: string | null;
  resolved_at: string | null;
  created_at: string;
}
