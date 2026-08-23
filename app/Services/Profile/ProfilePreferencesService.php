<?php

namespace App\Services\Profile;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;

class ProfilePreferencesService
{
    public function updateLanguage(
        User $user,
        string $languageCode,
        Request $request
    ): void {
        $old =
            $user->language_code;

        if ($old === $languageCode) {
            return;
        }

        $user->forceFill([
            'language_code' =>
                $languageCode,
        ])->save();

        AuditLog::record(
            'USER',
            $user->getKey(),
            'PROFILE_LANGUAGE_UPDATED',
            'PROFILE',
            'USER',
            $user->getKey(),
            [
                'language_code' =>
                    $old,
            ],
            [
                'language_code' =>
                    $languageCode,
            ],
            $request->ip(),
            $request->userAgent()
        );
    }
}
