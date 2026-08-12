<?php

namespace App\Support\Concerns;

use Illuminate\Support\Str;

/**
 * Adds a `public_id` ULID to a model on creation. This is the identifier
 * exposed in API responses/URLs for public-facing entities — the internal
 * auto-increment `id` is never exposed (see docs/DATABASE_SCHEMA.md).
 */
trait HasUlid
{
    public static function bootHasUlid(): void
    {
        static::creating(function ($model): void {
            if (empty($model->public_id)) {
                $model->public_id = (string) Str::ulid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
