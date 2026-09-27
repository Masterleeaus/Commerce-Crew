<?php

namespace App\Extensions\ChatbotVoice\System\Http\Requests\Train;

use App\Extensions\ChatbotVoice\System\Enums\TrainTypeEnum;
use App\Extensions\ChatbotVoice\System\Models\ExtVoiceChatbot;
use Illuminate\Foundation\Http\FormRequest;

class DataRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'id'         => 'required|exists:' . (new ExtVoiceChatbot)->getTable() . ',id',
            'type'       => ['sometimes', 'nullable', 'in:' . implode(',', TrainTypeEnum::toArray())],
        ];
    }
}
