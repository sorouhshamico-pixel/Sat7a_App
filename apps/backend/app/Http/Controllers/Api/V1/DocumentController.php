<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Compliance\Models\Document;
use App\Domain\Drivers\Models\Driver;
use App\Domain\Providers\Models\Provider;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use App\Support\Enums\ErrorCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

/**
 * Every document is served from here — never a public URL (see
 * docs/SECURITY.md §File uploads). Access is granted to anyone belonging
 * to the same provider as the underlying record (the provider itself, or
 * one of its drivers) or to a staff member with the matching permission,
 * checked fresh on every request rather than trusting a previously-issued
 * link.
 */
class DocumentController extends Controller
{
    public function download(Request $request, Document $document): Response|JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $this->canAccess($user, $document)) {
            return ApiResponse::error(ErrorCode::Unauthorized, 'This action is unauthorized.', 403);
        }

        $contents = Storage::disk('documents')->get($document->storage_path);

        if ($contents === null) {
            return ApiResponse::error(ErrorCode::NotFound, 'The file could not be found.', 404);
        }

        return response($contents, 200, [
            'Content-Type' => $document->mime_type,
            'Content-Disposition' => 'inline; filename="'.$document->original_filename.'"',
        ]);
    }

    private function canAccess(User $user, Document $document): bool
    {
        $documentableProviderId = $this->documentableProviderId($document);

        if ($documentableProviderId !== null && $documentableProviderId === $user->provider_id) {
            return true;
        }

        if ($document->document_type->isHighlySensitive()) {
            return $user->hasPermission('documents.view_sensitive');
        }

        return $user->hasPermission('documents.view');
    }

    private function documentableProviderId(Document $document): ?int
    {
        $documentable = $document->documentable;

        return match (true) {
            $documentable instanceof Provider => $documentable->id,
            $documentable instanceof Driver => $documentable->provider_id,
            default => null,
        };
    }
}
