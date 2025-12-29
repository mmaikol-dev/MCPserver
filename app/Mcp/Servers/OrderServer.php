<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\CreateOrderTool;
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
- Create new customer orders using the provided tools.
- Always use the correct tool for each action.
- Never assume or fabricate missing data.
- Do not update or delete orders unless a specific tool is provided.
- System-managed fields (IDs, timestamps, processed flags) are handled automatically.

If required information is missing, request clarification instead of guessing.
MARKDOWN;

    /**
     * The tools registered with this MCP server.
     *
     * @var array<int, class-string<\Laravel\Mcp\Server\Tool>>
     */
    protected array $tools = [
        CreateOrderTool::class,
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
