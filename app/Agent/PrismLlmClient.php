<?php

namespace App\Agent;

use Closure;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Schema\RawSchema;
use Prism\Prism\Tool;

/** Real LLM via Prism PHP. Prism runs the tool-calling loop for us. */
class PrismLlmClient implements LlmClient
{
    public function __construct(
        private readonly string $provider,
        private readonly string $model,
        private readonly int $maxSteps = 6,
    ) {}

    public function respond(string $system, string $prompt, array $tools, Closure $callTool): string
    {
        $response = Prism::text()
            ->using($this->provider, $this->model)
            ->withSystemPrompt($system)
            ->withPrompt($prompt)
            ->withTools(array_map(fn (array $definition) => self::toPrismTool($definition, $callTool), $tools))
            ->withMaxSteps($this->maxSteps)
            ->asText();

        return trim($response->text) !== '' ? $response->text : 'I ran the tools but the model returned no text.';
    }

    /** Turn an MCP tool definition (JSON Schema) into a Prism tool that calls back into MCP. */
    public static function toPrismTool(array $definition, Closure $callTool): Tool
    {
        $tool = (new Tool)->as($definition['name'])->for($definition['description']);
        $required = $definition['inputSchema']['required'] ?? [];

        foreach ($definition['inputSchema']['properties'] ?? [] as $name => $schema) {
            $tool->withParameter(new RawSchema($name, $schema), in_array($name, $required, true));
        }

        return $tool->using(function (...$arguments) use ($definition, $callTool): string {
            $result = $callTool($definition['name'], array_filter($arguments, fn ($v) => $v !== null));

            return collect($result['content'] ?? [])->pluck('text')->implode("\n");
        });
    }
}
