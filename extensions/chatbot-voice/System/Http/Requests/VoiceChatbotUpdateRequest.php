<?php

namespace App\Extensions\ChatbotVoice\System\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class VoiceChatbotUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'id'				            => ['required', 'integer', 'exists:ext_voice_chatbots,id'],
            'user_id' 			       => ['required', 'integer', 'exists:users,id'],
            'uuid' 				         => ['required', 'string'],
            'title' 			         => ['required', 'string'],
            'bubble_message' 	  => ['required', 'string'],
            'welcome_message' 	 => ['required', 'string'],
            'instructions' 		   => ['required', 'string'],
            'language' 			      => ['sometimes', 'nullable', 'string'],
            'active'            => ['sometimes', 'boolean'],

            'voice_id'			=> ['required', 'string'],
            'avatar'			  => ['sometimes', 'nullable', 'string'],
            'position'			=> ['required', 'string'],
        ];
    }
}
