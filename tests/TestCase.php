<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Testing\TestResponse;

abstract class TestCase extends BaseTestCase
{
    /** Send one JSON-RPC request to POST /mcp. */
    protected function rpc(string $method, array $params = [], int|string|null $id = 1): TestResponse
    {
        $message = ['jsonrpc' => '2.0', 'method' => $method, 'params' => (object) $params];
        if ($id !== null) {
            $message['id'] = $id;
        }

        return $this->postJson('/mcp', $message);
    }
}
