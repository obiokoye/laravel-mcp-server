<?php

/*
 * The console's built-in agent: an LLM that talks to our own MCP server.
 * Provider keys themselves live in config/prism.php (GEMINI_API_KEY, etc.).
 */

$provider = env('LLM_PROVIDER', 'gemini');

$defaults = [
    'gemini' => ['model' => 'gemini-2.5-flash', 'key' => 'GEMINI_API_KEY'],
    'groq' => ['model' => 'llama-3.3-70b-versatile', 'key' => 'GROQ_API_KEY'],
    'openai' => ['model' => 'gpt-4o-mini', 'key' => 'OPENAI_API_KEY'],
    'anthropic' => ['model' => 'claude-haiku-4-5', 'key' => 'ANTHROPIC_API_KEY'],
];

$selected = $defaults[$provider] ?? $defaults['gemini'];

return [
    'provider' => array_key_exists($provider, $defaults) ? $provider : 'gemini',
    'model' => env('LLM_MODEL') ?: $selected['model'],
    'max_steps' => 6,

    // Demo mode when asked for, or automatically when the provider has no key.
    'demo' => filter_var(env('DEMO_MODE', false), FILTER_VALIDATE_BOOL) || blank(env($selected['key'])),
];
