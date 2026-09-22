<?php

namespace App\Http\Controllers\Admin\OurChannel;

use App\Http\Controllers\Controller;
use App\Exceptions\ChannelConnectionException;
use App\Http\Requests\Admin\OurChannel\SelectMetaPageRequest;
use App\Http\Requests\Admin\OurChannel\UpdateMetaChannelConnectionRequest;
use App\Http\Requests\Admin\OurChannel\UpdateTelegramChannelConnectionRequest;
use App\Http\Requests\Admin\OurChannel\UpdateYouTubeChannelConnectionRequest;
use App\Models\AdminUser;
use App\Services\Admin\OurChannel\AdminChannelConnectionService;
use App\Services\OurChannel\MetaOAuthService;
use App\Services\OurChannel\YouTubeOAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ChannelConnectionController extends Controller
{
    public function index(
        Request $request,
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
                'youtubeRedirectUri' =>
                    $this->youtubeRedirectUri(
                        $request
                    ),
                'metaRedirectUri' =>
                    $this->metaRedirectUri(
                        $request
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



    public function updateMeta(
        UpdateMetaChannelConnectionRequest $request,
        AdminChannelConnectionService $service
    ): RedirectResponse {
        $admin = $this->admin();

        abort_unless(
            $admin->hasPermission(
                'our_channel.manage'
            ),
            403
        );

        $service->updateMeta(
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
                'Meta OAuth configuration saved securely.'
            );
    }

    public function startMeta(
        Request $request,
        AdminChannelConnectionService $service,
        MetaOAuthService $oauth
    ): RedirectResponse {
        $admin = $this->admin();

        abort_unless(
            $admin->hasPermission(
                'our_channel.manage'
            ),
            403
        );

        $connection =
            $service->metaConnection();

        try {
            $url = $oauth->authorizationUrl(
                $connection,
                $admin,
                $request,
                $this->metaRedirectUri(
                    $request
                )
            );
        } catch (ChannelConnectionException $exception) {
            return redirect()
                ->route(
                    'admin.our_channel.connections.index'
                )
                ->withErrors([
                    'meta_oauth' =>
                        '['
                        .$exception->providerCode
                        .'] '
                        .$exception->getMessage(),
                ]);
        }

        return redirect()->away($url);
    }

    public function metaCallback(
        Request $request,
        AdminChannelConnectionService $service,
        MetaOAuthService $oauth
    ): RedirectResponse {
        $admin = $this->admin();

        abort_unless(
            $admin->hasPermission(
                'our_channel.manage'
            ),
            403
        );

        $connection =
            $service->metaConnection();

        try {
            $connected = $oauth->complete(
                $connection,
                $admin,
                $request
            );
        } catch (ChannelConnectionException $exception) {
            return redirect()
                ->route(
                    'admin.our_channel.connections.index'
                )
                ->withErrors([
                    'meta_oauth' =>
                        '['
                        .$exception->providerCode
                        .'] '
                        .$exception->getMessage(),
                ]);
        }

        return redirect()
            ->route(
                'admin.our_channel.connections.index'
            )
            ->with(
                'success',
                $connected->last_test_status
                    === 'SUCCESS'
                    ? 'Meta connection verified. Facebook and linked Instagram publishing are ready.'
                    : 'Meta login succeeded. Select the Facebook Page TradeX should publish to.'
            );
    }

    public function selectMetaPage(
        SelectMetaPageRequest $request,
        AdminChannelConnectionService $service,
        MetaOAuthService $oauth
    ): RedirectResponse {
        $admin = $this->admin();

        abort_unless(
            $admin->hasPermission(
                'our_channel.manage'
            ),
            403
        );

        $connection =
            $service->metaConnection();

        try {
            $oauth->selectPage(
                $connection,
                $admin,
                $request,
                (string) $request->validated(
                    'page_id'
                ),
                (int) $request->validated(
                    'version'
                )
            );
        } catch (ChannelConnectionException $exception) {
            return redirect()
                ->route(
                    'admin.our_channel.connections.index'
                )
                ->withErrors([
                    'meta_oauth' =>
                        '['
                        .$exception->providerCode
                        .'] '
                        .$exception->getMessage(),
                ]);
        }

        return redirect()
            ->route(
                'admin.our_channel.connections.index'
            )
            ->with(
                'success',
                'Facebook Page selected and Meta publishing connection verified.'
            );
    }

    public function updateYouTube(
        UpdateYouTubeChannelConnectionRequest $request,
        AdminChannelConnectionService $service
    ): RedirectResponse {
        $admin = $this->admin();

        abort_unless(
            $admin->hasPermission(
                'our_channel.manage'
            ),
            403
        );

        $service->updateYouTube(
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
                'YouTube OAuth configuration saved securely.'
            );
    }

    public function startYouTube(
        Request $request,
        AdminChannelConnectionService $service,
        YouTubeOAuthService $oauth
    ): RedirectResponse {
        $admin = $this->admin();

        abort_unless(
            $admin->hasPermission(
                'our_channel.manage'
            ),
            403
        );

        $connection =
            $service->youtubeConnection();

        try {
            $url = $oauth->authorizationUrl(
                $connection,
                $admin,
                $request,
                $this->youtubeRedirectUri(
                    $request
                )
            );
        } catch (ChannelConnectionException $exception) {
            return redirect()
                ->route(
                    'admin.our_channel.connections.index'
                )
                ->withErrors([
                    'youtube_oauth' =>
                        $exception->getMessage(),
                ]);
        }

        return redirect()->away($url);
    }

    public function youtubeCallback(
        Request $request,
        AdminChannelConnectionService $service,
        YouTubeOAuthService $oauth
    ): RedirectResponse {
        $admin = $this->admin();

        abort_unless(
            $admin->hasPermission(
                'our_channel.manage'
            ),
            403
        );

        $connection =
            $service->youtubeConnection();

        try {
            $oauth->complete(
                $connection,
                $admin,
                $request
            );
        } catch (ChannelConnectionException $exception) {
            return redirect()
                ->route(
                    'admin.our_channel.connections.index'
                )
                ->withErrors([
                    'youtube_oauth' =>
                        '['
                        .$exception->providerCode
                        .'] '
                        .$exception->getMessage(),
                ]);
        }

        return redirect()
            ->route(
                'admin.our_channel.connections.index'
            )
            ->with(
                'success',
                'YouTube channel connected and automatic publishing enabled.'
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



    private function metaRedirectUri(
        Request $request
    ): string {
        return rtrim(
            $request->getSchemeAndHttpHost(),
            '/'
        )
            .route(
                'admin.our_channel.connections.meta.callback',
                [],
                false
            );
    }

    private function youtubeRedirectUri(
        Request $request
    ): string {
        return rtrim(
            $request->getSchemeAndHttpHost(),
            '/'
        )
            .route(
                'admin.our_channel.connections.youtube.callback',
                [],
                false
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
