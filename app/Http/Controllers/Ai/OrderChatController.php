<?php

namespace App\Http\Controllers\Ai;

use App\Http\Controllers\Controller;
use App\Mcp\Tools\AnalyzeCodeTool;
use App\Mcp\Tools\CreateOrderTool;
use App\Mcp\Tools\DeleteOrderTool;
use App\Mcp\Tools\ListFilesTool;
use App\Mcp\Tools\ReadFileTool;
use App\Mcp\Tools\UpdateOrderTool;
use App\Mcp\Tools\ViewOrderTool;
use App\Mcp\Tools\WriteFileTool;
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

            // Read File Tool
            [
                'name' => 'read_file',
                'description' => 'Read the contents of a file from the project codebase. Use this to view source code, configuration files, or any text-based file.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'file_path' => [
                            'type' => 'string',
                            'description' => 'Path to file relative to project root (e.g., "app/Http/Controllers/Ai/OrderChatController.php")',
                        ],
                    ],
                    'required' => ['file_path'],
                ],
            ],

            // List Files Tool
            [
                'name' => 'list_files',
                'description' => 'List files and directories in a specified path. Use this to browse the project structure and find files.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'directory' => [
                            'type' => 'string',
                            'description' => 'Directory path relative to project root',
                            'default' => '.',
                        ],
                        'recursive' => [
                            'type' => 'boolean',
                            'description' => 'List files recursively',
                            'default' => false,
                        ],
                        'pattern' => [
                            'type' => 'string',
                            'description' => 'File name pattern (e.g., "*.php")',
                        ],
                    ],
                    'required' => [],
                ],
            ],

            // Write File Tool
            [
                'name' => 'write_file',
                'description' => '⚠️ Write or modify files in the codebase. REQUIRES PASSWORD. Creates backups by default. Use carefully!',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'file_path' => [
                            'type' => 'string',
                            'description' => 'Path to file to write/modify',
                        ],
                        'content' => [
                            'type' => 'string',
                            'description' => 'Complete file content to write',
                        ],
                        'password' => [
                            'type' => 'string',
                            'description' => 'Password for file modification',
                        ],
                        'backup' => [
                            'type' => 'boolean',
                            'description' => 'Create backup before modifying',
                            'default' => true,
                        ],
                    ],
                    'required' => ['file_path', 'content', 'password'],
                ],
            ],

            // Analyze Code Tool
            [
                'name' => 'analyze_code',
                'description' => 'Analyze code structure, find usages, search for patterns. Helps understand code relationships.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'search_type' => [
                            'type' => 'string',
                            'description' => 'Type: text, class, function, import',
                            'default' => 'text',
                        ],
                        'search_term' => [
                            'type' => 'string',
                            'description' => 'Term to search for',
                        ],
                        'directory' => [
                            'type' => 'string',
                            'description' => 'Directory to search in',
                            'default' => 'app',
                        ],
                        'file_pattern' => [
                            'type' => 'string',
                            'description' => 'File pattern (e.g., *.php)',
                            'default' => '*.php',
                        ],
                    ],
                    'required' => ['search_term'],
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
            } elseif ($name === 'read_file') {
                $tool = new ReadFileTool;
                $response = $tool->handle($mcpRequest);
            } elseif ($name === 'list_files') {
                $tool = new ListFilesTool;
                $response = $tool->handle($mcpRequest);
            } elseif ($name === 'write_file') {
                $tool = new WriteFileTool;
                $response = $tool->handle($mcpRequest);
            } elseif ($name === 'analyze_code') {
                $tool = new AnalyzeCodeTool;
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
You are an AI assistant for order management and code development in a logistics system.

CAPABILITIES:
1. CREATE ORDERS - Generate new orders with auto-generated order numbers
2. UPDATE ORDERS - Modify existing order details
3. DELETE ORDERS - Permanently remove orders (⚠️ REQUIRES PASSWORD)
4. VIEW/SEARCH ORDERS - Find and display order information
5. CODE ACCESS - Read, analyze, and modify project files (⚠️ REQUIRES PASSWORD for modifications)

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

📦 ORDER MANAGEMENT:

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
- Use today's date for order_date if not specified (format: YYYY-MM-DD)
- Required fields: order_date, amount, client_name, phone, address, city, product_name, quantity, merchant, order_type

UPDATING ORDERS:
- MUST have the order number to update
- Only update the fields the user wants to change
- Confirm changes before executing

DELETING ORDERS (⚠️ CRITICAL):
- Deletion is PERMANENT and IRREVERSIBLE
- ALWAYS warn the user before asking for password
- REQUIRES: order number + password (qwerty2025!)

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

💻 CODE ACCESS & DEVELOPMENT:

READING FILES:
- Use read_file to view source code
- Examples:
  * "Show me the OrderChatController" → read_file(file_path="app/Http/Controllers/Ai/OrderChatController.php")
  * "What's in the chat UI?" → read_file(file_path="resources/js/Pages/chats/index.tsx")
- Allowed directories: app, resources, routes, config, database, tests
- Blocked: .env files, keys, vendor, node_modules, storage

LISTING FILES:
- Use list_files to browse project structure
- Examples:
  * "What MCP tools exist?" → list_files(directory="app/Mcp/Tools")
  * "Show all PHP files in controllers" → list_files(directory="app/Http/Controllers", pattern="*.php")
  * "List all React pages" → list_files(directory="resources/js/Pages", recursive=true)

ANALYZING CODE:
- Use analyze_code to find usages and relationships
- Search types: text, class, function, import
- Examples:
  * "Where is CreateOrderTool used?" → analyze_code(search_type="class", search_term="CreateOrderTool")
  * "Find all function calls to viewOrder" → analyze_code(search_type="function", search_term="viewOrder")
  * "Where do we import Gemini?" → analyze_code(search_type="import", search_term="Gemini")

MODIFYING CODE (⚠️ REQUIRES PASSWORD):
- Use write_file to create or modify files
- ALWAYS create backup (default: true)
- REQUIRES password: qwerty2025!
- Allowed directories: app/Mcp/Tools, resources/js/Pages, app/Http/Controllers/Ai
- Workflow:
  1. Read the current file (if exists)
  2. Make necessary changes
  3. Explain changes to user
  4. Get password confirmation
  5. Write file with backup
  
Examples:
* "Add a status filter to ViewOrderTool"
  → 1. read_file to get current code
  → 2. Modify the schema and handle method
  → 3. Explain changes
  → 4. Ask for password
  → 5. write_file with password

* "Fix the bug where deleted orders show in search"
  → 1. read_file to understand the issue
  → 2. analyze_code to find related code
  → 3. Propose fix
  → 4. Apply fix with write_file (requires password)

* "Create a new ExportOrdersTool"
  → 1. Read similar tools for reference
  → 2. Generate complete tool code
  → 3. write_file to create new file (requires password)

SELF-IMPROVEMENT WORKFLOW:
When asked to improve or fix code:
1. **Understand**: Read relevant files to understand current implementation
2. **Analyze**: Use analyze_code to find dependencies and usages
3. **Plan**: Explain what changes you'll make and why
4. **Implement**: Make changes with write_file (after getting password)
5. **Verify**: Explain what was changed and suggest testing

IMPORTANT CODE RULES:
- ALWAYS read files before modifying them
- Create backups before any modifications (automatic)
- Explain changes clearly before asking for password
- Test suggestions after modifications
- Keep code style consistent with existing code
- Add comments for complex logic
- Follow Laravel and React best practices

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

🔐 SECURITY:
- Order deletion password: qwerty2025!
- File modification password: qwerty2025!
- Automatic backups created with timestamp
- Only allowed directories can be accessed/modified
- Sensitive files (.env, keys) are blocked

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

💡 BEHAVIOR GUIDELINES:
- Be proactive: If you see a bug, offer to fix it
- Be cautious: Always explain code changes before applying them
- Be helpful: Suggest improvements when you see opportunities
- Be clear: Use structured responses for code and orders
- Be secure: Never bypass password requirements

User message: {$userMessage}
PROMPT;
    }
}
