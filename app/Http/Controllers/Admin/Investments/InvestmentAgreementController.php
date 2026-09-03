<?php

namespace App\Http\Controllers\Admin\Investments;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Investments\UploadSignedInvestmentAgreementRequest;
use App\Models\AdminUser;
use App\Models\Investment;
use App\Models\InvestmentAgreement;
use App\Services\Admin\Investments\InvestmentAgreementService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InvestmentAgreementController extends Controller
{
    public function index(): View
    {
        $admin = $this->admin();

        abort_unless(
            $admin->hasPermission('investments.agreements.view'),
            403
        );

        $agreements = InvestmentAgreement::query()
            ->with(['investment.user'])
            ->latest('id')
            ->paginate(25);

        $awaiting = Investment::query()
            ->with(['user', 'latestAgreement'])
            ->where(
                'status',
                Investment::STATUS_AWAITING_AGREEMENT
            )
            ->latest('id')
            ->limit(50)
            ->get();

        return view(
            'admin.investments.agreements.index',
            [
                'agreements' => $agreements,
                'awaitingInvestments' => $awaiting,
            ]
        );
    }

    public function generate(
        Request $request,
        Investment $investment,
        InvestmentAgreementService $service
    ): RedirectResponse {
        try {
            $service->generate(
                $investment,
                $this->admin(),
                $request
            );
        } catch (DomainException $exception) {
            return back()->withErrors([
                'agreement' => $exception->getMessage(),
            ]);
        }

        return back()->with(
            'success',
            'New immutable agreement version generated in private storage.'
        );
    }

    public function uploadSigned(
        UploadSignedInvestmentAgreementRequest $request,
        Investment $investment,
        InvestmentAgreement $agreement,
        InvestmentAgreementService $service
    ): RedirectResponse {
        try {
            $service->uploadSigned(
                $investment,
                $agreement,
                $request->file('signed_document'),
                (string) $request->validated('signed_at'),
                $this->admin(),
                $request
            );
        } catch (DomainException $exception) {
            return back()->withErrors([
                'agreement' => $exception->getMessage(),
            ]);
        }

        return back()->with(
            'success',
            'Signed PDF uploaded. Agreement verification is now required.'
        );
    }

    public function verify(
        Request $request,
        Investment $investment,
        InvestmentAgreement $agreement,
        InvestmentAgreementService $service
    ): RedirectResponse {
        try {
            $service->verifySigned(
                $investment,
                $agreement,
                $this->admin(),
                $request
            );
        } catch (DomainException $exception) {
            return back()->withErrors([
                'agreement' => $exception->getMessage(),
            ]);
        }

        return back()->with(
            'success',
            'Signed investment agreement verified.'
        );
    }

    public function generated(
        Request $request,
        Investment $investment,
        InvestmentAgreement $agreement,
        InvestmentAgreementService $service
    ): StreamedResponse {
        try {
            $evidence = $service->documentEvidence(
                $investment,
                $agreement,
                false,
                $this->admin(),
                $request
            );
        } catch (DomainException) {
            abort(404);
        }

        return Storage::disk($evidence['disk'])
            ->response(
                $evidence['path'],
                $evidence['name'],
                [
                    ...$this->sensitiveHeaders(),
                    'Content-Type' =>
                        $evidence['mime_type'],
                ],
                'attachment'
            );
    }

    public function signed(
        Request $request,
        Investment $investment,
        InvestmentAgreement $agreement,
        InvestmentAgreementService $service
    ): StreamedResponse {
        try {
            $evidence = $service->documentEvidence(
                $investment,
                $agreement,
                true,
                $this->admin(),
                $request
            );
        } catch (DomainException) {
            abort(404);
        }

        return Storage::disk($evidence['disk'])
            ->response(
                $evidence['path'],
                $evidence['name'],
                [
                    ...$this->sensitiveHeaders(),
                    'Content-Type' =>
                        $evidence['mime_type'],
                ],
                'attachment'
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
            'Cache-Control' =>
                'private, no-store, no-cache, max-age=0, must-revalidate',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'no-referrer',
            'X-Frame-Options' => 'DENY',
            'X-Robots-Tag' =>
                'noindex, nofollow, noarchive',
        ];
    }
}
