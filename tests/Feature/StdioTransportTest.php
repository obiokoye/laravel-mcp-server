<?php

namespace Tests\Feature;

use App\Mcp\McpServer;
use App\Mcp\StdioTransport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StdioTransportTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    public function test_it_answers_newline_delimited_json_and_skips_notifications(): void
    {
        $input = fopen('php://memory', 'r+');
        fwrite($input, implode("\n", [
            '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-06-18"}}',
            '{"jsonrpc":"2.0","method":"notifications/initialized"}',
            '',
            '{"jsonrpc":"2.0","id":2,"method":"tools/call","params":{"name":"list_low_stock","arguments":{}}}',
            'garbage',
        ])."\n");
        rewind($input);
        $output = fopen('php://memory', 'r+');

        $handled = (new StdioTransport(app(McpServer::class)))->run($input, $output);

        rewind($output);
        $lines = array_map(fn ($l) => json_decode($l, true), array_filter(explode("\n", stream_get_contents($output))));
        $lines = array_values($lines);

        $this->assertSame(4, $handled);
        $this->assertCount(3, $lines); // the notification gets no reply
        $this->assertSame('2025-06-18', $lines[0]['result']['protocolVersion']);
        $this->assertSame(5, $lines[1]['result']['structuredContent']['count']);
        $this->assertSame(-32700, $lines[2]['error']['code']);
    }
}
