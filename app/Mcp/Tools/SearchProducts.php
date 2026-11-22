<?php

namespace App\Mcp\Tools;

use App\Mcp\Contracts\Tool;
use App\Models\Product;

class SearchProducts implements Tool
{
    public function name(): string
    {
        return 'search_products';
    }

    public function title(): string
    {
        return 'Search products';
    }

    public function description(): string
    {
        return 'Search the catalogue by keyword (name or category), optionally under a maximum price in naira. Returns up to 10 products with price and stock.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => ['type' => 'string', 'description' => 'Keyword, e.g. "indigo" or "cushion"'],
                'max_price' => ['type' => 'integer', 'description' => 'Optional maximum price in naira'],
            ],
            'required' => ['query'],
        ];
    }

    public function rules(): array
    {
        return [
            'query' => ['required', 'string', 'max:100'],
            'max_price' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function handle(array $arguments): array
    {
        // Every word must match the name or category: "indigo cushion" finds indigo cushions.
        $words = preg_split('/\s+/', trim($arguments['query'])) ?: [];

        $products = Product::query()
            ->where(function ($query) use ($words) {
                foreach ($words as $word) {
                    $like = '%'.str_replace(['%', '_'], ['\%', '\_'], rtrim(strtolower($word), 's')).'%';
                    $query->where(fn ($q) => $q->whereRaw('lower(name) like ?', [$like])->orWhereRaw('lower(category) like ?', [$like]));
                }
            })
            ->when(isset($arguments['max_price']), fn ($q) => $q->where('price', '<=', $arguments['max_price']))
            ->orderBy('price')
            ->limit(10)
            ->get();

        return [
            'count' => $products->count(),
            'products' => $products->map(fn (Product $p) => [
                'sku' => $p->sku,
                'name' => $p->name,
                'category' => $p->category,
                'price_naira' => $p->price,
                'stock' => $p->stock,
            ])->all(),
        ];
    }
}
