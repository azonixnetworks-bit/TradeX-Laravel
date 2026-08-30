<?php

namespace Tests\Unit\Admin\Legal;

use App\Http\Requests\Admin\Legal\PublishLegalPolicyRequest;
use Tests\TestCase;

class PublishLegalPolicyRequestTimezoneTest extends TestCase
{
    public function test_sri_lanka_datetime_local_is_normalized_to_utc(): void
    {
        config()->set(
            'tradex_legal.admin_timezone',
            'Asia/Colombo'
        );

        config()->set(
            'app.timezone',
            'UTC'
        );

        $request =
            new class extends PublishLegalPolicyRequest
            {
                public function normalizeForTest(): void
                {
                    $this->prepareForValidation();
                }
            };

        $request->replace([
            'effective_at' =>
                '2026-08-30T10:30',

            'publish_confirmation' =>
                '1',
        ]);

        $request->normalizeForTest();

        $this->assertSame(
            '2026-08-30 05:00:00',
            $request->input(
                'effective_at'
            )
        );

        $this->assertTrue(
            $request->boolean(
                'publish_confirmation'
            )
        );
    }

    public function test_blank_effective_time_remains_null_for_immediate_publication(): void
    {
        $request =
            new class extends PublishLegalPolicyRequest
            {
                public function normalizeForTest(): void
                {
                    $this->prepareForValidation();
                }
            };

        $request->replace([
            'effective_at' =>
                '   ',

            'publish_confirmation' =>
                '1',
        ]);

        $request->normalizeForTest();

        $this->assertNull(
            $request->input(
                'effective_at'
            )
        );
    }

    public function test_invalid_datetime_is_left_for_laravel_date_validation(): void
    {
        $request =
            new class extends PublishLegalPolicyRequest
            {
                public function normalizeForTest(): void
                {
                    $this->prepareForValidation();
                }
            };

        $request->replace([
            'effective_at' =>
                'not-a-date',

            'publish_confirmation' =>
                '1',
        ]);

        $request->normalizeForTest();

        $this->assertSame(
            'not-a-date',
            $request->input(
                'effective_at'
            )
        );
    }
}
