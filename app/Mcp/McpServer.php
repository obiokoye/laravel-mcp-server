<?php

namespace App\Mcp;

use App\Mcp\Contracts\Prompt;
use App\Mcp\Contracts\Resource;
use App\Mcp\Contracts\Tool;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use JsonException;
use Throwable;

/**
 * A transport-agnostic Model Context Protocol server.
 *
 * It takes one JSON-RPC 2.0 message (already decoded) and returns the reply.
 * The HTTP controller and the stdio artisan command are thin wrappers around
 * handle(), the same way a controller and a console command can share a service.
 */
class McpServer
{
    /** Newest first. We answer with the client's version if we support it. */
    public const PROTOCOL_VERSIONS = ['2025-06-18', '2025-03-26', '2024-11-05'];

    /** @var array<string, Tool> */
    private array $tools = [];

    /** @var array<string, resource> */
    private array $resources = [];

    /** @var array<string, Prompt> */
    private array $prompts = [];

    /**
     * @param  iterable<Tool>  $tools
     * @param  iterable<resource>  $resources
     * @param  iterable<Prompt>  $prompts
     */
    public function __construct(
        iterable $tools,
        iterable $resources,
        iterable $prompts,
        private readonly bool $allowWrites = false,
        private readonly string $name = 'laravel-mcp-server',
        private readonly string $version = '1.0.0',
    ) {
        foreach ($tools as $tool) {
            // Least privilege: write tools are not even listed unless enabled.
            if ($tool->isReadOnly() || $this->allowWrites) {
                $this->tools[$tool->name()] = $tool;
            }
        }
        foreach ($resources as $resource) {
            $this->resources[$resource->uri()] = $resource;
        }
        foreach ($prompts as $prompt) {
            $this->prompts[$prompt->name()] = $prompt;
        }
    }

    /**
     * Decode, handle and encode one raw JSON message (used by stdio).
     * Returns null for notifications, which get no reply.
     */
    public function handleJson(string $json): ?string
    {
        try {
            $message = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $this->encode($this->error(null, JsonRpcError::PARSE_ERROR, 'Parse error'));
        }

        $reply = is_array($message) ? $this->handle($message) : $this->error(null, JsonRpcError::INVALID_REQUEST, 'Invalid Request');

        return $reply === null ? null : $this->encode($reply);
    }

    /**
     * @param  array<string, mixed>  $message
     * @return array<string, mixed>|null
     */
    public function handle(array $message): ?array
    {
        $id = $message['id'] ?? null;
        $isNotification = ! array_key_exists('id', $message);

        if (($message['jsonrpc'] ?? null) !== '2.0' || ! is_string($message['method'] ?? null) || array_is_list($message)) {
            return $this->error($id, JsonRpcError::INVALID_REQUEST, 'Invalid Request');
        }

        $params = $message['params'] ?? [];
        if (! is_array($params)) {
            return $this->error($id, JsonRpcError::INVALID_PARAMS, 'params must be an object');
        }

        try {
            $result = $this->dispatch($message['method'], $params);
        } catch (JsonRpcError $e) {
            return $isNotification ? null : $this->error($id, $e->rpcCode, $e->getMessage());
        }

        return $isNotification ? null : ['jsonrpc' => '2.0', 'id' => $id, 'result' => $result === [] ? new \stdClass : $result];
    }

    /** @return list<Tool> */
    public function tools(): array
    {
        return array_values($this->tools);
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function dispatch(string $method, array $params): array
    {
        return match ($method) {
            'initialize' => $this->initialize($params),
            'ping', 'notifications/initialized', 'notifications/cancelled' => [],
            'tools/list' => ['tools' => array_map($this->toolDefinition(...), $this->tools())],
            'tools/call' => $this->callTool($params),
            'resources/list' => ['resources' => array_map($this->resourceDefinition(...), array_values($this->resources))],
            'resources/read' => $this->readResource($params),
            'prompts/list' => ['prompts' => array_map($this->promptDefinition(...), array_values($this->prompts))],
            'prompts/get' => $this->getPrompt($params),
            default => throw new JsonRpcError("Method not found: {$method}", JsonRpcError::METHOD_NOT_FOUND),
        };
    }

    private function initialize(array $params): array
    {
        $requested = $params['protocolVersion'] ?? null;

        return [
            'protocolVersion' => in_array($requested, self::PROTOCOL_VERSIONS, true) ? $requested : self::PROTOCOL_VERSIONS[0],
            'capabilities' => [
                'tools' => ['listChanged' => false],
                'resources' => ['listChanged' => false, 'subscribe' => false],
                'prompts' => ['listChanged' => false],
            ],
            'serverInfo' => ['name' => $this->name, 'version' => $this->version],
            'instructions' => 'Shop data for Adire Lane. Read tools are safe to call freely. '
                .($this->allowWrites ? 'update_order_status changes real data: confirm with the user first.' : 'This server is read-only.'),
        ];
    }

    private function callTool(array $params): array
    {
        $name = $params['name'] ?? null;
        $arguments = $params['arguments'] ?? [];

        if (! is_string($name) || ! isset($this->tools[$name])) {
            throw new JsonRpcError('Unknown tool: '.(is_string($name) ? $name : '(missing)'), JsonRpcError::INVALID_PARAMS);
        }
        if (! is_array($arguments)) {
            throw new JsonRpcError('arguments must be an object', JsonRpcError::INVALID_PARAMS);
        }

        $tool = $this->tools[$name];

        // Same idea as a Form Request: never trust input, even from an AI.
        $validator = Validator::make($arguments, $tool->rules());
        if ($validator->fails()) {
            return $this->toolError($name, $arguments, 'Invalid arguments: '.implode(' ', $validator->errors()->all()));
        }

        try {
            $data = $tool->handle($validator->validated());
        } catch (ToolException $e) {
            return $this->toolError($name, $arguments, $e->getMessage());
        } catch (Throwable $e) {
            report($e);

            return $this->toolError($name, $arguments, 'The tool failed unexpectedly.');
        }

        Log::info('mcp.tools.call', ['tool' => $name, 'arguments' => $arguments, 'ok' => true]);

        return [
            'content' => [['type' => 'text', 'text' => $this->encode($data)]],
            'structuredContent' => $data,
            'isError' => false,
        ];
    }

    /** Tool errors are results, not protocol errors, so the model can read them and retry. */
    private function toolError(string $name, array $arguments, string $message): array
    {
        Log::warning('mcp.tools.call', ['tool' => $name, 'arguments' => $arguments, 'ok' => false, 'error' => $message]);

        return ['content' => [['type' => 'text', 'text' => $message]], 'isError' => true];
    }

    private function readResource(array $params): array
    {
        $uri = $params['uri'] ?? null;
        $resource = is_string($uri) ? ($this->resources[$uri] ?? null) : null;

        if ($resource === null) {
            throw new JsonRpcError('Resource not found: '.(is_string($uri) ? $uri : '(missing)'), JsonRpcError::INVALID_PARAMS);
        }

        return ['contents' => [[
            'uri' => $resource->uri(),
            'mimeType' => $resource->mimeType(),
            'text' => $resource->read(),
        ]]];
    }

    private function getPrompt(array $params): array
    {
        $name = $params['name'] ?? null;
        $prompt = is_string($name) ? ($this->prompts[$name] ?? null) : null;

        if ($prompt === null) {
            throw new JsonRpcError('Prompt not found: '.(is_string($name) ? $name : '(missing)'), JsonRpcError::INVALID_PARAMS);
        }

        $arguments = is_array($params['arguments'] ?? null) ? $params['arguments'] : [];
        foreach ($prompt->arguments() as $argument) {
            if ($argument['required'] && ! isset($arguments[$argument['name']])) {
                throw new JsonRpcError("Missing prompt argument: {$argument['name']}", JsonRpcError::INVALID_PARAMS);
            }
        }

        return ['description' => $prompt->description(), 'messages' => $prompt->messages($arguments)];
    }

    private function toolDefinition(Tool $tool): array
    {
        return [
            'name' => $tool->name(),
            'title' => $tool->title(),
            'description' => $tool->description(),
            'inputSchema' => $tool->inputSchema(),
            'annotations' => [
                'readOnlyHint' => $tool->isReadOnly(),
                'destructiveHint' => ! $tool->isReadOnly(),
                'openWorldHint' => false,
            ],
        ];
    }

    private function resourceDefinition(Resource $resource): array
    {
        return [
            'uri' => $resource->uri(),
            'name' => $resource->name(),
            'description' => $resource->description(),
            'mimeType' => $resource->mimeType(),
        ];
    }

    private function promptDefinition(Prompt $prompt): array
    {
        return ['name' => $prompt->name(), 'description' => $prompt->description(), 'arguments' => $prompt->arguments()];
    }

    private function error(mixed $id, int $code, string $message): array
    {
        return ['jsonrpc' => '2.0', 'id' => $id, 'error' => ['code' => $code, 'message' => $message]];
    }

    private function encode(array|object $data): string
    {
        return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
