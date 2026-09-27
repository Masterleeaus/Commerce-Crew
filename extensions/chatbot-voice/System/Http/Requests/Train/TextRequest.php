<?php

namespace App\Extensions\ChatbotVoice\System\Http\Requests\Train;

use App\Extensions\ChatbotVoice\System\Models\ExtVoiceChatbot;
use Illuminate\Foundation\Http\FormRequest;

class TextRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'id'      => 'required|exists:' . (new ExtVoiceChatbot)->getTable() . ',id',
            'title'   => ['required', 'string'],
            'content' => ['required', 'string'],
        ];
    }
}
