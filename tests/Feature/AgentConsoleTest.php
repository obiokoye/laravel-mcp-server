<?php

namespace Tests\Feature;

use App\Agent\McpAgent;
use App\Agent\PrismLlmClient;
use App\Agent\ScriptedLlmClient;
use App\Mcp\McpServer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Testing\TextResponseFake;
use Tests\TestCase;

/** The console agent runs on the scripted (fake) LLM here: no network, no API key. */
class AgentConsoleTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function agent(): McpAgent
    {
        return new McpAgent(app(McpServer::class), new ScriptedLlmClient, demo: true);
    }

    public function test_agent_discovers_tools_over_mcp_before_calling_them(): void
    {
        $run = $this->agent()->ask('Which products are running low on stock?');

        $methods = collect($run->traffic)->pluck('message.method')->filter()->values()->all();
        $this->assertSame(['initialize', 'notifications/initialized', 'tools/list', 'tools/call'], $methods);
        $this->assertSame('list_low_stock', $run->steps[0]['tool']);
        $this->assertStringContainsString('Woven raffia basket (small): 1 left', $run->answer);
    }

    public function test_agent_answers_from_real_order_data(): void
    {
        $run = $this->agent()->ask("What's the status of order AL-1042?");

        $this->assertStringContainsString('Amaka Eze', $run->answer);
        $this->assertStringContainsString('₦56,500', $run->answer);
    }

    public function test_agent_can_ship_an_order_through_the_write_tool(): void
    {
        $run = $this->agent()->ask('Mark order AL-1042 as shipped');

        $this->assertSame(['get_order', 'update_order_status'], array_column($run->steps, 'tool'));
        $this->assertDatabaseHas('orders', ['number' => 'AL-1042', 'status' => 'shipped']);
        $this->assertStringContainsString('moved from paid to **shipped**', $run->answer);
    }

    public function test_agent_explains_a_rejected_status_change(): void
    {
        $run = $this->agent()->ask('Mark AL-1031 as shipped');

        $this->assertTrue($run->steps[1]['is_error']);
        $this->assertStringContainsString("didn't change anything", $run->answer);
    }

    public function test_agent_reports_read_only_server(): void
    {
        config(['mcp.allow_writes' => false]);

        $run = $this->agent()->ask('Mark order AL-1042 as shipped');

        $this->assertStringContainsString('MCP_ALLOW_WRITES=false', $run->answer);
        $this->assertDatabaseHas('orders', ['number' => 'AL-1042', 'status' => 'paid']);
    }

    public function test_search_parses_price_limits(): void
    {
        $run = $this->agent()->ask('Find indigo cushions under ₦20,000');

        $this->assertSame(['query' => 'indigo cushion', 'max_price' => 20000], $run->steps[0]['arguments']);
        $this->assertStringContainsString('Indigo adire cushion cover: ₦14,500', $run->answer);
    }

    public function test_console_page_shows_capabilities_and_demo_notice(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Demo mode: scripted AI replies')
            ->assertSee('update_order_status')
            ->assertSee('shop://policies/returns');
    }

    public function test_console_ask_endpoint_returns_steps_and_traffic(): void
    {
        $this->postJson('/console/ask', ['message' => 'How did sales look this week?'])
            ->assertOk()
            ->assertJsonPath('demo', true)
            ->assertJsonPath('steps.0.tool', 'sales_summary')
            ->assertJsonPath('answer', fn ($a) => str_contains($a, '9 orders worth ₦352,000'));

        $this->postJson('/console/ask', ['message' => ''])->assertStatus(422);
    }

    public function test_mcp_tool_definitions_map_to_prism_tools(): void
    {
        $definition = collect(app(McpServer::class)->tools())->first(fn ($t) => $t->name() === 'search_products');
        $calls = [];
        $tool = PrismLlmClient::toPrismTool([
            'name' => $definition->name(),
            'description' => $definition->description(),
            'inputSchema' => $definition->inputSchema(),
        ], function ($name, $args) use (&$calls) {
            $calls[] = [$name, $args];

            return ['content' => [['type' => 'text', 'text' => '{"count":0}']]];
        });

        $this->assertSame('search_products', $tool->name());
        $this->assertSame(['query'], $tool->requiredParameters());
        $this->assertSame('{"count":0}', $tool->handle(query: 'rust'));
        $this->assertSame([['search_products', ['query' => 'rust']]], $calls);
    }

    public function test_prism_client_sends_the_mcp_tools_to_the_model(): void
    {
        $fake = Prism::fake([TextResponseFake::make()->withText('AL-1042 is paid.')]);

        $answer = (new McpAgent(app(McpServer::class), new PrismLlmClient('gemini', 'gemini-2.5-flash'), demo: false))
            ->ask('Status of AL-1042?')->answer;

        $this->assertSame('AL-1042 is paid.', $answer);
        $fake->assertRequest(function (array $requests) {
            $this->assertSame('gemini-2.5-flash', $requests[0]->model());
            $this->assertCount(5, $requests[0]->tools());
        });
    }
}
