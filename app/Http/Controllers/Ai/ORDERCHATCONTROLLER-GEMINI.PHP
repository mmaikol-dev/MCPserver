<?php

namespace App\Http\Controllers\Ai;

use App\Http\Controllers\Controller;
use App\Mcp\Tools\CreateOrderTool;
use App\Mcp\Tools\DeleteOrderTool;
use App\Mcp\Tools\UpdateOrderTool;
use App\Mcp\Tools\ViewOrderTool;
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

            // View/Search Order Tool
            [
                'name' => 'view_order',
                'description' => 'View a specific order by order number OR search multiple orders by various criteria (client name, status, merchant, product, date range, amount range, etc.). Returns detailed information about orders.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'order_no' => [
                            'type' => 'string',
                            'description' => 'Specific order number to view (if provided, returns only this order with full details)',
                        ],
                        'client_name' => [
                            'type' => 'string',
                            'description' => 'Search by customer name (partial match allowed)',
                        ],
                        'status' => [
                            'type' => 'string',
                            'description' => 'Filter by order status (Pending, Processing, Delivered, Cancelled, etc.)',
                        ],
                        'merchant' => [
                            'type' => 'string',
                            'description' => 'Filter by merchant name (partial match allowed)',
                        ],
                        'product_name' => [
                            'type' => 'string',
                            'description' => 'Filter by product name (partial match allowed)',
                        ],
                        'city' => [
                            'type' => 'string',
                            'description' => 'Filter by city (partial match allowed)',
                        ],
                        'order_type' => [
                            'type' => 'string',
                            'description' => 'Filter by order type (Online, Retail, Wholesale, etc.)',
                        ],
                        'agent' => [
                            'type' => 'string',
                            'description' => 'Filter by agent name (partial match allowed)',
                        ],
                        'phone' => [
                            'type' => 'string',
                            'description' => 'Search by phone number (searches both primary and alternative numbers)',
                        ],
                        'date_from' => [
                            'type' => 'string',
                            'description' => 'Start date for filtering orders (YYYY-MM-DD format)',
                        ],
                        'date_to' => [
                            'type' => 'string',
                            'description' => 'End date for filtering orders (YYYY-MM-DD format)',
                        ],
                        'min_amount' => [
                            'type' => 'number',
                            'description' => 'Minimum order amount for filtering',
                        ],
                        'max_amount' => [
                            'type' => 'number',
                            'description' => 'Maximum order amount for filtering',
                        ],
                        'sort_by' => [
                            'type' => 'string',
                            'description' => 'Field to sort results by (order_date, amount, client_name, status)',
                            'default' => 'order_date',
                        ],
                        'sort_order' => [
                            'type' => 'string',
                            'description' => 'Sort order: asc (ascending) or desc (descending)',
                            'default' => 'desc',
                        ],
                        'limit' => [
                            'type' => 'integer',
                            'description' => 'Maximum number of results to return (default 10, maximum 50)',
                            'default' => 10,
                        ],
                    ],
                    'required' => [],  // All fields are optional for flexible searching
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

            // Delete Order Tool
            [
                'name' => 'delete_order',
                'description' => '⚠️ PERMANENTLY DELETE an order from the system. This action is IRREVERSIBLE. Requires order number AND confirmation password. Use ONLY when user explicitly confirms deletion.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'order_no' => [
                            'type' => 'string',
                            'description' => 'The order number to delete (REQUIRED)',
                        ],
                        'password' => [
                            'type' => 'string',
                            'description' => 'Confirmation password for security. User must provide this to confirm deletion (REQUIRED)',
                        ],
                    ],
                    'required' => ['order_no', 'password'],
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
            } elseif ($name === 'delete_order') {
                $tool = new DeleteOrderTool;
                $response = $tool->handle($mcpRequest);
            } elseif ($name === 'view_order') {
                $tool = new ViewOrderTool;
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
3. DELETE ORDERS - Permanently remove orders (⚠️ REQUIRES PASSWORD)
4. VIEW/SEARCH ORDERS - Find and display order information

VIEWING/SEARCHING ORDERS:
- To view a SINGLE order: Use order_no parameter
  Example: "Show me order JUMANJI-042" → view_order(order_no="JUMANJI-042")
  
- To SEARCH multiple orders: Use filter parameters
  Examples:
  * "Show all pending orders" → view_order(status="Pending")
  * "Find orders for John" → view_order(client_name="John")
  * "Show Adla's orders from this week" → view_order(merchant="Adla", date_from="2025-12-23")
  * "Find orders over 100k" → view_order(min_amount=100000)
  * "Show last 20 orders" → view_order(limit=20)
  
- When showing multiple orders, present them in a clear, organized format
- If too many results, suggest refining the search
- If no results, suggest alternative search criteria

CREATING ORDERS:
- Order numbers are AUTO-GENERATED from merchant configuration
- Use today's date for order_date if not specified

UPDATING ORDERS:
- MUST have the order number to update
- Only update the fields the user wants to change

DELETING ORDERS (⚠️ CRITICAL):
- Deletion is PERMANENT and IRREVERSIBLE
- ALWAYS warn the user before asking for password
- REQUIRES: order number + password

User message: {$userMessage}
PROMPT;
    }
}
