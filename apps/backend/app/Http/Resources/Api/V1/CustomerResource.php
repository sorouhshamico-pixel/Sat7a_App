<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Customers\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin Customer
 */
class CustomerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'name' => $this->whenLoaded('user', fn () => $this->user->name),
            'phone' => $this->whenLoaded('user', fn () => $this->user->phone),
            'email' => $this->whenLoaded('user', fn () => $this->user->email),
            'locale' => $this->whenLoaded('user', fn () => $this->user->locale),
            'avatar_url' => $this->avatar_path !== null ? Storage::disk('public')->url($this->avatar_path) : null,
            'preferences' => $this->preferences ?? (object) [],
            'notification_preferences' => $this->notification_preferences,
            'created_at' => $this->created_at,
        ];
    }
}
