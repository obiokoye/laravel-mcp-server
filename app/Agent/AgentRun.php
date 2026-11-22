<?php

namespace App\Agent;

/** Everything one question produced: the answer, the tool steps and the raw MCP traffic. */
final class AgentRun
{
    /**
     * @param  list<array{tool: string, arguments: array, result: string, is_error: bool}>  $steps
     * @param  list<array{direction: string, message: array}>  $traffic
     */
    public function __construct(
        public readonly string $answer,
        public readonly array $steps,
        public readonly array $traffic,
        public readonly bool $demo,
    ) {}

    public function toArray(): array
    {
        return ['answer' => $this->answer, 'steps' => $this->steps, 'traffic' => $this->traffic, 'demo' => $this->demo];
    }
}
