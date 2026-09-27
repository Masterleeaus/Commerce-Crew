<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PutCartLineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'variant_id' => ['required', 'integer', 'min:1'],
            'quantity' => ['required', 'integer', 'min:0', 'max:999'],
            'customisation' => ['sometimes', 'array'],
            'metadata' => ['sometimes', 'array'],
            'idempotency_key' => ['sometimes', 'nullable', 'string', 'max:191'],
        ];
    }
}
