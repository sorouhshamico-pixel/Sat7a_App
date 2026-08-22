// Mirrors App\Http\Resources\Api\V1\ReviewResource (apps/backend).
export interface ReviewItem {
  id: string;
  order_id: string | null;
  driver_id: string | null;
  rating: number;
  comment: string | null;
  created_at: string;
}
