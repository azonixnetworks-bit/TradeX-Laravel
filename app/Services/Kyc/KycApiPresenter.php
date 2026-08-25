<?php

namespace App\Services\Kyc;

use App\Models\KycDocument;
use App\Models\KycSubmission;

class KycApiPresenter
{
    public function status(
        ?KycSubmission $submission
    ): array {
        if (
            ! $submission
            instanceof KycSubmission
        ) {
            return [
                'status' =>
                    (string) config(
                        'tradex_kyc.statuses.not_started',
                        'NOT_STARTED'
                    ),

                'can_submit' => true,

                'submission' => null,
            ];
        }

        return [
            'status' =>
                $submission->status,

            'can_submit' =>
                $submission
                    ->canBeResubmitted(),

            'submission' =>
                $this->submission(
                    $submission
                ),
        ];
    }

    public function submission(
        KycSubmission $submission
    ): array {
        $documents =
            $submission
                ->relationLoaded(
                    'documents'
                )
                ? $submission
                    ->documents
                : $submission
                    ->documents()
                    ->get();

        $rejectionReason = null;

        if (
            in_array(
                $submission->status,
                [
                    KycSubmission::STATUS_REJECTED,
                    KycSubmission::STATUS_NEEDS_RESUBMISSION,
                ],
                true
            )
        ) {
            $rejectionReason =
                $submission
                    ->rejection_reason;
        }

        return [
            'uuid' =>
                $submission->uuid,

            'country_code' =>
                $submission
                    ->country_code,

            'document_type' =>
                $submission
                    ->document_type,

            'status' =>
                $submission
                    ->status,

            'can_resubmit' =>
                $submission
                    ->canBeResubmitted(),

            'rejection_reason' =>
                $rejectionReason,

            'submitted_at' =>
                $submission
                    ->submitted_at
                    ?->toISOString(),

            'reviewed_at' =>
                $submission
                    ->reviewed_at
                    ?->toISOString(),

            'documents' =>
                $documents
                    ->map(
                        fn (
                            KycDocument $document
                        ): array =>
                            $this->document(
                                $document
                            )
                    )
                    ->values()
                    ->all(),
        ];
    }

    public function document(
        KycDocument $document
    ): array {
        return [
            'uuid' =>
                $document->uuid,

            'kind' =>
                $document
                    ->document_kind,

            'mime_type' =>
                $document
                    ->mime_type,

            'size_bytes' =>
                (int) $document
                    ->size_bytes,
        ];
    }
}
