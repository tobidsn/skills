<?php

namespace App\Services;

use Exception;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AntiCasService
{
    private ?string $apiUrl;

    private ?string $projectId;

    private ?string $projectSecret;

    private const TOKEN_CACHE_KEY = 'anticas_access_token_new';

    private const TOKEN_CACHE_DURATION = 25;

    private const CAMPAIGN_CONFIG_CACHE_KEY = 'anticas_campaign_configuration';

    private const CAMPAIGN_CONFIG_CACHE_DURATION = 1440;

    public function __construct()
    {
        $this->apiUrl = config('services.anticas.api_url');
        $this->projectId = config('services.anticas.project_id');
        $this->projectSecret = config('services.anticas.project_secret');
    }

    /**
     * Get or generate JWT token for API authentication
     */
    public function getToken(): string
    {
        $cachedToken = Cache::get(self::TOKEN_CACHE_KEY);
        if ($cachedToken) {
            return $cachedToken;
        }

        try {
            $response = Http::withHeaders([
                'User-Agent' => request()->header('User-Agent'),
            ])->post("{$this->apiUrl}/api/jwt/token", [
                'projectId' => $this->projectId,
                'projectSecret' => $this->projectSecret,
            ]);

            if (! $response->successful()) {
                $errorMessage = $this->extractErrorMessage($response);
                Log::error('Failed to generate AntiCAS token', [
                    'status' => $response->status(),
                    'response' => $response->body(),
                ]);
                throw new Exception('Failed to generate AntiCAS token: '.$errorMessage);
            }

            $data = $response->json();
            $token = $data['data']['accessToken'] ?? null;
            $expiresIn = $data['data']['expiresIn'] ?? null;

            if (! $token) {
                throw new Exception('Invalid token response from AntiCAS API');
            }

            $cacheDuration = $expiresIn ? max(1, ($expiresIn / 60) - 10) : self::TOKEN_CACHE_DURATION;

            Cache::put(self::TOKEN_CACHE_KEY, $token, now()->addMinutes($cacheDuration));

            return $token;
        } catch (Exception $e) {
            Log::error('Exception in getToken', [
                'message' => $e->getMessage(),
            ]);
            throw new Exception('Failed to generate token');
        }
    }

    /**
     * Get campaign configuration
     */
    public function getCampaignConfiguration(): array
    {
        $cachedConfig = Cache::get(self::CAMPAIGN_CONFIG_CACHE_KEY);
        if ($cachedConfig) {
            return $cachedConfig;
        }

        try {
            $response = $this->makeAuthenticatedRequest('GET', '/api/v1/campaign/configuration');

            if (! $response->successful() || $response->status() !== 200) {
                $errorMessage = $this->extractErrorMessage($response);
                Log::error('Failed to get campaign configuration', [
                    'status' => $response->status(),
                    'response' => $errorMessage,
                ]);
                throw new Exception('Failed to get campaign configuration: '.$errorMessage);
            }

            $configData = $response->json();

            Cache::put(self::CAMPAIGN_CONFIG_CACHE_KEY, $configData, now()->addMinutes(self::CAMPAIGN_CONFIG_CACHE_DURATION));

            return $configData;
        } catch (Exception $e) {
            Log::error('Exception in getCampaignConfiguration', [
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Check member with authorization code and return contestant ID
     *
     * @return string The contestant ID
     *
     * @throws Exception when validation fails or contestant ID is invalid
     */
    public function checkMember(string $authorizationCode): string
    {
        try {
            $response = $this->makeAuthenticatedRequest('PUT', '/api/v1/campaign/check-member', [
                'authorizationCode' => $authorizationCode,
            ]);

            if (! $response->successful() || $response->status() !== 200) {
                $errorMessage = $this->extractErrorMessage($response);
                Log::warning('Failed to check member', [
                    'status' => $response->status(),
                    'response' => $errorMessage,
                ]);
                throw new Exception($this->getCustomMessage($errorMessage));
            }

            $response = $response->json();

            if (! $response['success']) {
                Log::warning('AntiCAS authorization failed', [
                    'authorizationCode' => $authorizationCode,
                    'response' => $response,
                ]);
                throw new Exception('Authorization code is invalid');
            }

            $contestantId = $response['data']['contestantId'] ?? null;
            if (empty($contestantId)) {
                Log::warning('AntiCAS returned empty contestant ID', [
                    'authorizationCode' => $authorizationCode,
                    'response' => $response,
                ]);
                throw new Exception('Contestant ID is missing in the response');
            }

            return $contestantId;
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * Deduct/Burn points from member
     */
    public function burnPoints(string $contestantId, int $loyaltyCardId, int $pointsRequested): string
    {
        try {
            $response = $this->makeAuthenticatedRequest('POST', '/api/v1/campaign/burn-points', [
                'contestantId' => $contestantId,
                'loyaltyCardId' => $loyaltyCardId,
                'pointsRequested' => $pointsRequested,
            ]);

            if (! $response->successful() || $response->status() !== 200) {
                $errorMessage = $this->extractErrorMessage($response);
                Log::warning('Failed to burn points', [
                    'contestantId' => $contestantId,
                    'loyaltyCardId' => $loyaltyCardId,
                    'pointsRequested' => $pointsRequested,
                    'status' => $response->status(),
                    'response' => $errorMessage,
                ]);
                throw new Exception($this->getCustomMessage($errorMessage));
            }

            $response = $response->json();

            if (! $response['data'] || ! is_array($response['data'])) {
                Log::error('Burn points response data is empty', [
                    'contestantId' => $contestantId,
                    'loyaltyCardId' => $loyaltyCardId,
                    'pointsRequested' => $pointsRequested,
                    'response' => $response,
                ]);
                throw new Exception('Burn points response data is empty');
            }

            $transactionId = $response['data']['transactionId'] ?? null;
            if (empty($transactionId)) {
                Log::error('Burn points response missing transaction ID', [
                    'contestantId' => $contestantId,
                    'loyaltyCardId' => $loyaltyCardId,
                    'pointsRequested' => $pointsRequested,
                    'response' => $response,
                ]);
                throw new Exception('Burn points response missing transaction ID');
            }

            return $transactionId;
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * Redeem offers for member
     */
    public function redeemOffers(string $contestantId, int $loyaltyCardId, int $rewardId): string
    {
        try {
            $response = $this->makeAuthenticatedRequest('POST', '/api/v1/campaign/redeem-offers', [
                'contestantId' => $contestantId,
                'loyaltyCardId' => $loyaltyCardId,
                'rewardId' => $rewardId,
            ]);

            if (! $response->successful() || $response->status() !== 200) {
                $errorMessage = $this->extractErrorMessage($response);
                Log::warning('Failed to redeem offers', [
                    'contestantId' => $contestantId,
                    'loyaltyCardId' => $loyaltyCardId,
                    'rewardId' => $rewardId,
                    'status' => $response->status(),
                    'response' => $errorMessage,
                ]);
                throw new Exception($this->getCustomMessage($errorMessage));
            }

            $response = $response->json();

            if (! $response['data'] || ! is_array($response['data'])) {
                Log::error('Redeem offers response data is empty', [
                    'contestantId' => $contestantId,
                    'loyaltyCardId' => $loyaltyCardId,
                    'rewardId' => $rewardId,
                    'response' => $response,
                ]);
                throw new Exception('Redeem offers response data is empty');
            }

            $transactionId = $response['data']['transactionId'] ?? null;
            if (empty($transactionId)) {
                Log::error('Redeem offers response missing transaction ID', [
                    'contestantId' => $contestantId,
                    'loyaltyCardId' => $loyaltyCardId,
                    'rewardId' => $rewardId,
                    'response' => $response,
                ]);
                throw new Exception('Redeem offers response missing transaction ID');
            }

            return $transactionId;
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * Upsert tags for member
     */
    public function upsertTags(string $contestantId, string $tag): array
    {
        try {
            $response = $this->makeAuthenticatedRequest('PUT', '/api/v1/campaign/tagvalues', [
                'contestantId' => $contestantId,
                'tag' => $tag,
            ]);

            if (! $response->successful() || $response->status() !== 200) {
                $errorMessage = $this->extractErrorMessage($response);
                Log::error('Failed to upsert tags', [
                    'contestantId' => $contestantId,
                    'tag' => $tag,
                    'status' => $response->status(),
                    'response' => $errorMessage,
                ]);
                throw new Exception('Failed to upsert tags: '.$errorMessage);
            }

            return $response->json();
        } catch (Exception $e) {
            Log::error('Exception in upsertTags', [
                'contestantId' => $contestantId,
                'tag' => $tag,
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Make authenticated request to AntiCAS API
     */
    private function makeAuthenticatedRequest(string $method, string $endpoint, array $data = [], array $additionalHeaders = []): Response
    {
        $token = $this->getToken();

        $headers = array_merge([
            'Authorization' => "Bearer {$token}",
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'User-Agent' => request()->header('User-Agent'),
        ], $additionalHeaders);

        $response = Http::withHeaders($headers)->{strtolower($method)}(
            "{$this->apiUrl}{$endpoint}",
            $data
        );

        return $response;
    }

    /**
     * Extract error message from HTTP response
     */
    private function extractErrorMessage(Response $response): string
    {
        $responseBody = $response->json();

        return $responseBody['message'] ?? $response->body();
    }

    /**
     * Extract error message from response array
     */
    private function extractErrorMessageFromArray(array $response): string
    {
        return $response['message'] ?? 'CAS error';
    }

    /**
     * Clear cached token (useful for testing or when token is invalid)
     */
    public function clearTokenCache(): void
    {
        Cache::forget(self::TOKEN_CACHE_KEY);
    }

    /**
     * Clear cached campaign configuration
     */
    public function clearCampaignConfigCache(): void
    {
        Cache::forget(self::CAMPAIGN_CONFIG_CACHE_KEY);
    }

    /**
     * Check if service is properly configured
     */
    public function isConfigured(): bool
    {
        return ! empty($this->apiUrl) && ! empty($this->projectId) && ! empty($this->projectSecret);
    }

    /**
     * Make custom message based on type of error
     */
    public function getCustomMessage(string $errorMessage): string
    {
        // example error message : Invalid offer loyalty card id [invalid_offer_card]
        preg_match('/\[([^\]]+)\]/', $errorMessage, $matches);
        $type = $matches[1] ?? null;

        return match ($type) {
            'reward_not_found' => 'Maaf, Penawaran Spesial yang kamu dapatkan belum aktif. Coba lagi beberapa saat ya.',
            'resource_not_found' => 'Maaf, Penawaran Spesial yang kamu dapatkan belum aktif. Coba lagi beberapa saat ya.',
            'not_enough_points' => 'Ups, poin MyM Rewards kamu belum cukup nih. Yuk, jajan terus di Mekdi untuk tambah poin kamu!',
            'invalid_loyalty_card' => 'Maaf, Penawaran Spesial yang kamu dapatkan belum aktif. Coba lagi beberapa saat ya.',
            'invalid_jwt_format' => 'Ups, sistem sedang bermasalah. Silakan ulangi beberapa saat lagi.',
            'invalid_offer_card' => 'Maaf, Penawaran Spesial yang kamu dapatkan belum aktif. Coba lagi beberapa saat ya.',
            'offer_expired' => 'Maaf, Penawaran Spesial yang kamu dapatkan sudah kadaluwarsa.',
            'authorization_code_expired' => 'Kode autorisasi sudah kedaluwarsa. Coba lagi beberapa saat lagi.',
            default => $errorMessage,
        };
    }
}
