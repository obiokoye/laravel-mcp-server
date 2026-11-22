<?php

namespace App\Mcp;

/**
 * MCP stdio transport: newline-delimited JSON-RPC on stdin/stdout.
 * Only protocol messages may go to stdout; logs go to storage/logs.
 */
class StdioTransport
{
    public function __construct(private readonly McpServer $server) {}

    /**
     * @param  resource  $input
     * @param  resource  $output
     */
    public function run($input, $output): int
    {
        $handled = 0;

        while (($line = fgets($input)) !== false) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $reply = $this->server->handleJson($line);
            if ($reply !== null) {
                fwrite($output, $reply."\n");
                fflush($output);
            }
            $handled++;
        }

        return $handled;
    }
}
