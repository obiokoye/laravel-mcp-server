<?php

namespace App\Providers;

use App\Agent\LlmClient;
use App\Agent\McpAgent;
use App\Agent\PrismLlmClient;
use App\Agent\ScriptedLlmClient;
use App\Mcp\McpServer;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Built from config/mcp.php on each resolve, so tests can flip settings.
        $this->app->bind(McpServer::class, fn ($app) => new McpServer(
            tools: array_map(fn ($class) => $app->make($class), config('mcp.tools')),
            resources: array_map(fn ($class) => $app->make($class), config('mcp.resources')),
            prompts: array_map(fn ($class) => $app->make($class), config('mcp.prompts')),
            allowWrites: (bool) config('mcp.allow_writes'),
            name: config('mcp.name'),
            version: config('mcp.version'),
        ));

        $this->app->bind(LlmClient::class, fn () => config('agent.demo')
            ? new ScriptedLlmClient
            : new PrismLlmClient(config('agent.provider'), config('agent.model'), config('agent.max_steps')));

        $this->app->bind(McpAgent::class, fn ($app) => new McpAgent(
            $app->make(McpServer::class),
            $app->make(LlmClient::class),
            (bool) config('agent.demo'),
        ));
    }

    public function boot(): void
    {
        //
    }
}
