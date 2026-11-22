<?php

namespace App\Agent;

use Closure;

/**
 * Anything that can answer a prompt using MCP tools.
 * PrismLlmClient talks to a real model; ScriptedLlmClient is the demo/test fake.
 */
interface LlmClient
{
    /**
     * @param  list<array>  $tools  MCP tool definitions from tools/list
     * @param  Closure(string, array): array  $callTool  runs tools/call and returns the CallToolResult
     */
    public function respond(string $system, string $prompt, array $tools, Closure $callTool): string;
}
