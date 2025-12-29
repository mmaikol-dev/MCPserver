<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    protected string $apiKey = 'AIzaSyC8Tk-cx6wD_GlcCCSEfatsGhatzxXeiIQ';

    protected string $model = 'gemini-2.5-flash'; // or gemini-1.5-pro

    public function __construct()
    {
        // Using hardcoded API key for testing
    }

    /**
     * Send a chat message with MCP tool support
     */
    public function chat(string $message, array $tools = [], array $history = []): array
    {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent";

        $payload = [
            'contents' => array_merge($history, [
                ['role' => 'user', 'parts' => [['text' => $message]]],
            ]),
            'generationConfig' => [
                'temperature' => 0.7,
                'topP' => 0.95,
                'maxOutputTokens' => 8192,
            ],
        ];

        // Add tools if provided
        if (! empty($tools)) {
            $payload['tools'] = [[
                'functionDeclarations' => $tools,
            ]];
        }

        try {
            $response = Http::withOptions([
                'curl' => [
                    CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
                ],
            ])
                ->withHeaders([
                    'Content-Type' => 'application/json',
                ])
                ->timeout(30)
                ->post($url.'?key='.$this->apiKey, $payload);

            if (! $response->successful()) {
                Log::error('Gemini API error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                throw new \Exception('Gemini API request failed: '.$response->body());
            }

            $data = $response->json();

            return [
                'response' => $data,
                'text' => $this->extractText($data),
                'functionCalls' => $this->extractFunctionCalls($data),
            ];

        } catch (\Exception $e) {
            Log::error('Gemini chat error', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Extract text from Gemini response
     */
    protected function extractText(array $response): ?string
    {
        $candidates = $response['candidates'] ?? [];
        if (empty($candidates)) {
            return null;
        }

        $parts = $candidates[0]['content']['parts'] ?? [];
        foreach ($parts as $part) {
            if (isset($part['text'])) {
                return $part['text'];
            }
        }

        return null;
    }

    /**
     * Extract function calls from Gemini response
     */
    protected function extractFunctionCalls(array $response): array
    {
        $candidates = $response['candidates'] ?? [];
        if (empty($candidates)) {
            return [];
        }

        $parts = $candidates[0]['content']['parts'] ?? [];
        $functionCalls = [];

        foreach ($parts as $part) {
            if (isset($part['functionCall'])) {
                $functionCalls[] = $part['functionCall'];
            }
        }

        return $functionCalls;
    }
}
