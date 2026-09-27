<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RecoverCartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'recovery_token' => ['required', 'string', 'min:32', 'max:255'],
        ];
    }
}
