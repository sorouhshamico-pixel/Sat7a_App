<?php

namespace App\Http\Requests\Api\V1\Providers;

use App\Domain\Compliance\Enums\DocumentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * File validation checks the actual content type (via PHP's fileinfo, not
 * the client-supplied extension), caps size, and rejects filenames with a
 * double extension (e.g. "license.pdf.php") — see docs/SECURITY.md
 * §File uploads.
 */
class UploadDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'document_type' => ['required', 'string', Rule::in(array_map(fn (DocumentType $t) => $t->value, DocumentType::cases()))],
            'document_number' => ['nullable', 'string', 'max:100'],
            'issued_at' => ['nullable', 'date', 'before_or_equal:today'],
            'expires_at' => ['nullable', 'date', 'after:today'],
            'file' => [
                'required',
                'file',
                'mimes:pdf,jpg,jpeg,png',
                'max:10240',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $originalName = $value->getClientOriginalName();
                    $segments = explode('.', $originalName);

                    if (count($segments) > 2) {
                        $fail('The file name must not contain more than one extension.');
                    }
                },
            ],
        ];
    }
}
