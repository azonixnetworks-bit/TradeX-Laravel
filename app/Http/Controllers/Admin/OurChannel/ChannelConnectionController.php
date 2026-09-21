<?php

namespace App\Http\Controllers\Admin\OurChannel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\OurChannel\UpdateTelegramChannelConnectionRequest;
use App\Models\AdminUser;
use App\Services\Admin\OurChannel\AdminChannelConnectionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

class ChannelConnectionController extends Controller
{
    public function index(
        AdminChannelConnectionService $service
    ): Response {
        $admin = $this->admin();

        abort_unless(
            $admin->hasPermission(
                'our_channel.view'
            ),
            403
        );

        return response()->view(
            'admin.our_channel.connections',
            [
                ...$service->index(),
                'canManage' =>
                    $admin->hasPermission(
                        'our_channel.manage'
                    ),
            ],
            200,
            [
                'Cache-Control' =>
                    'private, no-store, no-cache, max-age=0, must-revalidate',
                'Pragma' => 'no-cache',
                'X-Content-Type-Options' => 'nosniff',
                'Referrer-Policy' => 'no-referrer',
                'X-Frame-Options' => 'DENY',
                'X-Robots-Tag' =>
                    'noindex, nofollow, noarchive',
            ]
        );
    }

    public function updateTelegram(
        UpdateTelegramChannelConnectionRequest $request,
        AdminChannelConnectionService $service
    ): RedirectResponse {
        $admin = $this->admin();

        abort_unless(
            $admin->hasPermission(
                'our_channel.manage'
            ),
            403
        );

        $telegram = $service->updateTelegram(
            $admin,
            $request->validated(),
            $request
        );

        return redirect()
            ->route(
                'admin.our_channel.connections.index'
            )
            ->with(
                'success',
                $telegram['last_test_status']
                    === 'SUCCESS'
                    ? (
                        $telegram['enabled']
                            ? 'Telegram Channel verified and automatic publishing is enabled.'
                            : 'Telegram Channel verified successfully. Enable auto publishing when ready.'
                    )
                    : 'Telegram Channel connection settings saved.'
            );
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
}
