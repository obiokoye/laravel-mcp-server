<?php

namespace App\Mcp\Tools;

use App\Mcp\Contracts\Tool;
use App\Mcp\ToolException;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

/**
 * The one write action. Guarded three ways: hidden unless MCP_ALLOW_WRITES=true,
 * limited to the transitions in Order::TRANSITIONS, and recorded in order_events.
 */
class UpdateOrderStatus implements Tool
{
    public function name(): string
    {
        return 'update_order_status';
    }

    public function title(): string
    {
        return 'Update order status';
    }

    public function description(): string
    {
        return 'Move an order to its next status (pending→paid|cancelled, paid→shipped|cancelled, shipped→delivered). Changes real data and is recorded in the audit log.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'order_number' => ['type' => 'string', 'description' => 'Order number such as AL-1042'],
                'status' => ['type' => 'string', 'enum' => ['paid', 'shipped', 'delivered', 'cancelled']],
                'note' => ['type' => 'string', 'description' => 'Optional short note for the audit log'],
            ],
            'required' => ['order_number', 'status'],
        ];
    }

    public function rules(): array
    {
        return [
            'order_number' => ['required', 'string', 'regex:/^AL-\d{4}$/i'],
            'status' => ['required', 'in:paid,shipped,delivered,cancelled'],
            'note' => ['nullable', 'string', 'max:200'],
        ];
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function handle(array $arguments): array
    {
        return DB::transaction(function () use ($arguments) {
            $order = Order::where('number', strtoupper($arguments['order_number']))->lockForUpdate()->first()
                ?? throw new ToolException("No order found with number {$arguments['order_number']}.");

            $from = $order->status;
            $to = $arguments['status'];

            if (! $order->canTransitionTo($to)) {
                $allowed = implode(', ', Order::TRANSITIONS[$from]) ?: 'none';
                throw new ToolException("Order {$order->number} is {$from} and cannot move to {$to}. Allowed: {$allowed}.");
            }

            $order->update(['status' => $to]);
            $order->events()->create([
                'from_status' => $from,
                'to_status' => $to,
                'actor' => 'mcp:update_order_status',
                'note' => $arguments['note'] ?? null,
            ]);

            return ['number' => $order->number, 'previous_status' => $from, 'status' => $to, 'audited' => true];
        });
    }
}
