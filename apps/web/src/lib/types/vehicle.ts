// Mirrors App\Http\Resources\Api\V1\VehicleResource (apps/backend).
export interface VehicleItem {
  id: string;
  make: string;
  model: string;
  year: number;
  type: string | null;
  color: string | null;
  plate_number: string | null;
  notes: string | null;
  image_url: string | null;
  created_at: string;
}
