<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\CreateOrderTool;
use App\Mcp\Tools\DeleteOrderTool;
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
- Delete orders using the DeleteOrderTool (REQUIRES PASSWORD).
- Always use the correct tool for each action.
- Never assume or fabricate missing data.
- System-managed fields (IDs, timestamps, processed flags) are handled automatically.

When creating orders:
- Order numbers are AUTO-GENERATED from merchant configuration.

When updating orders:
- Always ask for the order number first if not provided.
- Only update the fields that the user wants to change.

When deleting orders:
- ⚠️ CRITICAL: Always warn the user that deletion is PERMANENT and IRREVERSIBLE.
- MUST have both order number AND password.
- The password is REQUIRED for security - never proceed without it.
- Confirm the exact order details before attempting deletion.
- If password is wrong, inform user and do NOT retry automatically.

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
