<?php

namespace Database\Seeders;

use App\Models\AdminRole;
use App\Models\AdminUser;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $name = config('tradex.super_admin.name');
        $email = config('tradex.super_admin.email');
        $password = config('tradex.super_admin.password');

        if (blank($password)) {
            throw new RuntimeException(
                'SUPER_ADMIN_PASSWORD is missing from .env'
            );
        }

        $admin = AdminUser::firstOrNew([
            'email' => $email,
        ]);

        if (! $admin->exists) {
            $admin->uuid = Str::uuid()->toString();
        }

        $admin->name = $name;
        $admin->email_verified_at = now();
        $admin->password = Hash::make($password);
        $admin->is_active = true;

        $admin->save();

        $superAdminRole = AdminRole::where(
            'code',
            'SUPER_ADMIN'
        )->firstOrFail();

        $admin->roles()->syncWithoutDetaching([
            $superAdminRole->id,
        ]);
    }
}