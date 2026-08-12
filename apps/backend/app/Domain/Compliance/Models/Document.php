<?php

namespace App\Domain\Compliance\Models;

use App\Domain\Compliance\Enums\DocumentType;
use App\Domain\Compliance\Enums\DocumentVerificationStatus;
use App\Models\User;
use App\Support\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * Never served from a public URL — always through a permission-checked,
 * short-lived signed route (see docs/SECURITY.md §File uploads).
 *
 * @property DocumentType $document_type
 * @property DocumentVerificationStatus $verification_status
 * @property Carbon|null $expires_at
 * @property string $storage_path
 */
#[Fillable(['documentable_type', 'documentable_id', 'uploaded_by', 'document_type', 'document_number', 'issued_at', 'expires_at', 'storage_path', 'original_filename', 'mime_type', 'size_bytes', 'verification_status', 'verified_by', 'verified_at', 'rejection_reason'])]
class Document extends Model
{
    use HasUlid;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'document_type' => DocumentType::class,
            'verification_status' => DocumentVerificationStatus::class,
            'issued_at' => 'date',
            'expires_at' => 'date',
            'verified_at' => 'datetime',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
