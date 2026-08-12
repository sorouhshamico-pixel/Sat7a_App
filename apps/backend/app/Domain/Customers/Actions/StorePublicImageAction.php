<?php

namespace App\Domain\Customers\Actions;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Profile/vehicle photos aren't compliance-sensitive documents (see
 * docs/COMPLIANCE.md) — they're stored on the public disk under a random
 * filename, never the client-supplied one.
 */
class StorePublicImageAction
{
    public function handle(UploadedFile $file, string $directory): string
    {
        $storedName = (string) Str::ulid().'.'.$file->extension();

        $path = $file->storeAs($directory, $storedName, ['disk' => 'public']);

        if ($path === false) {
            throw new RuntimeException('Failed to store the uploaded image.');
        }

        return $path;
    }
}
