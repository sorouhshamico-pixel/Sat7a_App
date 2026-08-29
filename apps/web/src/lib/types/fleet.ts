import type { DriverItem } from "./driver";

// Mirrors App\Http\Resources\Api\V1\TowTruckResource (apps/backend).
export interface TowTruckItem {
  id: string;
  manufacturer: string;
  model: string;
  year: number;
  plate_number: string;
  capacity: string | null;
  service_capabilities: string[];
  status: string;
  driver: DriverItem | null;
  current_latitude: number | null;
  current_longitude: number | null;
  last_location_at: string | null;
  created_at: string;
}

// Mirrors App\Http\Controllers\Api\V1\Providers\ProviderFleetController::summary().
export interface FleetSummary {
  total_tow_trucks: number;
  total_drivers: number;
  available_drivers: number;
  tow_trucks_by_status: Record<string, number>;
}
