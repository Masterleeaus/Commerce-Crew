<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MergeCartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'source_session_id' => ['required', 'string', 'max:191', 'different:session_id'],
            'source_recovery_token' => ['required', 'string', 'min:32', 'max:255'],
            'idempotency_key' => ['sometimes', 'nullable', 'string', 'max:191'],
        ];
    }
}
