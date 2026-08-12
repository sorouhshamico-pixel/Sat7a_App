<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Compliance\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Deliberately never exposes `storage_path` — the file is only reachable
 * through the permission-checked download endpoint (see
 * docs/SECURITY.md §File uploads).
 *
 * @mixin Document
 */
class DocumentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'document_type' => $this->document_type->value,
            'document_number' => $this->document_number,
            'issued_at' => $this->issued_at,
            'expires_at' => $this->expires_at,
            'verification_status' => $this->verification_status->value,
            'rejection_reason' => $this->rejection_reason,
            'verified_at' => $this->verified_at,
            'original_filename' => $this->original_filename,
            'created_at' => $this->created_at,
        ];
    }
}
