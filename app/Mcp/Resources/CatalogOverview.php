<?php

namespace App\Mcp\Resources;

use App\Mcp\Contracts\Resource;
use App\Models\Product;

class CatalogOverview implements Resource
{
    public function uri(): string
    {
        return 'shop://catalog/overview';
    }

    public function name(): string
    {
        return 'Catalogue overview';
    }

    public function description(): string
    {
        return 'Live product counts, price range and stock per category.';
    }

    public function mimeType(): string
    {
        return 'application/json';
    }

    public function read(): string
    {
        $categories = Product::query()
            ->selectRaw('category, count(*) as products, min(price) as min_price, max(price) as max_price, sum(stock) as stock')
            ->groupBy('category')
            ->orderBy('category')
            ->get()
            ->map(fn ($row) => [
                'category' => $row->category,
                'products' => (int) $row->products,
                'price_range_naira' => [(int) $row->min_price, (int) $row->max_price],
                'units_in_stock' => (int) $row->stock,
            ]);

        return json_encode(['categories' => $categories], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}
