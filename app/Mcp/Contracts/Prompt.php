<?php

namespace App\Mcp\Contracts;

/** A reusable prompt template the server offers to clients. */
interface Prompt
{
    public function name(): string;

    public function description(): string;

    /** @return list<array{name: string, description: string, required: bool}> */
    public function arguments(): array;

    /**
     * @param  array<string, string>  $arguments
     * @return list<array{role: string, content: array{type: string, text: string}}>
     */
    public function messages(array $arguments): array;
}
