<?php

namespace App\Services\TradeJournal;

use App\Models\TradeJournalEntry;
use App\Models\TradeJournalReview;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;

class TradeJournalRetentionService
{
    public function pruneExpired(?User $user = null): int
    {
        $cutoff = CarbonImmutable::now('UTC')->subDays($this->retentionDays());
        $entriesDeleted = 0;
        $reviewsDeleted = 0;

        $entries = TradeJournalEntry::withTrashed()
            ->where('created_at', '<', $cutoff);
        $reviews = TradeJournalReview::withTrashed()
            ->where('created_at', '<', $cutoff);

        if ($user !== null) {
            $entries->where('user_id', $user->getKey());
            $reviews->where('user_id', $user->getKey());
        }

        $entries->chunkById(100, function ($chunk) use (&$entriesDeleted): void {
            foreach ($chunk as $entry) {
                $this->deleteScreenshots($entry);
                $entry->forceDelete();
                $entriesDeleted++;
            }
        });

        $reviews->chunkById(100, function ($chunk) use (&$reviewsDeleted): void {
            foreach ($chunk as $review) {
                $review->forceDelete();
                $reviewsDeleted++;
            }
        });

        return $entriesDeleted + $reviewsDeleted;
    }

    public function retentionDays(): int
    {
        return max(1, (int) config('trade_journal.retention_days', 45));
    }

    private function deleteScreenshots(TradeJournalEntry $entry): void
    {
        $paths = array_values(array_filter([
            $entry->before_screenshot_path,
            $entry->after_screenshot_path,
        ]));

        if ($paths === []) {
            return;
        }

        Storage::disk(
            (string) config('trade_journal.screenshots.disk', 'private')
        )->delete($paths);
    }
}
