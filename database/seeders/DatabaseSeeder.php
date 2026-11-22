<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Demo data for "Adire Lane", a fictional Lagos homeware and textile shop.
 * Deterministic on purpose so the demo, screenshots and tests are repeatable.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $products = collect([
            ['AL-CUS-01', 'Indigo adire cushion cover', 'Cushions', 14500, 3],
            ['AL-CUS-02', 'Rust tie-dye cushion cover', 'Cushions', 13000, 18],
            ['AL-CUS-03', 'Indigo kampala floor cushion', 'Cushions', 32000, 6],
            ['AL-THR-01', 'Aso-oke throw blanket', 'Throws', 48000, 2],
            ['AL-THR-02', 'Indigo cotton throw', 'Throws', 27500, 11],
            ['AL-TAB-01', 'Adire table runner', 'Table', 9500, 24],
            ['AL-TAB-02', 'Set of 4 indigo napkins', 'Table', 8000, 4],
            ['AL-BAS-01', 'Woven raffia basket (large)', 'Baskets', 21000, 9],
            ['AL-BAS-02', 'Woven raffia basket (small)', 'Baskets', 12500, 1],
            ['AL-WAL-01', 'Framed adire wall panel', 'Wall art', 65000, 5],
        ])->mapWithKeys(fn ($p) => [$p[0] => Product::create([
            'sku' => $p[0], 'name' => $p[1], 'category' => $p[2], 'price' => $p[3], 'stock' => $p[4],
        ])]);

        $customers = collect([
            ['Amaka Eze', 'amaka@example.com', 'Lagos'],
            ['Tunde Bakare', 'tunde@example.com', 'Ibadan'],
            ['Zainab Musa', 'zainab@example.com', 'Abuja'],
            ['Chidi Okafor', 'chidi@example.com', 'Enugu'],
            ['Funke Adeyemi', 'funke@example.com', 'Lagos'],
            ['Ifeoma Nwosu', 'ifeoma@example.com', 'Port Harcourt'],
        ])->map(fn ($c) => Customer::create(['name' => $c[0], 'email' => $c[1], 'city' => $c[2]]));

        // [number, customer index, status, days ago, [sku => qty]]
        $orders = [
            ['AL-1031', 1, 'delivered', 13, ['AL-THR-02' => 1]],
            ['AL-1032', 3, 'delivered', 12, ['AL-TAB-01' => 2, 'AL-TAB-02' => 1]],
            ['AL-1033', 0, 'delivered', 10, ['AL-WAL-01' => 1]],
            ['AL-1034', 4, 'cancelled', 9, ['AL-BAS-01' => 1]],
            ['AL-1035', 2, 'delivered', 8, ['AL-CUS-02' => 2]],
            ['AL-1036', 5, 'delivered', 6, ['AL-CUS-01' => 2, 'AL-TAB-01' => 1]],
            ['AL-1037', 1, 'shipped', 5, ['AL-THR-01' => 1]],
            ['AL-1038', 0, 'shipped', 4, ['AL-BAS-01' => 2]],
            ['AL-1039', 3, 'paid', 3, ['AL-CUS-03' => 1, 'AL-CUS-02' => 1]],
            ['AL-1040', 4, 'paid', 2, ['AL-TAB-02' => 2]],
            ['AL-1041', 2, 'pending', 2, ['AL-BAS-02' => 1]],
            ['AL-1042', 0, 'paid', 1, ['AL-CUS-01' => 2, 'AL-THR-02' => 1]],
            ['AL-1043', 5, 'pending', 1, ['AL-WAL-01' => 1]],
            ['AL-1044', 1, 'paid', 0, ['AL-TAB-01' => 3]],
        ];

        foreach ($orders as [$number, $customer, $status, $daysAgo, $lines]) {
            $total = collect($lines)->map(fn ($qty, $sku) => $products[$sku]->price * $qty)->sum();

            $order = Order::create([
                'number' => $number,
                'customer_id' => $customers[$customer]->id,
                'status' => $status,
                'total' => $total,
                'placed_at' => Carbon::now()->subDays($daysAgo)->setTime(10 + $daysAgo % 8, 15),
            ]);

            foreach ($lines as $sku => $qty) {
                $order->items()->create([
                    'product_id' => $products[$sku]->id,
                    'quantity' => $qty,
                    'unit_price' => $products[$sku]->price,
                ]);
            }
        }
    }
}
