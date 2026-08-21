<?php

namespace App\Http\Requests\Admin\TradeIdeas;

class UpdateTradeIdeaRequest extends TradeIdeaPayloadRequest
{
    protected function requiredPermission(): string
    {
        return 'trade_ideas.update';
    }
}
