<?php

namespace App\Http\Controllers\Ai;

use App\Http\Controllers\Controller;
use App\Mcp\Tools\CreateOrderTool;
use App\Services\GeminiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Laravel\Mcp\Request as McpRequest;

class OrderChatController extends Controller
{
    public function index()
    {
        return Inertia::render('chats/index');
    }

    public function chat(Request $request, GeminiService $gemini)
    {
        Log::info('Chat endpoint hit', $request->all());

        $validated = $request->validate([
            'message' => 'required|string',
            'history' => 'array|nullable',
        ]);

        $message = $validated['message'];
        $history = $validated['history'] ?? [];

        // Get MCP tools schema for Gemini
        $tools = $this->getMcpToolsSchema();

        // First call to Gemini with tools
        $response = $gemini->chat(
            $this->buildOrderPrompt($message),
            $tools,
            $history
        );

        $functionCalls = $response['functionCalls'];

        // If Gemini wants to call a function, execute it
        if (! empty($functionCalls)) {
            $toolResults = [];

            foreach ($functionCalls as $call) {
                $result = $this->executeMcpTool($call);
                $toolResults[] = $result;
            }

            // Send tool results back to Gemini for final response
            $finalHistory = array_merge($history, [
                ['role' => 'user', 'parts' => [['text' => $message]]],
                ['role' => 'model', 'parts' => array_map(fn ($call) => ['functionCall' => $call], $functionCalls)],
                ['role' => 'function', 'parts' => $toolResults],
            ]);

            $finalResponse = $gemini->chat('Continue', [], $finalHistory);

            return response()->json([
                'reply' => $finalResponse['text'],
                'toolResults' => $toolResults,
                'history' => $finalHistory,
            ]);
        }

        // No function call, just return the text response
        return response()->json([
            'reply' => $response['text'],
            'history' => array_merge($history, [
                ['role' => 'user', 'parts' => [['text' => $message]]],
                ['role' => 'model', 'parts' => [['text' => $response['text']]]],
            ]),
        ]);
    }

    /**
     * Get MCP tools in Gemini function calling format
     */
    protected function getMcpToolsSchema(): array
    {
        return [
            [
                'name' => 'create_order',
                'description' => 'Create a new order in the logistics system. Use this to register customer orders with product details, delivery information, and merchant details.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'order_date' => [
                            'type' => 'string',
                            'description' => 'Order date in YYYY-MM-DD format',
                        ],
                        'order_no' => [
                            'type' => 'string',
                            'description' => 'Unique order reference number',
                        ],
                        'amount' => [
                            'type' => 'number',
                            'description' => 'Total order amount',
                        ],
                        'client_name' => ['type' => 'string'],
                        'address' => ['type' => 'string'],
                        'phone' => ['type' => 'string'],
                        'alt_no' => ['type' => 'string'],
                        'country' => ['type' => 'string', 'default' => 'Kenya'],
                        'city' => ['type' => 'string'],
                        'product_name' => ['type' => 'string'],
                        'quantity' => ['type' => 'integer', 'minimum' => 1],
                        'status' => ['type' => 'string', 'default' => 'Pending'],
                        'agent' => ['type' => 'string'],
                        'delivery_date' => ['type' => 'string', 'description' => 'Delivery date in YYYY-MM-DD format'],
                        'instructions' => ['type' => 'string'],
                        'cc_email' => ['type' => 'string'],
                        'merchant' => ['type' => 'string'],
                        'order_type' => ['type' => 'string'],
                        'sheet_id' => ['type' => 'string'],
                        'sheet_name' => ['type' => 'string'],
                        'store_name' => ['type' => 'string'],
                        'code' => ['type' => 'string'],
                    ],
                    'required' => [
                        'order_date', 'order_no', 'amount', 'client_name',
                        'phone', 'address', 'city', 'product_name', 'quantity',
                        'merchant', 'order_type',
                    ],
                ],
            ],
        ];
    }

    /**
     * Execute MCP tool based on Gemini's function call
     */
    protected function executeMcpTool(array $functionCall): array
    {
        $name = $functionCall['name'];
        $args = $functionCall['args'] ?? [];

        try {
            if ($name === 'create_order') {
                $tool = new CreateOrderTool;
                $mcpRequest = new McpRequest($args);
                $response = $tool->handle($mcpRequest);

                return [
                    'functionResponse' => [
                        'name' => $name,
                        'response' => $response->toArray(),
                    ],
                ];
            }

            throw new \Exception("Unknown tool: {$name}");
        } catch (\Exception $e) {
            Log::error('MCP tool execution error', [
                'tool' => $name,
                'error' => $e->getMessage(),
            ]);

            return [
                'functionResponse' => [
                    'name' => $name,
                    'response' => [
                        'error' => $e->getMessage(),
                    ],
                ],
            ];
        }
    }

    protected function buildOrderPrompt(string $userMessage): string
    {
        return <<<PROMPT
You are an AI assistant for order management.

When the user wants to create an order:
1. Extract all available information from their message
2. If required fields are missing, ask concise follow-up questions
3. When you have all required fields, call the create_order function

Required fields: order_date, order_no, amount, client_name, phone, address, city, product_name, quantity, merchant, order_type

User message: {$userMessage}
PROMPT;
    }
}
