<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotVoiceCall\System\Http\Controllers;

use App\Helpers\Classes\Helper;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class VoiceCallSettingController extends Controller
{
    public function index(Request $request): RedirectResponse|View
    {
        if (Helper::appIsDemo()) {
            return to_route('dashboard.user.index')->with([
                'status'  => 'error',
                'message' => trans('This feature is disabled in demo mode.'),
            ]);
        }

        return view('chatbot-voice-call::settings');
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'voice_call_provider'      => ['nullable', 'string', 'in:openai_realtime,elevenlabs'],
            'voice_call_voice_id'      => ['nullable', 'string'],
        ]);

        setting($data)->save();

        return back()->with([
            'type'    => 'success',
            'message' => trans('Settings updated successfully.'),
        ]);
    }
}
