<?php

namespace App\Extensions\ChatbotVoice\System\Http\Requests\Train;

use App\Extensions\ChatbotVoice\System\Models\ExtVoiceChatbot;
use Illuminate\Foundation\Http\FormRequest;

class FileRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'id'     => 'required|exists:' . (new ExtVoiceChatbot)->getTable() . ',id',
            'file'   => 'required|file',
        ];
    }
}
