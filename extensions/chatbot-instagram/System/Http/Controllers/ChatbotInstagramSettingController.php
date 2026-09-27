<?php

namespace App\Extensions\ChatbotInstagram\System\Http\Controllers;

use App\Helpers\Classes\Helper;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ChatbotInstagramSettingController extends Controller
{
    /**
     * @var array<string, string>
     */
    private array $credentials = [
        'INSTAGRAM_APP_ID'       => 'Instagram App ID',
        'INSTAGRAM_APP_SECRET'   => 'Instagram App Secret',
        'INSTAGRAM_VERIFY_TOKEN' => 'Verify Token',
    ];

    public function index(): View
    {
        return view('instagram-channel::settings.index', [
            'credentials' => $this->credentials,
            'values'      => $this->currentValues(),
            'webhookUrl'  => $this->webhookUrl(),
            'redirectUrl' => $this->redirectUrl(),
            'appIsDemo'   => Helper::appIsDemo(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        if (Helper::appIsDemo()) {
            return back()->with([
                'type'    => 'error',
                'message' => trans('This feature is disabled in demo mode.'),
            ]);
        }

        $rules = [];

        foreach (array_keys($this->credentials) as $key) {
            $rules[$key] = 'required|string';
        }

        $data = $request->validate($rules);

        setting($data)->save();

        return back()->with([
            'type'    => 'success',
            'message' => __('Instagram settings updated'),
        ]);
    }

    private function currentValues(): array
    {
        $values = [];
        foreach (array_keys($this->credentials) as $key) {
            $values[$key] = setting($key);
        }

        return $values;
    }

    private function webhookUrl(): string
    {
        return url('api/v2/chatbot/webhook/instagram');
    }

    private function redirectUrl(): string
    {
        return route('chatbot.instagram.oauth.callback');
    }
}
