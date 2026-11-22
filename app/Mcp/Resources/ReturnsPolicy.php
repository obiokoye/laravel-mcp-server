<?php

namespace App\Mcp\Resources;

use App\Mcp\Contracts\Resource;

class ReturnsPolicy implements Resource
{
    public function uri(): string
    {
        return 'shop://policies/returns';
    }

    public function name(): string
    {
        return 'Returns policy';
    }

    public function description(): string
    {
        return 'The shop\'s returns and refunds policy, for answering customer questions.';
    }

    public function mimeType(): string
    {
        return 'text/markdown';
    }

    public function read(): string
    {
        return <<<'MD'
        # Adire Lane returns policy

        - Returns are accepted within 14 days of delivery, unused and in original packaging.
        - Hand-dyed items vary slightly in colour; this is not a fault.
        - Refunds go back to the original payment method within 5 working days of the item arriving.
        - Orders can be cancelled free of charge until they are shipped.
        MD;
    }
}
