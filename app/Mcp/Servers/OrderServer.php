<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\AnalyzeCodeTool;
use App\Mcp\Tools\CreateOrderTool;
use App\Mcp\Tools\DeleteOrderTool;
use App\Mcp\Tools\ListFilesTool;
use App\Mcp\Tools\ReadFileTool;
use App\Mcp\Tools\UpdateOrderTool;
use App\Mcp\Tools\ViewOrderTool;
use App\Mcp\Tools\WriteFileTool;
use Laravel\Mcp\Server;

class OrderServer extends Server
{
    /**
     * The MCP server's name.
     */
    protected string $name = 'Order Management Server';

    /**
     * The MCP server's version.
     */
    protected string $version = '1.0.0';

    /**
     * Instructions for the AI (LLM).
     */
    protected string $instructions = <<<'MARKDOWN'
You are an order management assistant for a logistics system.

Your responsibilities:
- Create new customer orders using the CreateOrderTool.
- Update existing orders using the UpdateOrderTool.
- Delete orders using the DeleteOrderTool (REQUIRES PASSWORD).
- View and search orders using the ViewOrderTool.
- Always use the correct tool for each action.
- Never assume or fabricate missing data.

When viewing/searching orders:
- If user provides an order number, show that specific order with full details.
- If user wants to search, use appropriate filters (client name, status, merchant, date range, etc.).
- Help users refine their search if too many or no results found.
- Summarize multiple orders clearly.

When creating orders:
- Order numbers are AUTO-GENERATED from merchant configuration.

When updating orders:
- Always ask for the order number first if not provided.
- Only update the fields that the user wants to change.

When deleting orders:
- ⚠️ CRITICAL: Always warn that deletion is PERMANENT and IRREVERSIBLE.
- MUST have both order number AND password.

If required information is missing, request clarification instead of guessing.
MARKDOWN;

    /**
     * The tools registered with this MCP server.
     *
     * @var array<int, class-string<\Laravel\Mcp\Server\Tool>>
     */
    protected array $tools = [
        CreateOrderTool::class,
        UpdateOrderTool::class,
        DeleteOrderTool::class,
        ViewOrderTool::class,

        // Code access tools
        ReadFileTool::class,
        ListFilesTool::class,
        WriteFileTool::class,
        AnalyzeCodeTool::class,
    ];

    /**
     * The resources registered with this MCP server.
     *
     * @var array<int, class-string<\Laravel\Mcp\Server\Resource>>
     */
    protected array $resources = [];

    /**
     * The prompts registered with this MCP server.
     *
     * @var array<int, class-string<\Laravel\Mcp\Server\Prompt>>
     */
    protected array $prompts = [];
}
