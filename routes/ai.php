<?php

use Laravel\Mcp\Facades\Mcp;

Mcp::web('/mcp/demo', \App\Mcp\Servers\OrderServer::class);
