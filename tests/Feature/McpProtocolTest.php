<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class McpProtocolTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    public function test_initialize_negotiates_protocol_and_declares_capabilities(): void
    {
        $this->rpc('initialize', ['protocolVersion' => '2025-03-26', 'capabilities' => [], 'clientInfo' => ['name' => 'test', 'version' => '1']])
            ->assertOk()
            ->assertJsonPath('result.protocolVersion', '2025-03-26')
            ->assertJsonPath('result.serverInfo.name', 'adire-lane-shop')
            ->assertJsonStructure(['result' => ['capabilities' => ['tools', 'resources', 'prompts']]]);
    }

    public function test_unknown_protocol_version_falls_back_to_latest(): void
    {
        $this->rpc('initialize', ['protocolVersion' => '1999-01-01'])->assertJsonPath('result.protocolVersion', '2025-06-18');
    }

    public function test_notifications_get_202_and_no_body(): void
    {
        $this->rpc('notifications/initialized', [], null)->assertStatus(202)->assertNoContent(202);
    }

    public function test_ping_returns_an_empty_object(): void
    {
        $response = $this->rpc('ping');

        $this->assertSame('{"jsonrpc":"2.0","id":1,"result":{}}', $response->getContent());
    }

    public function test_tools_list_includes_schemas_and_safety_annotations(): void
    {
        $tools = collect($this->rpc('tools/list')->assertOk()->json('result.tools'))->keyBy('name');

        $this->assertEqualsCanonicalizing(
            ['search_products', 'get_order', 'list_low_stock', 'sales_summary', 'update_order_status'],
            $tools->keys()->all(),
        );
        $this->assertSame('object', $tools['get_order']['inputSchema']['type']);
        $this->assertTrue($tools['get_order']['annotations']['readOnlyHint']);
        $this->assertFalse($tools['update_order_status']['annotations']['readOnlyHint']);
    }

    public function test_write_tools_are_hidden_and_uncallable_when_writes_are_disabled(): void
    {
        config(['mcp.allow_writes' => false]);

        $names = collect($this->rpc('tools/list')->json('result.tools'))->pluck('name');
        $this->assertNotContains('update_order_status', $names);

        $this->rpc('tools/call', ['name' => 'update_order_status', 'arguments' => ['order_number' => 'AL-1042', 'status' => 'shipped']])
            ->assertJsonPath('error.code', -32602);
        $this->assertDatabaseHas('orders', ['number' => 'AL-1042', 'status' => 'paid']);
    }

    public function test_search_products_matches_every_word_and_price_limit(): void
    {
        $this->rpc('tools/call', ['name' => 'search_products', 'arguments' => ['query' => 'indigo cushions', 'max_price' => 20000]])
            ->assertOk()
            ->assertJsonPath('result.isError', false)
            ->assertJsonPath('result.structuredContent.count', 1)
            ->assertJsonPath('result.structuredContent.products.0.sku', 'AL-CUS-01');
    }

    public function test_get_order_returns_details_without_customer_email(): void
    {
        $response = $this->rpc('tools/call', ['name' => 'get_order', 'arguments' => ['order_number' => 'al-1042']])
            ->assertJsonPath('result.structuredContent.status', 'paid')
            ->assertJsonPath('result.structuredContent.customer.name', 'Amaka Eze')
            ->assertJsonPath('result.structuredContent.total_naira', 56500)
            ->assertJsonPath('result.structuredContent.allowed_next_statuses', ['shipped', 'cancelled']);

        $this->assertStringNotContainsString('@example.com', $response->getContent());
    }

    public function test_invalid_arguments_come_back_as_a_tool_error_the_model_can_read(): void
    {
        $this->rpc('tools/call', ['name' => 'get_order', 'arguments' => ['order_number' => '1; DROP TABLE orders']])
            ->assertOk()
            ->assertJsonPath('result.isError', true)
            ->assertJsonPath('result.content.0.text', fn ($text) => str_starts_with($text, 'Invalid arguments'));
    }

    public function test_low_stock_and_sales_summary(): void
    {
        $this->rpc('tools/call', ['name' => 'list_low_stock', 'arguments' => ['threshold' => 2]])
            ->assertJsonPath('result.structuredContent.count', 2)
            ->assertJsonPath('result.structuredContent.products.0.name', 'Woven raffia basket (small)');

        $this->rpc('tools/call', ['name' => 'sales_summary', 'arguments' => ['days' => 7]])
            ->assertJsonPath('result.structuredContent.orders', 9)
            ->assertJsonPath('result.structuredContent.revenue_naira', 352000);
    }

    public function test_update_order_status_follows_business_rules_and_is_audited(): void
    {
        $this->rpc('tools/call', ['name' => 'update_order_status', 'arguments' => ['order_number' => 'AL-1042', 'status' => 'shipped', 'note' => 'courier picked up']])
            ->assertJsonPath('result.structuredContent.previous_status', 'paid')
            ->assertJsonPath('result.structuredContent.status', 'shipped');

        $this->assertDatabaseHas('order_events', ['from_status' => 'paid', 'to_status' => 'shipped', 'actor' => 'mcp:update_order_status', 'note' => 'courier picked up']);

        // shipped -> paid is not an allowed transition.
        $this->rpc('tools/call', ['name' => 'update_order_status', 'arguments' => ['order_number' => 'AL-1042', 'status' => 'paid']])
            ->assertJsonPath('result.isError', true);
        $this->assertDatabaseHas('orders', ['number' => 'AL-1042', 'status' => 'shipped']);
    }

    public function test_resources_and_prompts(): void
    {
        $this->rpc('resources/list')->assertJsonCount(2, 'result.resources');
        $this->rpc('resources/read', ['uri' => 'shop://policies/returns'])
            ->assertJsonPath('result.contents.0.mimeType', 'text/markdown')
            ->assertJsonPath('result.contents.0.text', fn ($t) => str_contains($t, '14 days'));
        $this->rpc('resources/read', ['uri' => 'shop://nope'])->assertJsonPath('error.code', -32602);

        $this->rpc('prompts/get', ['name' => 'draft_customer_reply', 'arguments' => ['order_number' => 'AL-1037']])
            ->assertJsonPath('result.messages.0.role', 'user')
            ->assertJsonPath('result.messages.0.content.text', fn ($t) => str_contains($t, 'AL-1037'));
        $this->rpc('prompts/get', ['name' => 'draft_customer_reply'])->assertJsonPath('error.code', -32602);
    }

    public function test_protocol_errors(): void
    {
        $this->rpc('does/not/exist')->assertJsonPath('error.code', -32601);
        $this->rpc('tools/call', ['name' => 'rm_rf'])->assertJsonPath('error.code', -32602);
        $this->postJson('/mcp', ['id' => 1, 'method' => 'ping'])->assertJsonPath('error.code', -32600);
        $this->postJson('/mcp', [['jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping']])->assertStatus(400)->assertJsonPath('error.code', -32600);
        $this->call('POST', '/mcp', [], [], [], ['CONTENT_TYPE' => 'application/json'], '{not json')
            ->assertStatus(400)->assertJsonPath('error.code', -32700);
        $this->get('/mcp')->assertStatus(405);
    }

    public function test_bearer_token_is_required_when_configured(): void
    {
        config(['mcp.token' => 'secret-token']);

        $this->rpc('ping')->assertStatus(401);
        $this->withToken('wrong')->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping'])->assertStatus(401);
        $this->withToken('secret-token')->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping'])->assertOk();
    }

    public function test_foreign_origins_are_rejected(): void
    {
        $this->withHeader('Origin', 'https://evil.example')->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping'])->assertStatus(403);
        $this->withHeader('Origin', 'http://localhost:6274')->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping'])->assertOk();
    }
}
