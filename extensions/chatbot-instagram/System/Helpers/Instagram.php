<?php

namespace App\Extensions\ChatbotInstagram\System\Helpers;

use App\Extensions\ChatbotInstagram\System\Helpers\Contracts\BaseMetaHelper;
use Exception;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Instagram extends BaseMetaHelper
{
    protected ?string $accessToken = null;

    public function __construct(?array $config = null, ?string $accessToken = null)
    {
        $instagramConfig = config('chatbot-instagram.instagram', []);

        $instagramConfig = array_merge($instagramConfig, [
            'app_id'     => setting('INSTAGRAM_APP_ID'),
            'app_secret' => setting('INSTAGRAM_APP_SECRET'),
        ]);

        $instagramConfig['redirect_uri'] = secure_url($instagramConfig['redirect_uri'] ?? '/chatbot/instagram/oauth/callback');

        $this->config = $config ?? $instagramConfig;
        $this->accessToken = $accessToken;
    }

    private function apiClient(): PendingRequest
    {
        return Http::withToken($this->accessToken)
            ->baseUrl($this->config['api_url'])
            ->retry(1, 3000);
    }

    public static function authRedirect(array $scopes = []): RedirectResponse
    {
        $instagram = new self;

        if (! empty($scopes)) {
            $instagram->config['scopes'] = $scopes;
        }

        $authUri = $instagram->apiUrl('dialog/oauth', [
            'response_type' => 'code',
            'client_id'     => $instagram->config['app_id'],
            'redirect_uri'  => $instagram->config['redirect_uri'],
            'scope'         => collect($instagram->config['scopes'] ?? [])->join(','),
        ], true);

        return redirect($authUri);
    }

    public function refreshAccessToken(): Response
    {
        $apiUrl = $this->apiUrl('/oauth/access_token', [
            'client_id'         => $this->config['app_id'],
            'client_secret'     => $this->config['app_secret'],
            'grant_type'        => 'fb_exchange_token',
            'fb_exchange_token' => $this->accessToken,
        ]);

        return Http::post($apiUrl);
    }

    public function getAccountInfo(?array $fields = null): Response
    {
        $apiUrl = $this->apiUrl('/me/accounts', [
            'access_token' => $this->accessToken,
            'fields'       => collect($fields)->join(','),
        ]);

        return Http::get($apiUrl);
    }

    public function getInstagramInfo(string $igId, ?array $fields = null): Response
    {
        $apiUrl = $this->apiUrl('/' . $igId);

        return Http::withToken($this->accessToken)->get($apiUrl, [
            'fields' => collect($fields)->join(','),
        ]);
    }

    public function publishSingleMediaPost(string $igId, array $postData): Response
    {
        $apiUrl = $this->apiUrl("$igId/media");

        $uploadMediaRes = Http::withToken($this->accessToken)
            ->retry(3, 3000)
            ->post($apiUrl, $postData)->throw();

        $mediaId = $uploadMediaRes->json('id');

        $uploadStatus = $this->checkUploadStatus($mediaId);

        throw_if(! $uploadStatus['is_ready'], new Exception($uploadStatus['status']));

        return $this->publishContainer($igId, $uploadMediaRes->json('id'));
    }

    public function publishCarouselPost(string $igId, array $files, string $mediaType = 'image', string $caption = ''): Response
    {
        $containerIds = [];

        foreach ($files as $fileUrl) {
            $containerData = [
                'is_carousel_item' => true,
            ];

            if ($mediaType === 'image') {
                $containerData['media_type'] = 'IMAGE';
                $containerData['image_url'] = $fileUrl;
            } elseif ($mediaType === 'video') {
                $containerData['media_type'] = 'VIDEO';
                $containerData['video_url'] = $fileUrl;
            }

            $apiUrl = $this->apiUrl($igId . '/media');
            $containerRes = Http::withToken($this->accessToken)
                ->asForm()
                ->acceptJson()
                ->post($apiUrl, $containerData)
                ->throw();

            $containerIds[] = $containerRes->json('id');
        }

        $publishCarouselContainerRes = Http::withToken($this->accessToken)
            ->retry(3, 3000)
            ->post($apiUrl, [
                'media_type' => 'CAROUSEL',
                'children'   => $containerIds,
                'caption'    => $caption,
            ]);

        return $this->publishContainer($igId, $publishCarouselContainerRes->json('id'));
    }

    protected function publishContainer(string $igId, string $creationId)
    {
        $apiUrl = $this->apiUrl($igId . '/media_publish');

        return Http::retry(3, 3000)
            ->withToken($this->accessToken)
            ->post($apiUrl, [
                'creation_id' => $creationId,
            ]);
    }

    private function checkUploadStatus(string $mediaId, int $delayInSeconds = 3, int $maxAttempts = 10): array
    {
        $status = false;
        $attempted = 0;
        $isFinished = false;
        $videoStatus = null;

        while (! $isFinished && $attempted < $maxAttempts) {
            Log::info("Checking Instagram media upload for: {$mediaId}");
            $videoStatus = $this->apiClient()->get($this->apiUrl($mediaId, ['fields' => 'status_code,status']))->throw();
            Log::info("Upload status {$status} attempt {$attempted}/{$maxAttempts}");

            $status = $videoStatus->json('status_code');
            $isFinished = in_array(strtolower((string) $status), ['finished', 'ok', 'completed', 'ready'], true);

            if ($isFinished) {
                break;
            }

            $isError = in_array(strtolower((string) $status), ['error', 'failed'], true);
            if ($isError) {
                break;
            }

            $attempted++;
            sleep($delayInSeconds);
        }

        return [
            'is_ready'    => $isFinished,
            'status_code' => $status,
            'status'      => optional($videoStatus)->json('status'),
        ];
    }

    public function getPostAnalytics(string $postId, array $fields = []): Response
    {
        return Http::withToken($this->accessToken)
            ->get($this->apiUrl($postId, [
                'fields' => collect($fields)->join(','),
            ]));
    }
}
