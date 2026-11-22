<?php

namespace App\Agent;

use Closure;

/**
 * A fake LLM for demo mode and tests. It pattern-matches the question,
 * makes the same MCP tool calls a real model would, and writes its reply
 * from the real tool results. No network, no API key.
 */
class ScriptedLlmClient implements LlmClient
{
    public function respond(string $system, string $prompt, array $tools, Closure $callTool): string
    {
        $text = strtolower($prompt);
        preg_match('/al-\d{4}/i', $prompt, $order);
        $orderNumber = isset($order[0]) ? strtoupper($order[0]) : null;

        return match (true) {
            $orderNumber !== null && preg_match('/\b(ship|shipped|mark|cancel|deliver|delivered|paid)\b/', $text) === 1 => $this->changeStatus($callTool, $orderNumber, $text),
            $orderNumber !== null => $this->orderStatus($callTool, $orderNumber),
            str_contains($text, 'stock') || str_contains($text, 'restock') || str_contains($text, 'running low') => $this->lowStock($callTool),
            preg_match('/\b(sales|revenue|sold|selling)\b/', $text) === 1 => $this->sales($callTool, $text),
            preg_match('/\b(find|search|show|under|cushion|cushions|throw|throws|basket|baskets|napkin|runner)\b/', $text) === 1 => $this->search($callTool, $text),
            default => "I'm running in demo mode with scripted replies. Try asking which products are low on stock, "
                .'how sales looked this week, the status of an order like AL-1042, or to find indigo cushions under ₦20,000.',
        };
    }

    private function lowStock(Closure $callTool): string
    {
        $data = $this->data($callTool('list_low_stock', ['threshold' => 5]));

        if ($data === null || $data['count'] === 0) {
            return 'Nothing is at or below 5 units right now.';
        }

        $lines = collect($data['products'])->map(fn ($p) => "- {$p['name']}: {$p['stock']} left")->implode("\n");

        return "{$data['count']} products are at or below {$data['threshold']} units:\n{$lines}\n\nThe ones with 1–2 left will sell out first.";
    }

    private function sales(Closure $callTool, string $text): string
    {
        $days = preg_match('/(\d+)\s*days?/', $text, $m) === 1 ? min(90, max(1, (int) $m[1])) : (str_contains($text, 'month') ? 30 : 7);
        $data = $this->data($callTool('sales_summary', ['days' => $days]));

        if ($data === null) {
            return 'I could not load the sales summary.';
        }

        $top = $data['top_product'] ? " Best seller: {$data['top_product']['name']} ({$data['top_product']['units']} units)." : '';

        return "In the last {$data['days']} days: {$data['orders']} orders worth ".$this->naira($data['revenue_naira'])
            .', an average of '.$this->naira($data['average_order_naira']).' per order.'.$top;
    }

    private function orderStatus(Closure $callTool, string $number): string
    {
        $result = $callTool('get_order', ['order_number' => $number]);
        $data = $this->data($result);

        if ($data === null) {
            return $this->errorText($result);
        }

        $items = collect($data['items'])->map(fn ($i) => "{$i['quantity']} × {$i['product']}")->implode(', ');
        $next = $data['allowed_next_statuses'] ? ' It can move to: '.implode(' or ', $data['allowed_next_statuses']).'.' : '';

        return "{$data['number']} for {$data['customer']['name']} ({$data['customer']['city']}) is **{$data['status']}**. "
            ."Placed {$data['placed_at']}: {$items}, total ".$this->naira($data['total_naira']).'.'.$next;
    }

    private function changeStatus(Closure $callTool, string $number, string $text): string
    {
        $target = match (true) {
            str_contains($text, 'cancel') => 'cancelled',
            str_contains($text, 'deliver') => 'delivered',
            str_contains($text, 'paid') => 'paid',
            default => 'shipped',
        };

        $order = $this->data($callTool('get_order', ['order_number' => $number]));
        if ($order === null) {
            return "I couldn't find order {$number}.";
        }

        $result = $callTool('update_order_status', [
            'order_number' => $number,
            'status' => $target,
            'note' => 'Requested in the MCP console',
        ]);
        $data = $this->data($result);

        if ($data === null) {
            $error = $this->errorText($result);

            return str_contains($error, 'Unknown tool')
                ? 'I can only read data here: write tools are turned off on this server (MCP_ALLOW_WRITES=false).'
                : "I didn't change anything. {$error}";
        }

        return "Done. {$data['number']} for {$order['customer']['name']} moved from {$data['previous_status']} to **{$data['status']}**, "
            .'and the change is recorded in the audit log.';
    }

    private function search(Closure $callTool, string $text): string
    {
        $known = ['indigo', 'rust', 'cushion', 'throw', 'basket', 'napkin', 'runner', 'wall', 'raffia', 'adire', 'aso-oke'];
        $words = array_values(array_filter($known, fn ($w) => str_contains($text, $w)));
        $query = $words ? implode(' ', $words) : 'adire';

        $arguments = ['query' => $query];
        if (preg_match('/(?:under|below|less than)\s*₦?\s*([\d,]+)\s*(k)?/u', $text, $m) === 1) {
            $arguments['max_price'] = (int) str_replace(',', '', $m[1]) * (isset($m[2]) && $m[2] === 'k' ? 1000 : 1);
        }

        $data = $this->data($callTool('search_products', $arguments));
        if ($data === null || $data['count'] === 0) {
            return "I couldn't find products matching \"{$query}\"".(isset($arguments['max_price']) ? ' in that price range.' : '.');
        }

        $lines = collect($data['products'])
            ->map(fn ($p) => "- {$p['name']}: ".$this->naira($p['price_naira'])." ({$p['stock']} in stock)")
            ->implode("\n");

        return "I found {$data['count']}:\n{$lines}";
    }

    private function data(array $result): ?array
    {
        return ($result['isError'] ?? false) ? null : ($result['structuredContent'] ?? null);
    }

    private function errorText(array $result): string
    {
        return collect($result['content'] ?? [])->pluck('text')->implode(' ');
    }

    private function naira(int $amount): string
    {
        return '₦'.number_format($amount);
    }
}
