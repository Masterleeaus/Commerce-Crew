<?php

namespace App\Extensions\ChatbotInstagram\System\Http\Controllers\Oauth;

use App\Extensions\ChatbotInstagram\System\Helpers\Instagram;
use App\Helpers\Classes\Helper;
use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class InstagramController extends Controller
{
    public function redirect(Request $request): RedirectResponse
    {
        if (Helper::appIsDemo()) {
            return back()->with([
                'type'    => 'error',
                'message' => trans('Bu özellik demo modunda kapalı.'),
            ]);
        }

        if (! setting('INSTAGRAM_APP_ID') || ! setting('INSTAGRAM_APP_SECRET')) {
            return back()->with([
                'type'    => 'error',
                'message' => trans('Önce Meta uygulama bilgilerini ayarlardan kaydetmelisiniz.'),
            ]);
        }

        return Instagram::authRedirect(config('chatbot-instagram.instagram.scopes', []));
    }

    public function callback(Request $request): View|RedirectResponse
    {
        $code = $request->get('code');

        if (! $code) {
            return redirect()->route('dashboard.chatbot-multi-channel.index')
                ->with([
                    'type'    => 'error',
                    'message' => trans('Instagram bağlantısı tamamlanamadı. Lütfen yeniden deneyin.'),
                ]);
        }

        $instagram = new Instagram;

        try {
            $token = $instagram->getAccessToken($code)->throw()->json('access_token');
            $instagram->setToken($token);

            $page = $instagram->getAccountInfo(['connected_instagram_account,name,access_token'])
                ->throw()
                ->json('data.0');

            if (! isset($page['connected_instagram_account']['id'])) {
                throw new Exception('Instagram hesabı bulunamadı.');
            }

            $igAccount = $instagram
                ->getInstagramInfo(
                    $page['connected_instagram_account']['id'],
                    ['id,name,username,profile_picture_url']
                )
                ->throw()
                ->json();
        } catch (Exception $exception) {
            Log::error('Instagram OAuth hatası: ' . $exception->getMessage());

            return redirect()->route('dashboard.chatbot-multi-channel.index')
                ->with([
                    'type'    => 'error',
                    'message' => trans('Instagram bağlantısı sırasında bir hata oluştu.'),
                ]);
        }

        $credentials = [
            'page_id'       => data_get($page, 'id'),
            'page_name'     => data_get($page, 'name'),
            'instagram_id'  => data_get($igAccount, 'id'),
            'username'      => data_get($igAccount, 'username'),
            'name'          => data_get($igAccount, 'name'),
            'picture'       => data_get($igAccount, 'profile_picture_url'),
            'access_token'  => $token,
        ];

        return view('instagram-channel::oauth.success', [
            'credentials' => $credentials,
        ]);
    }

    public function webhook(Request $request)
    {
        $verifyToken = setting('INSTAGRAM_VERIFY_TOKEN', 'chatbot-instagram');

        if ($request->get('hub_mode') === 'subscribe' && $request->get('hub_verify_token') === $verifyToken) {
            return response($request->get('hub_challenge'), 200);
        }

        return response('Token invalid', 403);
    }
}
