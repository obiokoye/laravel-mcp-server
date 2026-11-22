<?php

use App\Mcp\Prompts\DraftCustomerReply;
use App\Mcp\Resources\CatalogOverview;
use App\Mcp\Resources\ReturnsPolicy;
use App\Mcp\Tools\GetOrder;
use App\Mcp\Tools\LowStock;
use App\Mcp\Tools\SalesSummary;
use App\Mcp\Tools\SearchProducts;
use App\Mcp\Tools\UpdateOrderStatus;

return [
    'name' => 'adire-lane-shop',
    'version' => '1.0.0',

    // Bearer token for POST /mcp. Leave empty only on your own machine.
    'token' => env('MCP_TOKEN'),

    // Write tools (update_order_status) are hidden unless this is true.
    'allow_writes' => (bool) env('MCP_ALLOW_WRITES', false),

    'tools' => [
        SearchProducts::class,
        GetOrder::class,
        LowStock::class,
        SalesSummary::class,
        UpdateOrderStatus::class,
    ],

    'resources' => [
        ReturnsPolicy::class,
        CatalogOverview::class,
    ],

    'prompts' => [
        DraftCustomerReply::class,
    ],
];
