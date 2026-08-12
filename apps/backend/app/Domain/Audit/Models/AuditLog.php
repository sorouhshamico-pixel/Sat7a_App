<?php

namespace App\Domain\Audit\Models;

use App\Models\User;
use App\Support\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Immutable, append-only. Never updated or deleted by application code —
 * a correction is a new entry, not an edit (see docs/SECURITY.md §Audit).
 * Only ever written to via AuditLogger, never from raw request input.
 *
 * @property string $action
 * @property string $entity_type
 * @property string $entity_id
 */
#[Fillable(['actor_id', 'action', 'entity_type', 'entity_id', 'old_values', 'new_values', 'reason', 'ip_address', 'user_agent'])]
class AuditLog extends Model
{
    use HasUlid;

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
