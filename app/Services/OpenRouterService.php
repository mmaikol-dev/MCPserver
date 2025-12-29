<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenRouterService
{
    protected string $apiKey = '';

    protected string $model = 'openai/gpt-oss-20b:free';

    protected string $endpoint = 'https://openrouter.ai/api/v1/chat/completions';

    public function __construct()
    {
        // Using hardcoded API key for testing (same pattern as GeminiService)
    }

    /**
     * Send a chat message with MCP tool support
     */
    public function chat(string $message, array $tools = [], array $history = []): array
    {
        // Convert Gemini-style history -> OpenAI style
        $messages = $this->buildMessages($history, $message);

        $payload = [
            'model' => $this->model,
            'messages' => $messages,
            'temperature' => 0.7,
            'top_p' => 0.95,
            'max_tokens' => 8192,
        ];

        // Add tools if provided
        if (! empty($tools)) {
            $payload['tools'] = $this->wrapTools($tools);
            $payload['tool_choice'] = 'auto';
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$this->apiKey,
                'Content-Type' => 'application/json',
                'HTTP-Referer' => config('app.url'),
                'X-Title' => config('app.name'),
            ])
                ->timeout(30)
                ->post($this->endpoint, $payload);

            if (! $response->successful()) {
                Log::error('OpenRouter API error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                throw new \Exception('OpenRouter API request failed: '.$response->body());
            }

            $data = $response->json();

            return [
                'response' => $data,
                'text' => $this->extractText($data),
                'functionCalls' => $this->extractFunctionCalls($data),
            ];

        } catch (\Exception $e) {
            Log::error('OpenRouter chat error', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Convert Gemini-style history to OpenAI messages
     */
    protected function buildMessages(array $history, string $message): array
    {
        $messages = [];

        foreach ($history as $item) {
            $role = $item['role'] === 'model' ? 'assistant' : $item['role'];

            foreach ($item['parts'] as $part) {
                if (isset($part['text'])) {
                    $messages[] = [
                        'role' => $role,
                        'content' => $part['text'],
                    ];
                }
            }
        }

        $messages[] = [
            'role' => 'user',
            'content' => $message,
        ];

        return $messages;
    }

    /**
     * Wrap Gemini functionDeclarations into OpenAI tool format
     */
    protected function wrapTools(array $tools): array
    {
        return array_map(function ($tool) {
            return [
                'type' => 'function',
                'function' => [
                    'name' => $tool['name'],
                    'description' => $tool['description'] ?? '',
                    'parameters' => $tool['parameters'] ?? [
                        'type' => 'object',
                        'properties' => [],
                    ],
                ],
            ];
        }, $tools);
    }

    /**
     * Extract text from OpenRouter response
     */
    protected function extractText(array $response): ?string
    {
        return $response['choices'][0]['message']['content'] ?? null;
    }

    /**
     * Extract function calls from OpenRouter response
     *
     * Returns Gemini-style:
     * [
     *   [
     *     'name' => 'create_order',
     *     'args' => [...]
     *   ]
     * ]
     */
    protected function extractFunctionCalls(array $response): array
    {
        $message = $response['choices'][0]['message'] ?? [];

        if (! isset($message['tool_calls'])) {
            return [];
        }

        $calls = [];

        foreach ($message['tool_calls'] as $toolCall) {
            $calls[] = [
                'name' => $toolCall['function']['name'],
                'args' => json_decode($toolCall['function']['arguments'], true) ?? [],
            ];
        }

        return $calls;
    }
}
