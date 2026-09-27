<?php

namespace App\Extensions\ChatbotInstagram\System\Helpers\Contracts;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

abstract class BaseMetaHelper
{
    protected array $config = [];

    public function getAccessToken(string $code): Response
    {
        $redirectUri = $this->apiUrl('/oauth/access_token', [
            'code'          => $code,
            'client_id'     => $this->config['app_id'],
            'client_secret' => $this->config['app_secret'],
            'redirect_uri'  => $this->config['redirect_uri'],
        ]);

        return Http::post($redirectUri);
    }

    protected function apiUrl(string $endpoint, array $params = [], bool $useBaseUrl = false): string
    {
        $base = $useBaseUrl ? $this->config['base_url'] : $this->config['api_url'];

        if (str_starts_with($endpoint, '/')) {
            $endpoint = substr($endpoint, 1);
        }

        $version = $this->config['api_version'] ?? '';
        $url = rtrim($base, '/') . '/' . ($version ? ($version . '/') : '') . $endpoint;

        if ($params) {
            $url .= '?' . http_build_query($params);
        }

        return $url;
    }

    public function setToken(string $bearerToken): self
    {
        $this->accessToken = $bearerToken;

        return $this;
    }
}
