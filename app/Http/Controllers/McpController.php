<?php

namespace App\Http\Controllers;

use App\Mcp\JsonRpcError;
use App\Mcp\McpServer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * MCP "Streamable HTTP" transport, JSON-only: one JSON-RPC message per POST,
 * one JSON reply. Notifications get 202 Accepted with no body.
 */
class McpController extends Controller
{
    public function __invoke(Request $request, McpServer $server): JsonResponse|Response
    {
        $message = json_decode($request->getContent(), true);

        if (! is_array($message)) {
            return $this->rpcError(JsonRpcError::PARSE_ERROR, 'Parse error', 400);
        }
        if ($message !== [] && array_is_list($message)) {
            return $this->rpcError(JsonRpcError::INVALID_REQUEST, 'Batch requests are not supported', 400);
        }

        $reply = $server->handle($message);

        return $reply === null
            ? response()->noContent(202)
            : response()->json($reply, 200, [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function rpcError(int $code, string $message, int $status): JsonResponse
    {
        return response()->json(['jsonrpc' => '2.0', 'id' => null, 'error' => ['code' => $code, 'message' => $message]], $status);
    }
}
