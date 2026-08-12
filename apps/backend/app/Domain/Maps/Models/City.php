<?php

namespace App\Domain\Maps\Models;

use App\Support\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * `serviceZones()` is added back once the PostGIS extension is active and
 * the `service_zones` table exists (see docs/DEPLOYMENT.md §One-time
 * PostGIS setup and docs/ROADMAP.md Phase 6).
 */
#[Fillable(['slug', 'name', 'name_ar', 'is_active'])]
class City extends Model
{
    use HasUlid;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
