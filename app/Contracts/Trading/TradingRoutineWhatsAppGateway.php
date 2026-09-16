<?php

namespace App\Contracts\Trading;

interface TradingRoutineWhatsAppGateway
{
    /**
     * @return array{provider:string,message_id:string,media_id:string}
     */
    public function sendPdf(
        string $recipient,
        string $pdfBytes,
        string $filename
    ): array;
}
