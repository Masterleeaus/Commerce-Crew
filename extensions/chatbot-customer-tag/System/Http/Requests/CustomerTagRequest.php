<?php

namespace App\Extensions\ChatbotCustomerTag\System\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CustomerTagRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'tag'              => ['required', 'string', 'max:255'],
            'tag_color'        => ['nullable', 'string', 'max:50'],
            'background_color' => ['nullable', 'string', 'max:50'],
        ];
    }
}
