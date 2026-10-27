<?php

namespace Database\Seeders;

use App\Models\SupportCategory;
use App\Models\SupportDepartment;
use App\Models\SupportSlaPolicy;
use Illuminate\Database\Seeder;

class SupportSystemSeeder extends Seeder
{
    public function run(): void
    {
        $department = SupportDepartment::updateOrCreate(
            ['slug' => 'customer-support'],
            ['name' => 'Customer Support', 'timezone' => config('support.timezone'), 'business_hours' => ['weekdays' => [1, 2, 3, 4, 5], 'opens' => '09:00', 'closes' => '17:00'], 'enabled' => true]
        );

        $categories = [
            ['account-login', 'Account & Login'], ['subscription-payments', 'Subscription & Payments'],
            ['copy-trading-ctrader', 'Copy Trading / cTrader'], ['funded-account-advisor', 'Funded Account Advisor'],
            ['trade-ideas-signals', 'Trade Ideas & Signals'], ['learning-assignments', 'Learning & Assignments'],
            ['affiliate-commission', 'Affiliate & Commission'], ['technical-bugs', 'Technical Bugs'],
            ['other-enquiries', 'Other Enquiries'],
        ];
        foreach ($categories as $order => [$slug, $label]) {
            SupportCategory::updateOrCreate(['slug' => $slug], ['department_id' => $department->id, 'label' => $label, 'intake_schema' => [], 'schema_version' => 1, 'sort_order' => $order, 'enabled' => true]);
        }

        foreach ([
            'critical' => [15, 240], 'high' => [60, 480], 'normal' => [240, 1440], 'low' => [480, 2880],
        ] as $priority => [$response, $resolution]) {
            SupportSlaPolicy::updateOrCreate(['department_id' => $department->id, 'priority' => $priority], ['first_response_minutes' => $response, 'resolution_minutes' => $resolution, 'enabled' => true]);
        }
    }
}
