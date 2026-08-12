<?php

namespace App\Domain\Customers\Models;

use App\Support\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['make', 'model', 'year', 'type', 'color', 'plate_number', 'notes', 'image_path'])]
class Vehicle extends Model
{
    use HasUlid;

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
