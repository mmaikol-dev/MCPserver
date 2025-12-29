<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\CreateOrderTool;
use App\Mcp\Tools\UpdateOrderTool;
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
- Always use the correct tool for each action.
- Never assume or fabricate missing data.
- System-managed fields (IDs, timestamps, processed flags) are handled automatically.

When updating orders:
- Always ask for the order number first if not provided.
- Only update the fields that the user wants to change.
- Confirm changes before executing.

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
    ];

    /**
     * The resources registered with this MCP server.
     *
     * @var array<int, class-string<\Laravel\Mcp\Server\Resource>>
     */
    protected array $resources = [
        // Future: OrderResource, SheetOrderResource
    ];

    /**
     * The prompts registered with this MCP server.
     *
     * @var array<int, class-string<\Laravel\Mcp\Server\Prompt>>
     */
    protected array $prompts = [
        // Optional reusable prompts can be added later
    ];
}
