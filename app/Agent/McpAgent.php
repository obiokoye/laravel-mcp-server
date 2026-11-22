<?php

namespace App\Agent;

use App\Mcp\McpServer;

/**
 * The agent never touches Eloquent. It discovers tools over MCP and calls
 * them over MCP, exactly like Claude Desktop or Cursor would.
 */
class McpAgent
{
    public const SYSTEM_PROMPT = <<<'TXT'
    You are the assistant for Adire Lane, a small Lagos homeware shop.
    Use the MCP tools for every fact about products, orders and sales; never guess numbers.
    Prices are in naira; format them like ₦14,500.
    Only call update_order_status when the user explicitly asks to change an order.
    If a tool returns an error, explain it plainly and suggest what is allowed.
    Keep answers short.
    TXT;

    public function __construct(
        private readonly McpServer $server,
        private readonly LlmClient $llm,
        private readonly bool $demo,
    ) {}

    public function ask(string $prompt): AgentRun
    {
        $client = new McpClient($this->server);
        $client->initialize();
        $tools = $client->listTools();

        $steps = [];
        $callTool = function (string $name, array $arguments) use ($client, &$steps): array {
            $result = $client->callTool($name, $arguments);
            $steps[] = [
                'tool' => $name,
                'arguments' => $arguments,
                'result' => collect($result['content'] ?? [])->pluck('text')->implode("\n"),
                'is_error' => (bool) ($result['isError'] ?? false),
            ];

            return $result;
        };

        $answer = $this->llm->respond(self::SYSTEM_PROMPT, $prompt, $tools, $callTool);

        return new AgentRun($answer, $steps, $client->traffic(), $this->demo);
    }
}
