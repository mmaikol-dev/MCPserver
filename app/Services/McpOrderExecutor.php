<?php

namespace App\Services;

use App\Mcp\Servers\OrderServer;
use Laravel\Mcp\Runner;

class McpOrderExecutor
{
    public function handle(string $aiResponse)
    {
        // This uses Laravel MCP's built-in runner
        $runner = new Runner(new OrderServer);

        return $runner->runFromText($aiResponse);
    }
}
