<?php

namespace App\Mcp\Contracts;

/**
 * One action the MCP server exposes. Think of it as a tiny controller:
 * a JSON Schema the AI client reads, Laravel validation rules we enforce,
 * and a handle() method that does the work.
 */
interface Tool
{
    public function name(): string;

    public function title(): string;

    public function description(): string;

    /** JSON Schema for the arguments, sent to clients in tools/list. */
    public function inputSchema(): array;

    /** Laravel validation rules, enforced on every tools/call. */
    public function rules(): array;

    /** Read-only tools are always exposed; write tools need MCP_ALLOW_WRITES=true. */
    public function isReadOnly(): bool;

    /**
     * @param  array<string, mixed>  $arguments  already validated
     * @return array<string, mixed> structured result, returned as JSON
     */
    public function handle(array $arguments): array;
}
