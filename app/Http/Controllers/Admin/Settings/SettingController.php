<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Settings\UpdateGeneralSettingsRequest;
use App\Models\AdminUser;
use App\Services\Admin\Settings\AdminSystemSettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

class SettingController extends Controller
{
    public function home(): RedirectResponse
    {
        $admin = $this->admin();

        abort_unless(
            $admin->hasPermission(
                'settings.view'
            ),
            403
        );

        return redirect()->route(
            'admin.settings.general'
        );
    }

    public function general(
        AdminSystemSettingService $service
    ): Response {
        $admin = $this->viewer();

        $data =
            $service->general();

        $data['canManage'] =
            $admin->hasPermission(
                'settings.manage'
            );

        return $this->view(
            'admin.settings.general',
            $data
        );
    }

    public function updateGeneral(
        UpdateGeneralSettingsRequest $request,
        AdminSystemSettingService $service
    ): RedirectResponse {
        $admin = $this->admin();

        abort_unless(
            $admin->hasPermission(
                'settings.view'
            )
            && $admin->hasPermission(
                'settings.manage'
            ),
            403
        );

        $result =
            $service->updateGeneral(
                $admin,
                $request->validated(),
                $request
            );

        return redirect()
            ->route(
                'admin.settings.general'
            )
            ->with(
                'success',
                $result['changed_keys'] === []
                    ? 'No setting changes were detected.'
                    : 'General settings updated securely.'
            );
    }

    public function affiliate(
        AdminSystemSettingService $service
    ): Response {
        $this->viewer();

        return $this->view(
            'admin.settings.affiliate',
            [
                'snapshot' =>
                    $service
                        ->affiliateSnapshot(),
            ]
        );
    }

    public function payments(
        AdminSystemSettingService $service
    ): Response {
        $this->viewer();

        return $this->view(
            'admin.settings.payments',
            [
                'snapshot' =>
                    $service
                        ->paymentSnapshot(),
            ]
        );
    }

    public function security(
        AdminSystemSettingService $service
    ): Response {
        $this->viewer();

        return $this->view(
            'admin.settings.security',
            [
                'snapshot' =>
                    $service
                        ->securitySnapshot(),
            ]
        );
    }

    public function video(
        AdminSystemSettingService $service
    ): Response {
        $this->viewer();

        return $this->view(
            'admin.settings.video',
            [
                'snapshot' =>
                    $service
                        ->videoSnapshot(),
            ]
        );
    }

    private function viewer(): AdminUser
    {
        $admin = $this->admin();

        abort_unless(
            $admin->hasPermission(
                'settings.view'
            ),
            403
        );

        return $admin;
    }

    private function admin(): AdminUser
    {
        $admin = auth('admin')->user();

        abort_unless(
            $admin instanceof AdminUser,
            401
        );

        return $admin;
    }

    private function view(
        string $view,
        array $data
    ): Response {
        return response()->view(
            $view,
            $data,
            200,
            [
                'Cache-Control' =>
                    'private, no-store, no-cache, max-age=0, must-revalidate',

                'Pragma' =>
                    'no-cache',

                'X-Content-Type-Options' =>
                    'nosniff',

                'Referrer-Policy' =>
                    'no-referrer',

                'X-Frame-Options' =>
                    'DENY',

                'X-Robots-Tag' =>
                    'noindex, nofollow, noarchive',
            ]
        );
    }
}
