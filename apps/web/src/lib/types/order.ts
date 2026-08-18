// Mirrors App\Http\Resources\Api\V1\OrderResource (apps/backend).
export interface OrderListItem {
  id: string;
  status: string;
  service_type: string;
  vehicle: { id: string; make: string; model: string; year: number } | null;
  pickup: { latitude: number; longitude: number; formatted_address: string };
  dropoff: { latitude: number; longitude: number; formatted_address: string };
  quoted_price: number;
  final_price: number | null;
  created_at: string;
}

export interface OrderDetail extends OrderListItem {
  notes: string | null;
  payment_method: string;
  current_dispatch_wave: number;
  manual_dispatch_required: boolean;
  assigned_provider: { id: string; business_name: string } | null;
  assigned_driver: { id: string; name: string; phone: string; rating: string | null } | null;
  assigned_tow_truck: { id: string; plate_number: string } | null;
  cancelled_by: string | null;
  cancellation_reason: string | null;
  cancellation_fee: number;
  accepted_at: string | null;
  arrived_at: string | null;
  trip_started_at: string | null;
  completed_at: string | null;
  cancelled_at: string | null;
}

export interface DispatchOffer {
  id: string;
  status: string;
  wave: number;
  distance_meters: number;
  expires_at: string;
  responded_at: string | null;
  created_at: string;
}

export interface Paginated<T> {
  items: T[];
  meta: { current_page: number; per_page: number; total: number };
}
