<?php

namespace App\Domain\Compliance\Actions;

use App\Domain\Compliance\Enums\DocumentType;
use App\Domain\Compliance\Enums\DocumentVerificationStatus;
use App\Domain\Compliance\Models\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class UploadDocumentAction
{
    public function handle(
        Model $documentable,
        UploadedFile $file,
        DocumentType $documentType,
        User $uploadedBy,
        ?string $documentNumber,
        ?string $issuedAt,
        ?string $expiresAt,
    ): Document {
        // Stored under a random name, never the client-supplied filename —
        // the original name is kept only as display metadata (see
        // docs/SECURITY.md §File uploads).
        $storedName = (string) Str::ulid().'.'.$file->getClientOriginalExtension();
        $path = $file->storeAs(
            'provider-'.$documentable->getKey(),
            $storedName,
            ['disk' => 'documents'],
        );

        if ($path === false) {
            throw new RuntimeException('Failed to store the uploaded document.');
        }

        return Document::create([
            'documentable_type' => $documentable::class,
            'documentable_id' => $documentable->getKey(),
            'uploaded_by' => $uploadedBy->id,
            'document_type' => $documentType->value,
            'document_number' => $documentNumber,
            'issued_at' => $issuedAt,
            'expires_at' => $expiresAt,
            'storage_path' => $path,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
            'size_bytes' => $file->getSize() ?: 0,
            'verification_status' => DocumentVerificationStatus::Pending->value,
        ]);
    }

    public function delete(Document $document): void
    {
        Storage::disk('documents')->delete($document->storage_path);
        $document->delete();
    }
}
