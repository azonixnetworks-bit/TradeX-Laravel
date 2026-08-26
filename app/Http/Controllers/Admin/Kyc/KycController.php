<?php

namespace App\Http\Controllers\Admin\Kyc;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Models\KycDocument;
use App\Models\KycSubmission;
use App\Services\Admin\Kyc\AdminKycService;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class KycController extends Controller
{
    public function index(
        Request $request,
        AdminKycService $service
    ): View {
        $admin = $this->admin();
        abort_unless($admin->hasPermission('kyc.view'), 403);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:191'],
            'status' => [
                'nullable',
                'in:'.implode(',', KycSubmission::VALID_STATUSES),
            ],
            'country' => ['nullable', 'regex:/^[A-Za-z]{2}$/'],
            'document_type' => [
                'nullable',
                'string',
                'max:40',
                'regex:/^[A-Za-z0-9_]+$/',
            ],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'per_page' => [
                'nullable',
                'integer',
                'in:10,20,50,100',
            ],
        ]);

        return view(
            'admin.kyc.index',
            $service->dashboard($filters)
        );
    }

    public function show(
        KycSubmission $kycSubmission,
        AdminKycService $service
    ): View {
        $admin = $this->admin();
        abort_unless($admin->hasPermission('kyc.view'), 403);

        $data = $service->detail($kycSubmission);
        $data['permissions'] = [
            'view_documents' => $admin->hasPermission('kyc.view_documents'),
            'approve' => $admin->hasPermission('kyc.approve'),
            'reject' => $admin->hasPermission('kyc.reject'),
            'request_resubmission' => $admin->hasPermission('kyc.request_resubmission'),
        ];

        return view('admin.kyc.review', $data);
    }

    public function identity(
        Request $request,
        KycSubmission $kycSubmission,
        AdminKycService $service
    ): Response {
        $admin = $this->admin();

        abort_unless(
            $admin->hasPermission('kyc.view')
                && $admin->hasPermission('kyc.view_documents'),
            403
        );

        try {
            $data = $service->sensitiveIdentity(
                $kycSubmission,
                $admin,
                $request
            );
        } catch (DomainException) {
            abort(404);
        }

        return response()->view(
            'admin.kyc.identity',
            $data,
            200,
            $this->sensitiveHeaders()
        );
    }

    public function document(
        Request $request,
        KycSubmission $kycSubmission,
        KycDocument $kycDocument,
        AdminKycService $service
    ): StreamedResponse {
        $admin = $this->admin();

        abort_unless(
            $admin->hasPermission('kyc.view')
                && $admin->hasPermission('kyc.view_documents'),
            403
        );

        try {
            $evidence = $service->documentEvidence(
                $kycSubmission,
                $kycDocument,
                $admin,
                $request
            );
        } catch (DomainException) {
            abort(404);
        }

        return Storage::disk($evidence['disk'])->response(
            $evidence['path'],
            $evidence['name'],
            [
                ...$this->sensitiveHeaders(),
                'Content-Type' => $evidence['mime_type'],
            ],
            'inline'
        );
    }

    private function admin(): AdminUser
    {
        $admin = auth('admin')->user();
        abort_unless($admin instanceof AdminUser, 401);

        return $admin;
    }

    private function sensitiveHeaders(): array
    {
        return [
            'Cache-Control' => 'private, no-store, no-cache, max-age=0, must-revalidate',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'no-referrer',
            'X-Frame-Options' => 'DENY',
            'X-Robots-Tag' => 'noindex, nofollow, noarchive',
        ];
    }
}
