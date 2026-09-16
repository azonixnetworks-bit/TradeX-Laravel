<?php

namespace Database\Seeders;

use App\Models\AdminPermission;
use Illuminate\Database\Seeder;

class EconomicNewsPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            ['economic_news.view', 'View Economic News Intelligence'],
            ['economic_news.events.manage', 'Manage Economic Events'],
            ['economic_news.pairs.manage', 'Manage Affected Pair Mapping'],
            ['economic_news.live_updates.manage', 'Manage Economic News Live Updates'],
            ['economic_news.overrides.manage', 'Manage Economic News Manual Overrides'],
            ['economic_news.sources.manage', 'Manage Economic News Sources'],
            ['economic_news.publish', 'Publish Economic News Intelligence'],
        ];

        foreach ($permissions as [$code, $name]) {
            AdminPermission::updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'module' => 'ECONOMIC_NEWS',
                    'description' => null,
                ]
            );
        }
    }
}
