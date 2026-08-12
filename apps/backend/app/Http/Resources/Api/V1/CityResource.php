<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Maps\Models\City;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin City
 */
class CityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'slug' => $this->slug,
            'name' => $this->name,
            'name_ar' => $this->name_ar,
        ];
    }
}
