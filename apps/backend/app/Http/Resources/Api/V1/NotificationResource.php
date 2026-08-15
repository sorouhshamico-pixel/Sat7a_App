<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Notifications\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Notification
 */
class NotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'type' => $this->type->value,
            'title' => $this->title,
            'body' => $this->body,
            'data' => $this->data,
            'channels' => $this->channels,
            'read_at' => $this->read_at,
            'created_at' => $this->created_at,
        ];
    }
}
