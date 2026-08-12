<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Compliance\Actions\RejectDocumentAction;
use App\Domain\Compliance\Actions\VerifyDocumentAction;
use App\Domain\Compliance\Models\Document;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\ReasonRequest;
use App\Http\Resources\Api\V1\DocumentResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentVerificationController extends Controller
{
    public function verify(Request $request, Document $document, VerifyDocumentAction $action): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        return ApiResponse::success(['document' => new DocumentResource($action->handle($document, $actor))]);
    }

    public function reject(ReasonRequest $request, Document $document, RejectDocumentAction $action): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $document = $action->handle($document, $actor, $request->string('reason')->toString());

        return ApiResponse::success(['document' => new DocumentResource($document)]);
    }
}
