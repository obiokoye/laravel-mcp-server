<?php

namespace App\Console\Commands;

use App\Mcp\McpServer;
use App\Mcp\StdioTransport;
use Illuminate\Console\Command;

class McpServe extends Command
{
    protected $signature = 'mcp:serve';

    protected $description = 'Run the MCP server over stdio (for Claude Desktop, Cursor and other local MCP clients)';

    public function handle(McpServer $server): int
    {
        (new StdioTransport($server))->run(STDIN, STDOUT);

        return self::SUCCESS;
    }
}
