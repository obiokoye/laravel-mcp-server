<?php

namespace App\Mcp\Tools;

use App\Mcp\Contracts\Tool;
use App\Models\Product;

class LowStock implements Tool
{
    public function name(): string
    {
        return 'list_low_stock';
    }

    public function title(): string
    {
        return 'List low stock';
    }

    public function description(): string
    {
        return 'List products whose stock is at or below a threshold (default 5), lowest first.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'threshold' => ['type' => 'integer', 'description' => 'Stock level to alert at (1-100, default 5)'],
            ],
        ];
    }

    public function rules(): array
    {
        return ['threshold' => ['nullable', 'integer', 'between:1,100']];
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function handle(array $arguments): array
    {
        $threshold = $arguments['threshold'] ?? 5;

        $products = Product::where('stock', '<=', $threshold)->orderBy('stock')->orderBy('name')->get();

        return [
            'threshold' => $threshold,
            'count' => $products->count(),
            'products' => $products->map(fn (Product $p) => [
                'sku' => $p->sku, 'name' => $p->name, 'stock' => $p->stock,
            ])->all(),
        ];
    }
}
