<?php

namespace App\Http\Requests\Admin\Integrations;

use App\Models\AdminUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateIntegrationProviderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $admin = auth('admin')->user();

        return $admin instanceof AdminUser
            && $admin->hasPermission(
                'integrations.view'
            )
            && $admin->hasPermission(
                'integrations.manage'
            );
    }

    protected function prepareForValidation(): void
    {
        $countriesRaw =
            $this->input(
                'countries'
            );

        if (is_string($countriesRaw)) {
            $countries =
                array_values(
                    array_unique(
                        array_filter(
                            array_map(
                                static fn (
                                    string $country
                                ): string =>
                                    strtoupper(
                                        trim($country)
                                    ),
                                explode(
                                    ',',
                                    $countriesRaw
                                )
                            )
                        )
                    )
                );
        } elseif (is_array($countriesRaw)) {
            $countries =
                array_values(
                    array_unique(
                        array_filter(
                            array_map(
                                static fn (
                                    mixed $country
                                ): string =>
                                    strtoupper(
                                        trim(
                                            (string) $country
                                        )
                                    ),
                                $countriesRaw
                            )
                        )
                    )
                );
        } else {
            $countries = [];
        }

        $this->merge([
            'is_enabled' =>
                filter_var(
                    $this->input(
                        'is_enabled',
                        false
                    ),
                    FILTER_VALIDATE_BOOL
                ),

            'eligibility_confirmed' =>
                filter_var(
                    $this->input(
                        'eligibility_confirmed',
                        false
                    ),
                    FILTER_VALIDATE_BOOL
                ),

            'environment' =>
                strtoupper(
                    trim(
                        (string) $this->input(
                            'environment'
                        )
                    )
                ),

            'countries' =>
                $countries,

            'change_reason' =>
                trim(
                    (string) $this->input(
                        'change_reason'
                    )
                ),
        ]);
    }

    public function rules(): array
    {
        return [
            'is_enabled' => [
                'required',
                'boolean',
            ],

            'eligibility_confirmed' => [
                'required',
                'boolean',
            ],

            'environment' => [
                'required',
                Rule::in([
                    'SANDBOX',
                    'LIVE',
                    'MANUAL',
                ]),
            ],

            'countries' => [
                'present',
                'array',
                'max:50',
            ],

            'countries.*' => [
                'required',
                'string',
                'max:2',
                'distinct',
                function (
                    string $attribute,
                    mixed $value,
                    \Closure $fail
                ): void {
                    $country = strtoupper(
                        trim((string) $value)
                    );

                    $providerCode = strtoupper(
                        trim(
                            (string) $this->route(
                                'providerCode'
                            )
                        )
                    );

                    if (
                        $providerCode === 'STRIPE'
                        && $country === '*'
                    ) {
                        return;
                    }

                    if (
                        preg_match(
                            '/^[A-Z]{2}$/',
                            $country
                        ) !== 1
                    ) {
                        $fail(
                            'Country scope must use ISO 2-letter codes; Stripe may use * for all customer countries.'
                        );
                    }
                },
            ],

            'version' => [
                'required',
                'integer',
                'min:0',
            ],

            'change_reason' => [
                'required',
                'string',
                'min:8',
                'max:500',
            ],
        ];
    }
}
