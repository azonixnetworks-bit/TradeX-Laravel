<?php

namespace App\Services\TradeIdeas;

use App\Models\TradeIdea;

class TradeIdeaStructureValidator
{
    /**
     * Every Trade Structure field is optional.
     *
     * When values are supplied, only the relationships that can be
     * meaningfully checked are validated.
     *
     * @param array<int, array{price:mixed}> $targets
     * @return array<string, string>
     */
    public function errors(
        string $direction,
        mixed $entryLow,
        mixed $entryHigh,
        mixed $stopLoss,
        array $targets
    ): array {
        $errors = [];

        $low = is_numeric($entryLow)
            ? (float) $entryLow
            : null;

        $high = is_numeric($entryHigh)
            ? (float) $entryHigh
            : null;

        $stop = is_numeric($stopLoss)
            ? (float) $stopLoss
            : null;

        if (
            $low !== null
            && $high !== null
            && $low > $high
        ) {
            $errors['entry_price_high'] =
                'Entry High must be greater than or equal to Entry Low.';
        }

        $normalizedTargets = [];

        foreach ($targets as $index => $target) {
            $price = $target['price'] ?? null;

            if (! is_numeric($price)) {
                continue;
            }

            $normalizedTargets[] = [
                'index' => $index,
                'price' => (float) $price,
            ];
        }

        if ($direction === TradeIdea::DIRECTION_BUY) {
            if (
                $stop !== null
                && $low !== null
                && $stop >= $low
            ) {
                $errors['stop_loss'] =
                    'For a BUY idea, Stop Loss must be below Entry Low when both are supplied.';
            }

            $previous = null;

            foreach ($normalizedTargets as $target) {
                if (
                    $high !== null
                    && $target['price'] <= $high
                ) {
                    $errors["targets.{$target['index']}.price"] =
                        'For a BUY idea, supplied targets must be above Entry High.';
                }

                if (
                    $previous !== null
                    && $target['price'] <= $previous
                ) {
                    $errors["targets.{$target['index']}.price"] =
                        'BUY targets must be entered in strictly ascending order.';
                }

                $previous = $target['price'];
            }
        }

        if ($direction === TradeIdea::DIRECTION_SELL) {
            if (
                $stop !== null
                && $high !== null
                && $stop <= $high
            ) {
                $errors['stop_loss'] =
                    'For a SELL idea, Stop Loss must be above Entry High when both are supplied.';
            }

            $previous = null;

            foreach ($normalizedTargets as $target) {
                if (
                    $low !== null
                    && $target['price'] >= $low
                ) {
                    $errors["targets.{$target['index']}.price"] =
                        'For a SELL idea, supplied targets must be below Entry Low.';
                }

                if (
                    $previous !== null
                    && $target['price'] >= $previous
                ) {
                    $errors["targets.{$target['index']}.price"] =
                        'SELL targets must be entered in strictly descending order.';
                }

                $previous = $target['price'];
            }
        }

        return $errors;
    }
}
