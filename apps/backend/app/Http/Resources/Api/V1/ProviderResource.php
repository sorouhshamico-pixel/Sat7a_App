<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Providers\Models\Provider;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Provider
 */
class ProviderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'business_name' => $this->business_name,
            'commercial_registration_number' => $this->commercial_registration_number,
            'tax_number' => $this->tax_number,
            'contact_phone' => $this->contact_phone,
            'contact_email' => $this->contact_email,
            'status' => $this->status->value,
            'rejection_reason' => $this->rejection_reason,
            'suspension_reason' => $this->suspension_reason,
            'approved_at' => $this->approved_at,
            'created_at' => $this->created_at,
        ];
    }
}
