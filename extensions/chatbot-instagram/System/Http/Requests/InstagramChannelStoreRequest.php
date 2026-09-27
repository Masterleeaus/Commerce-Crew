<?php

namespace App\Extensions\ChatbotInstagram\System\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InstagramChannelStoreRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'channel'     => 'required|string',
            'user_id'     => 'required',
            'chatbot_id'  => 'required',
            'credentials' => 'required|array',
            'connected_at'=> 'string',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'user_id'      => auth()->id(),
            'connected_at' => (string) now(),
            'channel'      => 'instagram',
        ]);
    }
}
