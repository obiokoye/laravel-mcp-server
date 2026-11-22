<?php

namespace App\Mcp\Tools;

use App\Mcp\Contracts\Tool;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

class SalesSummary implements Tool
{
    public function name(): string
    {
        return 'sales_summary';
    }

    public function title(): string
    {
        return 'Sales summary';
    }

    public function description(): string
    {
        return 'Revenue, order count and best-selling product for the last N days (default 7). Cancelled orders are excluded.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'days' => ['type' => 'integer', 'description' => 'How many days back to include (1-90, default 7)'],
            ],
        ];
    }

    public function rules(): array
    {
        return ['days' => ['nullable', 'integer', 'between:1,90']];
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function handle(array $arguments): array
    {
        $days = $arguments['days'] ?? 7;
        $since = now()->subDays($days)->startOfDay();

        $orders = Order::where('placed_at', '>=', $since)->where('status', '!=', 'cancelled');

        $top = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->where('orders.placed_at', '>=', $since)
            ->where('orders.status', '!=', 'cancelled')
            ->groupBy('products.name')
            ->selectRaw('products.name as name, sum(order_items.quantity) as units')
            ->orderByDesc('units')
            ->orderBy('products.name')
            ->first();

        $count = (clone $orders)->count();
        $revenue = (int) (clone $orders)->sum('total');

        return [
            'days' => $days,
            'since' => $since->toDateString(),
            'orders' => $count,
            'revenue_naira' => $revenue,
            'average_order_naira' => $count > 0 ? intdiv($revenue, $count) : 0,
            'top_product' => $top ? ['name' => $top->name, 'units' => (int) $top->units] : null,
        ];
    }
}
