<?php

namespace App\Extensions\ChatbotVoice\System\Http\Requests\Train;

use App\Extensions\ChatbotVoice\System\Models\ExtVoiceChatbot;
use App\Extensions\ChatbotVoice\System\Models\ExtVoicechatbotTrain;
use Illuminate\Foundation\Http\FormRequest;

class TrainRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'id'     => 'required|exists:' . (new ExtVoiceChatbot)->getTable() . ',id',
            'data'   => 'required|array',
            'data.*' => 'required|exists:' . (new ExtVoicechatbotTrain)->getTable() . ',id',
        ];
    }
}
