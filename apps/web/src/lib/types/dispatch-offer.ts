// Mirrors App\Http\Resources\Api\V1\DispatchOfferResource (apps/backend),
// as seen by a driver — the embedded `order` summary is only present
// because App\Http\Controllers\Api\V1\Drivers\DispatchOfferController::index
// eager-loads it.
export interface DriverDispatchOffer {
  id: string;
  status: string;
  wave: number;
  distance_meters: number;
  expires_at: string;
  responded_at: string | null;
  order: {
    id: string;
    service_type: string;
    pickup_formatted_address: string;
    dropoff_formatted_address: string;
    quoted_price: number;
  };
  created_at: string;
}
