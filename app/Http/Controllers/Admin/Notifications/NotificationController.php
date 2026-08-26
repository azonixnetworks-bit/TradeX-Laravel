<?php

namespace App\Http\Controllers\Admin\Notifications;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Notifications\SendNotificationBroadcastRequest;
use App\Http\Requests\Admin\Notifications\StoreNotificationDraftRequest;
use App\Http\Requests\Admin\Notifications\UpdateNotificationDraftRequest;
use App\Models\AdminUser;
use App\Models\NotificationMessage;
use App\Services\Admin\Notifications\AdminNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class NotificationController extends Controller
{
    public function index(
        Request $request,
        AdminNotificationService $service
    ): Response {
        $admin = $this->viewer();

        return $this->view(
            'admin.notifications.index',
            array_merge(
                $service->index(
                    $request->only([
                        'search',
                        'status',
                        'audience',
                        'category',
                        'priority',
                        'per_page',
                    ])
                ),
                [
                    'canCreate' =>
                        $admin->hasPermission(
                            'notifications.create'
                        ),

                    'canSend' =>
                        $admin->hasPermission(
                            'notifications.send'
                        ),
                ]
            )
        );
    }

    public function create(): Response
    {
        $this->creator();

        return $this->view(
            'admin.notifications.create',
            $this->formOptions()
        );
    }

    public function store(
        StoreNotificationDraftRequest $request,
        AdminNotificationService $service
    ): RedirectResponse {
        $admin = $this->creator();

        $message = $service->createDraft(
            $admin,
            $request->validated(),
            $request
        );

        return redirect()
            ->route(
                'admin.notifications.show',
                $message
            )
            ->with(
                'success',
                'Notification draft created.'
            );
    }

    public function show(
        NotificationMessage $notificationMessage,
        AdminNotificationService $service
    ): Response {
        $admin = $this->viewer();

        return $this->view(
            'admin.notifications.show',
            array_merge(
                $service->show(
                    $notificationMessage
                ),
                [
                    'canCreate' =>
                        $admin->hasPermission(
                            'notifications.create'
                        ),

                    'canSend' =>
                        $admin->hasPermission(
                            'notifications.send'
                        ),
                ]
            )
        );
    }

    public function edit(
        NotificationMessage $notificationMessage,
        AdminNotificationService $service
    ): Response {
        $this->creator();

        $service->assertDraftMutable(
            $notificationMessage
        );

        return $this->view(
            'admin.notifications.edit',
            array_merge(
                $this->formOptions(),
                [
                    'notificationMessage' =>
                        $notificationMessage
                            ->fresh(),
                ]
            )
        );
    }

    public function update(
        UpdateNotificationDraftRequest $request,
        NotificationMessage $notificationMessage,
        AdminNotificationService $service
    ): RedirectResponse {
        $admin = $this->creator();

        $service->updateDraft(
            $admin,
            $notificationMessage,
            $request->validated(),
            $request
        );

        return redirect()
            ->route(
                'admin.notifications.show',
                $notificationMessage
            )
            ->with(
                'success',
                'Notification draft updated.'
            );
    }

    public function send(
        SendNotificationBroadcastRequest $request,
        NotificationMessage $notificationMessage,
        AdminNotificationService $service
    ): RedirectResponse {
        $admin = $this->sender();

        $result = $service->dispatch(
            $admin,
            $notificationMessage,
            $request->validated(),
            $request
        );

        return redirect()
            ->route(
                'admin.notifications.show',
                $notificationMessage
            )
            ->with(
                'success',
                $result['scheduled']
                    ? 'In-app broadcast scheduled and recipient receipts prepared.'
                    : 'In-app broadcast published and recipient receipts created.'
            );
    }

    private function viewer(): AdminUser
    {
        $admin = $this->admin();

        abort_unless(
            $admin->hasPermission(
                'notifications.view'
            ),
            403
        );

        return $admin;
    }

    private function creator(): AdminUser
    {
        $admin = $this->admin();

        abort_unless(
            $admin->hasPermission(
                'notifications.view'
            )
            && $admin->hasPermission(
                'notifications.create'
            ),
            403
        );

        return $admin;
    }

    private function sender(): AdminUser
    {
        $admin = $this->admin();

        abort_unless(
            $admin->hasPermission(
                'notifications.view'
            )
            && $admin->hasPermission(
                'notifications.send'
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

    private function formOptions(): array
    {
        return [
            'audiences' => [
                NotificationMessage::AUDIENCE_ALL,
                NotificationMessage::AUDIENCE_FREE,
                NotificationMessage::AUDIENCE_PREMIUM,
            ],

            'categories' =>
                NotificationMessage::CATEGORIES,

            'priorities' =>
                NotificationMessage::PRIORITIES,

            'actionTypes' =>
                NotificationMessage::ACTION_TYPES,
        ];
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
