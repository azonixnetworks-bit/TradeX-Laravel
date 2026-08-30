<?php

namespace App\Http\Requests\Admin\Legal;

use App\Models\AdminUser;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Throwable;

class PublishLegalPolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $admin = auth('admin')->user();

        return $admin instanceof AdminUser
            && $admin->hasPermission(
                'legal.view'
            )
            && $admin->hasPermission(
                'legal.publish'
            );
    }

    protected function prepareForValidation(): void
    {
        $effectiveAt =
            $this->normalizeEffectiveAt(
                $this->input(
                    'effective_at'
                )
            );

        $this->merge([
            'effective_at' =>
                $effectiveAt,

            'publish_confirmation' =>
                filter_var(
                    $this->input(
                        'publish_confirmation',
                        false
                    ),
                    FILTER_VALIDATE_BOOL
                ),
        ]);
    }

    public function rules(): array
    {
        return [
            'effective_at' => [
                'nullable',
                'date',
            ],

            'revision_version' => [
                'required',
                'integer',
                'min:0',
            ],

            'publish_confirmation' => [
                'required',
                'accepted',
            ],
        ];
    }

    private function normalizeEffectiveAt(
        mixed $value
    ): ?string {
        if (
            ! is_scalar($value)
            || trim((string) $value) === ''
        ) {
            return null;
        }

        $raw =
            trim(
                (string) $value
            );

        try {
            $adminTimezone =
                (string) config(
                    'tradex_legal.admin_timezone',
                    'Asia/Colombo'
                );

            $applicationTimezone =
                (string) config(
                    'app.timezone',
                    'UTC'
                );

            return CarbonImmutable::parse(
                $raw,
                $adminTimezone
            )
                ->setTimezone(
                    $applicationTimezone
                )
                ->format(
                    'Y-m-d H:i:s'
                );
        } catch (Throwable) {
            /*
             * Preserve invalid input so the normal Laravel `date` validation
             * rule returns a validation error instead of turning it into a 500.
             */
            return $raw;
        }
    }
}
