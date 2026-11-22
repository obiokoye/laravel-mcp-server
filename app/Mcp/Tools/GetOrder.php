<?php

namespace App\Mcp\Tools;

use App\Mcp\Contracts\Tool;
use App\Mcp\ToolException;
use App\Models\Order;
use App\Models\OrderItem;

class GetOrder implements Tool
{
    public function name(): string
    {
        return 'get_order';
    }

    public function title(): string
    {
        return 'Get order';
    }

    public function description(): string
    {
        return 'Look up one order by its number (e.g. AL-1042): status, customer, line items, total and the statuses it can move to next.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'order_number' => ['type' => 'string', 'description' => 'Order number such as AL-1042'],
            ],
            'required' => ['order_number'],
        ];
    }

    public function rules(): array
    {
        return ['order_number' => ['required', 'string', 'regex:/^AL-\d{4}$/i']];
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function handle(array $arguments): array
    {
        $order = Order::with(['customer', 'items.product'])
            ->where('number', strtoupper($arguments['order_number']))
            ->first() ?? throw new ToolException("No order found with number {$arguments['order_number']}.");

        return [
            'number' => $order->number,
            'status' => $order->status,
            'allowed_next_statuses' => Order::TRANSITIONS[$order->status] ?? [],
            'placed_at' => $order->placed_at->toDateString(),
            // Only what support needs: name and city, not the email address.
            'customer' => ['name' => $order->customer->name, 'city' => $order->customer->city],
            'items' => $order->items->map(fn (OrderItem $item) => [
                'product' => $item->product->name,
                'quantity' => $item->quantity,
                'unit_price_naira' => $item->unit_price,
            ])->all(),
            'total_naira' => $order->total,
        ];
    }
}
