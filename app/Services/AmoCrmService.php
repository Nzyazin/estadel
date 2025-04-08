<?php

namespace App\Services;

use App\Models\AmoCrmToken;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class AmoCrmService
{
    /**
     * Базовый URL для API AMO CRM
     */
    protected string $baseUrl;

    /**
     * ID интеграции
     */
    protected string $clientId;

    /**
     * Секретный ключ интеграции
     */
    protected string $clientSecret;

    /**
     * URL для редиректа после авторизации
     */
    protected string $redirectUri;

    /**
     * Токен доступа
     */
    protected ?AmoCrmToken $token = null;

    /**
     * Конструктор сервиса
     */
    public function __construct()
    {
        $this->clientId = config('services.amocrm.client_id');
        $this->clientSecret = config('services.amocrm.client_secret');
        $this->redirectUri = config('services.amocrm.redirect_uri');
    }

    /**
     * Получение URL для авторизации
     *
     * @param string $baseDomain Домен аккаунта AMO CRM
     * @return string
     */
    public function getAuthUrl(string $baseDomain): string
    {
        $this->baseUrl = "https://{$baseDomain}";
        
        $params = [
            'client_id' => $this->clientId,
            'mode' => 'post_message',
            'redirect_uri' => $this->redirectUri,
            'response_type' => 'code',
            'state' => 'state',
        ];

        return "{$this->baseUrl}/oauth?" . http_build_query($params);
    }

    /**
     * Получение токена доступа по коду авторизации
     *
     * @param string $code Код авторизации
     * @param string $baseDomain Домен аккаунта AMO CRM
     * @return AmoCrmToken
     */
    public function getAccessToken(string $code, string $baseDomain): AmoCrmToken
    {
        $this->baseUrl = "https://{$baseDomain}";
        
        $response = Http::post("{$this->baseUrl}/oauth2/access_token", [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->redirectUri,
        ]);

        if ($response->failed()) {
            Log::error('AMO CRM API Error: ' . $response->body());
            throw new \Exception('Ошибка получения токена доступа: ' . $response->body());
        }

        $data = $response->json();

        $token = AmoCrmToken::updateOrCreate(
            ['base_domain' => $baseDomain],
            [
                'access_token' => $data['access_token'],
                'refresh_token' => $data['refresh_token'],
                'expires_at' => now()->addSeconds($data['expires_in']),
            ]
        );

        $this->token = $token;

        return $token;
    }

    /**
     * Обновление токена доступа
     *
     * @param AmoCrmToken $token Токен для обновления
     * @return AmoCrmToken
     */
    public function refreshToken(AmoCrmToken $token): AmoCrmToken
    {
        $this->baseUrl = "https://{$token->base_domain}";
        
        $response = Http::post("{$this->baseUrl}/oauth2/access_token", [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'grant_type' => 'refresh_token',
            'refresh_token' => $token->refresh_token,
            'redirect_uri' => $this->redirectUri,
        ]);

        if ($response->failed()) {
            Log::error('AMO CRM API Error: ' . $response->body());
            throw new \Exception('Ошибка обновления токена доступа: ' . $response->body());
        }

        $data = $response->json();

        $token->update([
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'],
            'expires_at' => now()->addSeconds($data['expires_in']),
        ]);

        $this->token = $token;

        return $token;
    }

    /**
     * Получение списка лидов
     *
     * @param array $params Параметры запроса
     * @return array
     */
    public function getLeads(array $params = []): array
    {
        $token = $this->getToken();
        $this->baseUrl = "https://{$token->base_domain}";

        $defaultParams = [
            'limit' => 25,
            'page' => 1,
        ];

        $queryParams = array_merge($defaultParams, $params);

        $response = Http::withToken($token->access_token)
            ->get("{$this->baseUrl}/api/v4/leads", $queryParams);

        if ($response->failed()) {
            Log::error('AMO CRM API Error: ' . $response->body());
            throw new \Exception('Ошибка получения списка лидов: ' . $response->body());
        }

        return $response->json();
    }

    /**
     * Получение лида по ID
     *
     * @param int $id ID лида
     * @return array
     */
    public function getLead(int $id): array
    {
        $token = $this->getToken();
        $this->baseUrl = "https://{$token->base_domain}";

        $response = Http::withToken($token->access_token)
            ->get("{$this->baseUrl}/api/v4/leads/{$id}");

        if ($response->failed()) {
            Log::error('AMO CRM API Error: ' . $response->body());
            throw new \Exception('Ошибка получения лида: ' . $response->body());
        }

        return $response->json();
    }

    /**
     * Получение токена доступа
     *
     * @return AmoCrmToken
     */
    protected function getToken(): AmoCrmToken
    {
        if ($this->token) {
            return $this->token;
        }

        $token = AmoCrmToken::first();

        if (!$token) {
            throw new \Exception('Токен доступа не найден. Необходимо авторизоваться в AMO CRM.');
        }

        if ($token->isExpired()) {
            $token = $this->refreshToken($token);
        }

        $this->token = $token;

        return $token;
    }
}
