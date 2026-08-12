<?php

namespace App\Http\Controllers\Api\V1\Providers;

use App\Domain\Compliance\Actions\UploadDocumentAction;
use App\Domain\Compliance\Enums\DocumentType;
use App\Http\Controllers\Concerns\ResolvesProvider;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Providers\UploadDocumentRequest;
use App\Http\Resources\Api\V1\DocumentResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProviderDocumentController extends Controller
{
    use ResolvesProvider;

    public function index(Request $request): JsonResponse
    {
        $provider = $this->resolveProvider($request);

        return ApiResponse::success([
            'documents' => DocumentResource::collection($provider->documents()->get()),
        ]);
    }

    public function store(UploadDocumentRequest $request, UploadDocumentAction $action): JsonResponse
    {
        $provider = $this->resolveProvider($request);

        $document = $action->handle(
            documentable: $provider,
            file: $request->file('file'),
            documentType: DocumentType::from($request->string('document_type')->toString()),
            uploadedBy: $request->user(),
            documentNumber: $request->string('document_number')->toString() ?: null,
            issuedAt: $request->input('issued_at'),
            expiresAt: $request->input('expires_at'),
        );

        return ApiResponse::success(['document' => new DocumentResource($document)], status: 201);
    }
}
