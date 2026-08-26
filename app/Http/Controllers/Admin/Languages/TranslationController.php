<?php

namespace App\Http\Controllers\Admin\Languages;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Languages\ReviewTranslationValueRequest;
use App\Http\Requests\Admin\Languages\UpdateTranslationValueRequest;
use App\Models\AdminUser;
use App\Models\LanguageProfile;
use App\Models\TranslationKey;
use App\Services\Admin\Languages\AdminLanguageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TranslationController extends Controller
{
    public function index(
        Request $request,
        LanguageProfile $languageProfile,
        AdminLanguageService $service
    ): Response {
        $admin =
            $this->viewer();

        return $this->view(
            'admin.languages.translations',
            array_merge(
                $service->languageTranslations(
                    $languageProfile,
                    $request->only([
                        'search',
                        'group',
                        'status',
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

    public function edit(
        LanguageProfile $languageProfile,
        TranslationKey $translationKey,
        AdminLanguageService $service
    ): Response {
        $this->manager();

        return $this->view(
            'admin.languages.translation_edit',
            $service->translationEditor(
                $languageProfile,
                $translationKey
            )
        );
    }

    public function update(
        UpdateTranslationValueRequest $request,
        LanguageProfile $languageProfile,
        TranslationKey $translationKey,
        AdminLanguageService $service
    ): RedirectResponse {
        $admin =
            $this->manager();

        $service->updateTranslation(
            $admin,
            $languageProfile,
            $translationKey,
            $request->validated(),
            $request
        );

        return redirect()
            ->route(
                'admin.languages.translations.edit',
                [
                    $languageProfile,
                    $translationKey,
                ]
            )
            ->with(
                'success',
                'Translation draft saved. Review is still required.'
            );
    }

    public function review(
        ReviewTranslationValueRequest $request,
        LanguageProfile $languageProfile,
        TranslationKey $translationKey,
        AdminLanguageService $service
    ): RedirectResponse {
        $admin =
            $this->manager();

        $service->reviewTranslation(
            $admin,
            $languageProfile,
            $translationKey,
            $request->validated(),
            $request
        );

        return redirect()
            ->route(
                'admin.languages.translations.edit',
                [
                    $languageProfile,
                    $translationKey,
                ]
            )
            ->with(
                'success',
                'Translation reviewed against the current source fingerprint.'
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
