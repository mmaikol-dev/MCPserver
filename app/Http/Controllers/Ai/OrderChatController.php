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
                'description' => 'Create a new order in the logistics system. Order numbers are automatically generated based on merchant configuration.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'order_date' => [
                            'type' => 'string',
                            'description' => 'Order date in YYYY-MM-DD format',
                        ],
                        // REMOVED order_no - it's auto-generated
                        'amount' => [
                            'type' => 'number',
                            'description' => 'Total order amount',
                        ],
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
        ];
    }
    // ```

    // ## Now you can use natural prompts like:
    // ```
    // Create an order for John Mwangi in Nairobi.
    // He wants 2 iPhones for 240k.
    // Phone: 0712345678.
    // Deliver to Westlands by Jan 30th.
    // Call before delivery.
    // Merchant: Apple Hub Kenya

    /**
     * Execute MCP tool based on Gemini's function call
     */
    /**
     * Execute MCP tool based on Gemini's function call
     */
    protected function executeMcpTool(array $functionCall): array
    {
        $name = $functionCall['name'];
        $args = $functionCall['args'] ?? [];

        try {
            if ($name === 'create_order') {
                // Create MCP request from Gemini's function arguments
                $mcpRequest = new McpRequest($args);

                // Execute the tool
                $tool = new CreateOrderTool;
                $response = $tool->handle($mcpRequest);

                // Extract response content
                $responseContent = $this->extractResponseContent($response);

                Log::info('Order created successfully', ['response' => $responseContent]);

                return [
                    'functionResponse' => [
                        'name' => $name,
                        'response' => $responseContent,
                    ],
                ];
            }

            throw new \Exception("Unknown tool: {$name}");
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

    /**
     * Extract content from MCP Response
     */
    protected function extractResponseContent($response): array
    {
        // Get the content property using reflection since it's protected
        $reflection = new \ReflectionClass($response);
        $contentProperty = $reflection->getProperty('content');
        $contentProperty->setAccessible(true);
        $content = $contentProperty->getValue($response);

        // If content is an array of content items, extract the first one
        if (is_array($content) && isset($content[0])) {
            $content = $content[0];
        }

        // Extract the actual data
        if (is_object($content)) {
            $dataProperty = $reflection->getProperty('content');
            $dataProperty->setAccessible(true);

            // Try to get data from the content object
            if (method_exists($content, 'toArray')) {
                return $content->toArray();
            }

            // Try to access content property
            $contentReflection = new \ReflectionClass($content);
            if ($contentReflection->hasProperty('content')) {
                $prop = $contentReflection->getProperty('content');
                $prop->setAccessible(true);
                $data = $prop->getValue($content);

                if (is_array($data)) {
                    return $data;
                }

                if (is_string($data)) {
                    // Try to decode JSON
                    $decoded = json_decode($data, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        return $decoded;
                    }

                    return ['message' => $data];
                }
            }
        }

        return ['message' => 'Order processed'];
    }

    protected function buildOrderPrompt(string $userMessage): string
    {
        return <<<PROMPT
You are an AI assistant for order management in a logistics system.

IMPORTANT: Order numbers are AUTO-GENERATED. DO NOT ask for or accept order numbers from users.

When the user wants to create an order:
1. Extract all available information from their message
2. Use today's date for order_date if not specified (format: YYYY-MM-DD)
3. The merchant name is CRITICAL - it determines which sheet the order belongs to
4. If required fields are missing, ask concise follow-up questions ONE AT A TIME
5. When you have all required fields, call the create_order function

Required fields:
- order_date (YYYY-MM-DD format, default to today if not specified)
- amount (total price)
- client_name
- phone
- address
- city
- product_name
- quantity
- merchant (CRITICAL: must match existing merchant name exactly)
- order_type (e.g., "Retail", "Wholesale", "Online")

Optional fields: alt_no, country (default Kenya), status, agent, delivery_date, instructions, cc_email, store_name, code

NOTE: Order number will be automatically generated based on the merchant's sheet configuration.

User message: {$userMessage}
PROMPT;
    }
}
