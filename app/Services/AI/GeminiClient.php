<?php

declare(strict_types=1);

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Throwable;

class GeminiClient
{
    public function __construct(
        protected ?string $apiKey = null,
        protected string $model = 'gemini-1.5-flash'
    ) {
        $this->apiKey = $apiKey ?? (is_string(config('services.gemini.api_key')) ? config('services.gemini.api_key') : null);
        $configuredModel = config('services.gemini.model');
        $this->model = is_string($configuredModel) ? $configuredModel : 'gemini-1.5-flash';
    }

    /**
     * Generate content via Google Gemini API.
     */
    public function generateContent(string $prompt): ?string
    {
        if ($this->apiKey === null || $this->apiKey === '') {
            return null;
        }

        $url = sprintf(
            'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent?key=%s',
            $this->model,
            $this->apiKey
        );

        try {
            $response = Http::timeout(15)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                ])
                ->post($url, [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $prompt],
                            ],
                        ],
                    ],
                    'generationConfig' => [
                        'temperature' => 0.2,
                        'maxOutputTokens' => 1024,
                    ],
                ]);

            if ($response->successful()) {
                /** @var array<string, mixed> $data */
                $data = $response->json();

                /** @var array<int, array{content?: array{parts?: array<int, array{text?: string}>}}>|null $candidates */
                $candidates = $data['candidates'] ?? null;

                if (is_array($candidates) && isset($candidates[0]['content']['parts'][0]['text'])) {
                    return trim((string) $candidates[0]['content']['parts'][0]['text']);
                }
            }

            return null;
        } catch (Throwable) {
            return null;
        }
    }
}
