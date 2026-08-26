<?php

namespace App\Http\Controllers\Admin\Languages;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Languages\StoreTranslationKeyRequest;
use App\Http\Requests\Admin\Languages\UpdateLanguageProfileRequest;
use App\Http\Requests\Admin\Languages\UpdateTranslationKeyRequest;
use App\Models\AdminUser;
use App\Models\LanguageProfile;
use App\Models\TranslationKey;
use App\Services\Admin\Languages\AdminLanguageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class LanguageController extends Controller
{
    public function index(
        AdminLanguageService $service
    ): Response {
        $admin =
            $this->viewer();

        return $this->view(
            'admin.languages.index',
            array_merge(
                $service->index(),
                [
                    'canManage' =>
                        $admin->hasPermission(
                            'languages.manage'
                        ),
                ]
            )
        );
    }

    public function update(
        UpdateLanguageProfileRequest $request,
        LanguageProfile $languageProfile,
        AdminLanguageService $service
    ): RedirectResponse {
        $admin =
            $this->manager();

        $service->updateLanguage(
            $admin,
            $languageProfile,
            $request->validated(),
            $request
        );

        return redirect()
            ->route(
                'admin.languages.index'
            )
            ->with(
                'success',
                'Translation-program language state updated.'
            );
    }

    public function keys(
        Request $request,
        AdminLanguageService $service
    ): Response {
        $admin =
            $this->viewer();

        return $this->view(
            'admin.languages.keys.index',
            array_merge(
                $service->keyIndex(
                    $request->only([
                        'search',
                        'group',
                        'per_page',
                    ])
                ),
                [
                    'canManage' =>
                        $admin->hasPermission(
                            'languages.manage'
                        ),
                ]
            )
        );
    }

    public function createKey(): Response
    {
        $this->manager();

        return $this->view(
            'admin.languages.keys.create',
            [
                'groups' =>
                    (array) config(
                        'tradex_languages.groups',
                        []
                    ),
            ]
        );
    }

    public function storeKey(
        StoreTranslationKeyRequest $request,
        AdminLanguageService $service
    ): RedirectResponse {
        $admin =
            $this->manager();

        $key =
            $service->createKey(
                $admin,
                $request->validated(),
                $request
            );

        return redirect()
            ->route(
                'admin.languages.keys.edit',
                $key
            )
            ->with(
                'success',
                'Source translation key created.'
            );
    }

    public function editKey(
        TranslationKey $translationKey
    ): Response {
        $this->manager();

        return $this->view(
            'admin.languages.keys.edit',
            [
                'key' =>
                    $translationKey,

                'groups' =>
                    (array) config(
                        'tradex_languages.groups',
                        []
                    ),
            ]
        );
    }

    public function updateKey(
        UpdateTranslationKeyRequest $request,
        TranslationKey $translationKey,
        AdminLanguageService $service
    ): RedirectResponse {
        $admin =
            $this->manager();

        $service->updateKey(
            $admin,
            $translationKey,
            $request->validated(),
            $request
        );

        return redirect()
            ->route(
                'admin.languages.keys.edit',
                $translationKey
            )
            ->with(
                'success',
                'Source text updated. Existing target translations may now be stale.'
            );
    }

    private function viewer(): AdminUser
    {
        $admin =
            $this->admin();

        abort_unless(
            $admin->hasPermission(
                'languages.view'
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
                'languages.view'
            )
            && $admin->hasPermission(
                'languages.manage'
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
