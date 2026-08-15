<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Ledger\Models\ProviderBankAccount;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProviderBankAccount
 */
class ProviderBankAccountResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $showFull = $user?->provider_id === $this->provider_id || $user?->hasPermission('settlements.view_bank_details');

        return [
            'id' => $this->public_id,
            'account_holder_name' => $this->account_holder_name,
            'iban' => $showFull ? $this->iban : $this->maskedIban(),
            'bank_name' => $this->bank_name,
            'verified' => $this->verified,
            'verified_at' => $this->verified_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
