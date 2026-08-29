// Mirrors App\Http\Resources\Api\V1\DriverResource (apps/backend).
export interface DriverItem {
  id: string;
  name: string | null;
  phone: string | null;
  nationality: string | null;
  license_number: string | null;
  license_expires_at: string | null;
  status: string;
  is_available: boolean;
  rating: string | null;
  tow_truck_id: string | null;
  created_at: string;
}
