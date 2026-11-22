<?php

namespace App\Mcp\Prompts;

use App\Mcp\Contracts\Prompt;

class DraftCustomerReply implements Prompt
{
    public function name(): string
    {
        return 'draft_customer_reply';
    }

    public function description(): string
    {
        return 'Draft a friendly reply to a customer asking about their order.';
    }

    public function arguments(): array
    {
        return [
            ['name' => 'order_number', 'description' => 'The order the customer is asking about', 'required' => true],
            ['name' => 'question', 'description' => 'What the customer asked', 'required' => false],
        ];
    }

    public function messages(array $arguments): array
    {
        $question = $arguments['question'] ?? 'Where is my order?';

        return [[
            'role' => 'user',
            'content' => [
                'type' => 'text',
                'text' => "A customer asked: \"{$question}\" about order {$arguments['order_number']}. "
                    .'Call get_order for the facts and read shop://policies/returns if it is about returns. '
                    .'Then write a short, warm reply signed "Adire Lane". Never invent dates or amounts.',
            ],
        ]];
    }
}
