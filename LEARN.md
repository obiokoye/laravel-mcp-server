# LEARN: Day 13, MCP for a Laravel developer

You already know Laravel, so this walkthrough skips the framework and focuses on the agent ideas: what MCP is, how this server speaks it, and why the guardrails are where they are.

## 1. What MCP is, in one paragraph

On Day 1 the tools lived *inside* the agent: a Python function plus a JSON Schema. That doesn't scale. If Claude Desktop, Cursor and your own agent all want to use your shop's data, you'd rewrite the same tools three times. The **Model Context Protocol** fixes that by putting tools behind a standard interface. Your app becomes an **MCP server**; any **MCP client** (the AI app) can connect, ask "what can you do?", and call what it finds.

Think of it like a REST API, but designed for models: self-describing, with a fixed set of methods every client knows.

| MCP idea | Closest Laravel idea |
|----------|----------------------|
| MCP server | An API with a single route that dispatches on `method` |
| Tool | A tiny invokable controller with a Form Request |
| Tool `inputSchema` | API docs the model reads before calling |
| Tool `rules()` | The Form Request's `rules()`, enforced on every call |
| Resource (`shop://policies/returns`) | A read-only `GET` endpoint, addressed by URI |
| Prompt (`draft_customer_reply`) | A reusable Blade template, but for prompts |
| Transport (HTTP or stdio) | Route vs Artisan command calling the same service |
| `initialize` handshake | API version negotiation |

## 2. The protocol is JSON-RPC 2.0

Every message has `jsonrpc: "2.0"` and a `method`. Requests have an `id` and get a reply with the same `id`. Notifications have no `id` and get no reply.

```json
→ {"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-06-18"}}
← {"jsonrpc":"2.0","id":1,"result":{"protocolVersion":"2025-06-18","capabilities":{"tools":{...}},"serverInfo":{...}}}
→ {"jsonrpc":"2.0","method":"notifications/initialized"}
→ {"jsonrpc":"2.0","id":2,"method":"tools/list"}
→ {"jsonrpc":"2.0","id":3,"method":"tools/call","params":{"name":"get_order","arguments":{"order_number":"AL-1042"}}}
```

All of that is handled in `app/Mcp/McpServer.php`. The heart of it is a `match` on the method name, which is just a router:

```php
return match ($method) {
    'initialize' => $this->initialize($params),
    'tools/list' => ['tools' => array_map($this->toolDefinition(...), $this->tools())],
    'tools/call' => $this->callTool($params),
    // ...
    default => throw new JsonRpcError("Method not found: {$method}", JsonRpcError::METHOD_NOT_FOUND),
};
```

Open the console (http://localhost:8000) and watch the right-hand panel: every one of these messages appears as the agent works.

## 3. Two kinds of errors (the important part)

There are two very different failures, and MCP keeps them apart:

1. **Protocol errors**: the message itself is wrong (unknown method, unknown tool, broken JSON). These come back as a JSON-RPC `error` with a code like `-32601`. They're for the *client developer*.
2. **Tool errors**: the call was well-formed but can't be done ("Order AL-1042 is shipped and cannot move to paid. Allowed: delivered."). These come back as a normal `result` with `isError: true`. They're for the *model*.

Why does that matter? Because the model reads the tool result. If you throw a 500, the agent is stuck. If you return a clear message, the model can fix its arguments and try again. It's the same reason a Form Request returns readable validation messages instead of a stack trace.

## 4. Guardrails live in code, not in the prompt

The system prompt in `McpAgent` says "only call update_order_status when the user explicitly asks". That's a hint, not a guarantee: prompt injection or a confused model can ignore it. The real protection is in PHP:

- **Least privilege**: `update_order_status` isn't even listed unless `MCP_ALLOW_WRITES=true`. A model can't call what it can't see, and `tools/call` refuses it too.
- **Business rules**: `Order::TRANSITIONS` decides which status changes are legal, inside a DB transaction with `lockForUpdate()`.
- **Validation**: every argument passes Laravel validation (`regex:/^AL-\d{4}$/` for order numbers), so `"1; DROP TABLE orders"` never reaches a query.
- **Audit trail**: every change writes an `order_events` row with `actor = mcp:update_order_status`.
- **Data minimisation**: `get_order` returns the customer's name and city, not their email.
- **Annotations**: `readOnlyHint` / `destructiveHint` tell clients which tools change data, so they can decide when to ask the user first.

## 5. Security note for the HTTP transport

An MCP server is an API that an AI can drive, so treat it like one:

- Set `MCP_TOKEN` anywhere other than your own machine. `AuthenticateMcp` compares it with `hash_equals()` (constant time).
- The same middleware rejects browser requests from unknown `Origin`s. The MCP spec asks for this because a malicious web page could otherwise use "DNS rebinding" to reach a server on `localhost`.
- `POST /mcp` is rate limited (`throttle:120,1`).
- The console at `/` is a local development tool with no login. Don't expose it publicly with writes enabled.

## 6. The agent is just another MCP client

`app/Agent/McpAgent.php` is deliberately strict: it never imports a model class. It does exactly what Claude Desktop does:

1. `initialize` and `tools/list` through `McpClient`.
2. Hand those tool definitions to the LLM (`PrismLlmClient` turns each MCP JSON Schema into a Prism tool with `RawSchema`).
3. When the model calls a tool, send `tools/call` back to the server and return the text result.

Prism runs the loop (`withMaxSteps(6)`), the same "call model, run tools, repeat" loop you saw on Day 1 and Day 8.

## 7. Testing without an API key

`ScriptedLlmClient` implements the same `LlmClient` interface as `PrismLlmClient`. It pattern-matches the question, makes real MCP tool calls and builds its answer from the real results. Tests bind it instead of Prism, the same idea as `Http::fake()`. There's also one test that uses `Prism::fake()` to prove the real client sends all five tools to the model.

## Try this

- Add a `customer_orders` tool that lists a customer's last five orders by name. Register it in `config/mcp.php` and ask the console "What has Tunde ordered?" (in demo mode you'll need to add a script line too).
- Set `MCP_ALLOW_WRITES=false`, reload the console, and ask "Mark order AL-1042 as shipped". Watch the write tool disappear and the agent explain why it can't.
- Connect Claude Desktop with the stdio config in the README and ask "Which products should I restock this week?". Then set `MCP_TOKEN`, and try the HTTP endpoint with curl with and without the `Authorization` header.
