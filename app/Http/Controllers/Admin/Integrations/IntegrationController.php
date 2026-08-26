<?php

namespace App\Http\Controllers\Admin\Integrations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Integrations\UpdateIntegrationProviderRequest;
use App\Models\AdminUser;
use App\Services\Admin\Integrations\AdminIntegrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

class IntegrationController extends Controller
{
    public function index(
        AdminIntegrationService $service
    ): Response {
        $admin =
            $this->viewer();

        return $this->view(
            'admin.integrations.index',
            array_merge(
                $service->index(),
                [
                    'canManage' =>
                        $admin->hasPermission(
                            'integrations.manage'
                        ),
                ]
            )
        );
    }

    public function show(
        string $providerCode,
        AdminIntegrationService $service
    ): Response {
        $admin =
            $this->viewer();

        return $this->view(
            'admin.integrations.show',
            array_merge(
                $service->show(
                    $providerCode
                ),
                [
                    'canManage' =>
                        $admin->hasPermission(
                            'integrations.manage'
                        ),
                ]
            )
        );
    }

    public function update(
        UpdateIntegrationProviderRequest $request,
        string $providerCode,
        AdminIntegrationService $service
    ): RedirectResponse {
        $admin =
            $this->manager();

        $provider =
            $service->update(
                $admin,
                $providerCode,
                $request->validated(),
                $request
            );

        return redirect()
            ->route(
                'admin.integrations.show',
                $provider['code']
            )
            ->with(
                'success',
                'Integration control-plane settings updated.'
            );
    }

    private function viewer(): AdminUser
    {
        $admin =
            $this->admin();

        abort_unless(
            $admin->hasPermission(
                'integrations.view'
            ),
            403
        );

        return $admin;
    }

    private function manager(): AdminUser
    {
        $admin =
            $this->admin();

        abort_unless(
            $admin->hasPermission(
                'integrations.view'
            )
            && $admin->hasPermission(
                'integrations.manage'
            ),
            403
        );

        return $admin;
    }

    private function admin(): AdminUser
    {
        $admin =
            auth('admin')->user();

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
