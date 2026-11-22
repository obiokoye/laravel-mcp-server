<?php

namespace App\Http\Controllers;

use App\Agent\McpAgent;
use App\Mcp\McpServer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** The web console: see what the server exposes and chat with an agent that uses it. */
class ConsoleController extends Controller
{
    public function index(McpServer $server): View
    {
        $init = $server->handle(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => []])['result'];
        $tools = $server->handle(['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list'])['result']['tools'];
        $resources = $server->handle(['jsonrpc' => '2.0', 'id' => 3, 'method' => 'resources/list'])['result']['resources'];
        $prompts = $server->handle(['jsonrpc' => '2.0', 'id' => 4, 'method' => 'prompts/list'])['result']['prompts'];

        return view('console', [
            'server' => $init,
            'tools' => $tools,
            'resources' => $resources,
            'prompts' => $prompts,
            'demo' => (bool) config('agent.demo'),
            'provider' => config('agent.provider'),
            'model' => config('agent.model'),
            'allowWrites' => (bool) config('mcp.allow_writes'),
            'suggestions' => [
                'Which products are running low on stock?',
                'What\'s the status of order AL-1042?',
                'Mark order AL-1042 as shipped',
                'How did sales look this week?',
                'Find indigo cushions under ₦20,000',
            ],
        ]);
    }

    public function ask(Request $request, McpAgent $agent): JsonResponse
    {
        $validated = $request->validate(['message' => ['required', 'string', 'max:500']]);

        try {
            $run = $agent->ask($validated['message']);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['error' => 'The model request failed: '.class_basename($e).'. Check your API key or set DEMO_MODE=true.'], 502);
        }

        return response()->json($run->toArray(), 200, [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
