<?php

namespace App\Http\Controllers\Ai;

use App\Http\Controllers\Controller;
use App\Mcp\Tools\CreateOrderTool;
use App\Mcp\Tools\UpdateOrderTool;
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

        try {
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

        } catch (\Exception $e) {
            Log::error('Chat error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'reply' => 'Sorry, I encountered an error: '.$e->getMessage(),
                'history' => $history,
            ], 500);
        }
    }

    /**
     * Get MCP tools in Gemini function calling format
     */
    protected function getMcpToolsSchema(): array
    {
        return [
            // Create Order Tool
            [
                'name' => 'create_order',
                'description' => 'Create a new order in the logistics system. Order numbers are automatically generated based on merchant configuration.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'order_date' => ['type' => 'string', 'description' => 'Order date in YYYY-MM-DD format'],
                        'amount' => ['type' => 'number', 'description' => 'Total order amount'],
                        'client_name' => ['type' => 'string', 'description' => 'Customer full name'],
                        'address' => ['type' => 'string', 'description' => 'Delivery address'],
                        'phone' => ['type' => 'string', 'description' => 'Customer phone number'],
                        'alt_no' => ['type' => 'string', 'description' => 'Alternative phone number'],
                        'country' => ['type' => 'string', 'default' => 'Kenya'],
                        'city' => ['type' => 'string', 'description' => 'City name'],
                        'product_name' => ['type' => 'string', 'description' => 'Name of the product'],
                        'quantity' => ['type' => 'integer', 'minimum' => 1, 'description' => 'Product quantity'],
                        'status' => ['type' => 'string', 'default' => 'Pending'],
                        'agent' => ['type' => 'string', 'description' => 'Agent name'],
                        'delivery_date' => ['type' => 'string', 'description' => 'Delivery date in YYYY-MM-DD format'],
                        'instructions' => ['type' => 'string', 'description' => 'Special delivery instructions'],
                        'cc_email' => ['type' => 'string', 'description' => 'CC email address'],
                        'merchant' => ['type' => 'string', 'description' => 'Merchant name - CRITICAL for order number generation'],
                        'order_type' => ['type' => 'string', 'description' => 'Type of order (e.g., Retail, Wholesale, Online)'],
                        'store_name' => ['type' => 'string', 'description' => 'Store name'],
                        'code' => ['type' => 'string', 'description' => 'Order code'],
                    ],
                    'required' => [
                        'order_date', 'amount', 'client_name',
                        'phone', 'address', 'city', 'product_name', 'quantity',
                        'merchant', 'order_type',
                    ],
                ],
            ],

            // Update Order Tool
            [
                'name' => 'update_order',
                'description' => 'Update an existing order in the logistics system. Requires order number. Only updates the fields provided.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'order_no' => ['type' => 'string', 'description' => 'The order number to update (REQUIRED)'],
                        'order_date' => ['type' => 'string', 'description' => 'Order date in YYYY-MM-DD format'],
                        'amount' => ['type' => 'number', 'description' => 'Total order amount'],
                        'client_name' => ['type' => 'string', 'description' => 'Customer full name'],
                        'address' => ['type' => 'string', 'description' => 'Delivery address'],
                        'phone' => ['type' => 'string', 'description' => 'Customer phone number'],
                        'alt_no' => ['type' => 'string', 'description' => 'Alternative phone number'],
                        'country' => ['type' => 'string', 'description' => 'Country name'],
                        'city' => ['type' => 'string', 'description' => 'City name'],
                        'product_name' => ['type' => 'string', 'description' => 'Name of the product'],
                        'quantity' => ['type' => 'integer', 'minimum' => 1, 'description' => 'Product quantity'],
                        'status' => ['type' => 'string', 'description' => 'Order status (Pending, Processing, Delivered, Cancelled)'],
                        'agent' => ['type' => 'string', 'description' => 'Agent name'],
                        'delivery_date' => ['type' => 'string', 'description' => 'Delivery date in YYYY-MM-DD format'],
                        'instructions' => ['type' => 'string', 'description' => 'Special delivery instructions'],
                        'cc_email' => ['type' => 'string', 'description' => 'CC email address'],
                        'merchant' => ['type' => 'string', 'description' => 'Merchant name'],
                        'order_type' => ['type' => 'string', 'description' => 'Type of order'],
                        'store_name' => ['type' => 'string', 'description' => 'Store name'],
                        'code' => ['type' => 'string', 'description' => 'Order code'],
                    ],
                    'required' => ['order_no'],
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
            $mcpRequest = new McpRequest($args);

            if ($name === 'create_order') {
                $tool = new CreateOrderTool;
                $response = $tool->handle($mcpRequest);
            } elseif ($name === 'update_order') {
                $tool = new UpdateOrderTool;
                $response = $tool->handle($mcpRequest);
            } else {
                throw new \Exception("Unknown tool: {$name}");
            }

            // Get response data that was stored in the request
            $responseData = $mcpRequest->get('_response_data', [
                'message' => 'Order processed',
            ]);

            Log::info('Tool executed successfully', [
                'tool' => $name,
                'response' => $responseData,
            ]);

            return [
                'functionResponse' => [
                    'name' => $name,
                    'response' => $responseData,
                ],
            ];

        } catch (\Exception $e) {
            Log::error('MCP tool execution error', [
                'tool' => $name,
                'args' => $args,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
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
You are an AI assistant for order management in a logistics system.

CAPABILITIES:
1. CREATE ORDERS - Generate new orders with auto-generated order numbers
2. UPDATE ORDERS - Modify existing order details

CREATING ORDERS:
- Order numbers are AUTO-GENERATED from merchant configuration
- DO NOT ask for or accept order numbers when creating
- Use today's date for order_date if not specified (format: YYYY-MM-DD)
- Merchant name is CRITICAL - it determines the order number format
- Required fields: order_date, amount, client_name, phone, address, city, product_name, quantity, merchant, order_type

UPDATING ORDERS:
- MUST have the order number to update
- If user doesn't provide order number, ask for it
- Only update the fields the user wants to change
- Confirm what will be changed before executing
- Common updates: status changes, delivery dates, addresses, quantities

INSTRUCTIONS:
1. Determine if user wants to CREATE or UPDATE
2. Extract all available information from their message
3. If required fields are missing, ask concise follow-up questions ONE AT A TIME
4. When you have all required info, call the appropriate function

User message: {$userMessage}
PROMPT;
    }
}
