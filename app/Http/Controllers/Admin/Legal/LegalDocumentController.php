<?php

namespace App\Http\Controllers\Admin\Legal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Legal\PublishLegalPolicyRequest;
use App\Http\Requests\Admin\Legal\StoreLegalPolicyDraftRequest;
use App\Http\Requests\Admin\Legal\UpdateLegalPolicyDraftRequest;
use App\Models\AdminUser;
use App\Models\LegalPolicyVersion;
use App\Services\Admin\Legal\AdminLegalPolicyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

class LegalDocumentController extends Controller
{
    public function index(
        AdminLegalPolicyService $service
    ): Response {
        $admin =
            $this->viewer();

        return $this->view(
            'admin.legal.index',
            array_merge(
                $service->index(),
                [
                    'canManage' =>
                        $admin->hasPermission(
                            'legal.manage'
                        ),

                    'canPublish' =>
                        $admin->hasPermission(
                            'legal.publish'
                        ),
                ]
            )
        );
    }

    public function create(
        string $policyType,
        AdminLegalPolicyService $service
    ): Response {
        $this->manager();

        return $this->view(
            'admin.legal.create',
            $service->createForm(
                $policyType
            )
        );
    }

    public function store(
        StoreLegalPolicyDraftRequest $request,
        AdminLegalPolicyService $service
    ): RedirectResponse {
        $admin =
            $this->manager();

        $policy =
            $service->createDraft(
                $admin,
                $request->validated(),
                $request
            );

        return redirect()
            ->route(
                'admin.legal.show',
                $policy
            )
            ->with(
                'success',
                'Legal policy draft created.'
            );
    }

    public function show(
        LegalPolicyVersion $legalPolicyVersion,
        AdminLegalPolicyService $service
    ): Response {
        $admin =
            $this->viewer();

        return $this->view(
            'admin.legal.show',
            array_merge(
                $service->show(
                    $legalPolicyVersion
                ),
                [
                    'canManage' =>
                        $admin->hasPermission(
                            'legal.manage'
                        ),

                    'canPublish' =>
                        $admin->hasPermission(
                            'legal.publish'
                        ),
                ]
            )
        );
    }

    public function edit(
        LegalPolicyVersion $legalPolicyVersion,
        AdminLegalPolicyService $service
    ): Response {
        $this->manager();

        $service->assertDraft(
            $legalPolicyVersion
        );

        return $this->view(
            'admin.legal.edit',
            [
                'policy' =>
                    $legalPolicyVersion,

                'definition' =>
                    (array) config(
                        'tradex_legal.policy_types.'
                        .$legalPolicyVersion
                            ->policy_type,
                        []
                    ),
            ]
        );
    }

    public function update(
        UpdateLegalPolicyDraftRequest $request,
        LegalPolicyVersion $legalPolicyVersion,
        AdminLegalPolicyService $service
    ): RedirectResponse {
        $admin =
            $this->manager();

        $service->updateDraft(
            $admin,
            $legalPolicyVersion,
            $request->validated(),
            $request
        );

        return redirect()
            ->route(
                'admin.legal.show',
                $legalPolicyVersion
            )
            ->with(
                'success',
                'Legal policy draft updated.'
            );
    }

    public function publish(
        PublishLegalPolicyRequest $request,
        LegalPolicyVersion $legalPolicyVersion,
        AdminLegalPolicyService $service
    ): RedirectResponse {
        $admin =
            $this->publisher();

        $service->publish(
            $admin,
            $legalPolicyVersion,
            $request->validated(),
            $request
        );

        return redirect()
            ->route(
                'admin.legal.show',
                $legalPolicyVersion
            )
            ->with(
                'success',
                'Legal policy version published and locked.'
            );
    }

    private function viewer(): AdminUser
    {
        $admin =
            $this->admin();

        abort_unless(
            $admin->hasPermission(
                'legal.view'
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
                'legal.view'
            )
            && $admin->hasPermission(
                'legal.manage'
            ),
            403
        );

        return $admin;
    }

    private function publisher(): AdminUser
    {
        $admin =
            $this->admin();

        abort_unless(
            $admin->hasPermission(
                'legal.view'
            )
            && $admin->hasPermission(
                'legal.publish'
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
