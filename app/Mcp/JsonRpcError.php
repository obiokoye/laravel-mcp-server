<?php

namespace App\Mcp;

use RuntimeException;

/** A protocol-level failure, returned as a JSON-RPC "error" object. */
class JsonRpcError extends RuntimeException
{
    public const PARSE_ERROR = -32700;

    public const INVALID_REQUEST = -32600;

    public const METHOD_NOT_FOUND = -32601;

    public const INVALID_PARAMS = -32602;

    public function __construct(string $message, public readonly int $rpcCode)
    {
        parent::__construct($message);
    }
}
