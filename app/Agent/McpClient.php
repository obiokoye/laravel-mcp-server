<?php

namespace App\Agent;

use App\Mcp\McpServer;
use RuntimeException;

/**
 * A minimal MCP client using an in-process transport.
 *
 * Messages are JSON-encoded and decoded exactly as they would be over HTTP or
 * stdio, and every exchange is recorded so the console can show the raw
 * JSON-RPC traffic between the agent and the server.
 */
class McpClient
{
    private int $nextId = 1;

    /** @var list<array{direction: string, message: array}> */
    private array $traffic = [];

    public function __construct(private readonly McpServer $server) {}

    public function initialize(): array
    {
        $result = $this->request('initialize', [
            'protocolVersion' => McpServer::PROTOCOL_VERSIONS[0],
            'capabilities' => new \stdClass,
            'clientInfo' => ['name' => 'adire-lane-console', 'version' => '1.0.0'],
        ]);
        $this->notify('notifications/initialized');

        return $result;
    }

    /** @return list<array> MCP tool definitions */
    public function listTools(): array
    {
        return $this->request('tools/list')['tools'] ?? [];
    }

    /** @return array MCP CallToolResult; protocol errors become isError results */
    public function callTool(string $name, array $arguments): array
    {
        try {
            return $this->request('tools/call', ['name' => $name, 'arguments' => (object) $arguments]);
        } catch (RuntimeException $e) {
            return ['content' => [['type' => 'text', 'text' => $e->getMessage()]], 'isError' => true];
        }
    }

    /** @return list<array{direction: string, message: array}> */
    public function traffic(): array
    {
        return $this->traffic;
    }

    private function request(string $method, array $params = []): array
    {
        $reply = $this->send(['jsonrpc' => '2.0', 'id' => $this->nextId++, 'method' => $method, 'params' => (object) $params]);

        if (isset($reply['error'])) {
            throw new RuntimeException("MCP error {$reply['error']['code']}: {$reply['error']['message']}");
        }

        return $reply['result'] ?? [];
    }

    private function notify(string $method): void
    {
        $this->send(['jsonrpc' => '2.0', 'method' => $method]);
    }

    private function send(array $message): ?array
    {
        $json = json_encode($message, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $this->traffic[] = ['direction' => 'client → server', 'message' => json_decode($json, true)];

        $replyJson = $this->server->handleJson($json);
        if ($replyJson === null) {
            return null;
        }

        $reply = json_decode($replyJson, true);
        $this->traffic[] = ['direction' => 'server → client', 'message' => $reply];

        return $reply;
    }
}
