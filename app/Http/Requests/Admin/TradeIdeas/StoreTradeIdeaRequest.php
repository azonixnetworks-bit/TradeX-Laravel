<?php

namespace App\Http\Requests\Admin\TradeIdeas;

class StoreTradeIdeaRequest extends TradeIdeaPayloadRequest
{
    protected function requiredPermission(): string
    {
        return 'trade_ideas.create';
    }
}
