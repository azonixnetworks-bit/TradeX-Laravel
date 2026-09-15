<?php

namespace App\Http\Controllers\Admin\TradingTools;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Models\AuditLog;
use App\Models\FundedAccountPartner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FundedAccountPartnerController extends Controller
{
    public function index(): View
    {
        $this->manager();

        return view('admin.trading_tools.funded_account.partners.index', [
            'partners' => FundedAccountPartner::query()
                ->withCount('reports')
                ->orderByDesc('is_featured')
                ->orderBy('priority')
                ->orderBy('name')
                ->paginate(20),
        ]);
    }

    public function create(): View
    {
        $this->manager();

        return view('admin.trading_tools.funded_account.partners.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $admin = $this->manager();
        $validated = $this->validatePartner($request);
        $uuid = (string) Str::uuid();

        $partner = FundedAccountPartner::query()->create(array_merge(
            $this->partnerAttributes($validated),
            [
                'uuid' => $uuid,
                'created_by_admin_id' => $admin->id,
                'updated_by_admin_id' => $admin->id,
            ],
            $this->storeLogo($request, $uuid)
        ));

        $this->audit($request, $admin, 'FUNDED_PARTNER_CREATED', $partner, null, [
            'name' => $partner->name,
            'is_active' => $partner->is_active,
            'is_featured' => $partner->is_featured,
            'priority' => $partner->priority,
        ]);

        return redirect()
            ->route('admin.funded_advisor.partners.edit', $partner)
            ->with('success', 'Funded account partner created successfully.');
    }

    public function edit(FundedAccountPartner $fundedAccountPartner): View
    {
        $this->manager();

        return view('admin.trading_tools.funded_account.partners.edit', [
            'partner' => $fundedAccountPartner,
        ]);
    }

    public function update(
        Request $request,
        FundedAccountPartner $fundedAccountPartner
    ): RedirectResponse {
        $admin = $this->manager();
        $validated = $this->validatePartner($request);
        $old = [
            'name' => $fundedAccountPartner->name,
            'is_active' => $fundedAccountPartner->is_active,
            'is_featured' => $fundedAccountPartner->is_featured,
            'priority' => $fundedAccountPartner->priority,
        ];

        $attributes = array_merge(
            $this->partnerAttributes($validated),
            ['updated_by_admin_id' => $admin->id]
        );

        if ($request->hasFile('logo')) {
            $this->deleteLogo($fundedAccountPartner);
            $attributes = array_merge(
                $attributes,
                $this->storeLogo($request, $fundedAccountPartner->uuid)
            );
        }

        if ($request->boolean('remove_logo')) {
            $this->deleteLogo($fundedAccountPartner);
            $attributes = array_merge($attributes, [
                'logo_disk' => null,
                'logo_path' => null,
                'logo_mime_type' => null,
                'logo_size_bytes' => null,
            ]);
        }

        $fundedAccountPartner->fill($attributes)->save();

        $this->audit($request, $admin, 'FUNDED_PARTNER_UPDATED', $fundedAccountPartner, $old, [
            'name' => $fundedAccountPartner->name,
            'is_active' => $fundedAccountPartner->is_active,
            'is_featured' => $fundedAccountPartner->is_featured,
            'priority' => $fundedAccountPartner->priority,
        ]);

        return back()->with('success', 'Funded account partner updated.');
    }

    public function destroy(
        Request $request,
        FundedAccountPartner $fundedAccountPartner
    ): RedirectResponse {
        $admin = $this->manager();
        $old = [
            'name' => $fundedAccountPartner->name,
            'is_active' => $fundedAccountPartner->is_active,
            'is_featured' => $fundedAccountPartner->is_featured,
        ];
        $partnerId = $fundedAccountPartner->id;

        $this->deleteLogo($fundedAccountPartner);
        $fundedAccountPartner->delete();

        AuditLog::record(
            'ADMIN',
            $admin->id,
            'FUNDED_PARTNER_DELETED',
            'TRADING_TOOLS',
            FundedAccountPartner::class,
            $partnerId,
            $old,
            null,
            $request->ip(),
            $request->userAgent()
        );

        return redirect()
            ->route('admin.funded_advisor.partners.index')
            ->with('success', 'Funded account partner deleted. Historical reports keep their saved partner-link snapshots.');
    }

    public function logo(
        FundedAccountPartner $fundedAccountPartner
    ): StreamedResponse {
        $admin = $this->admin();
        abort_unless(
            $admin->hasPermission('funded_advisor.view')
                || $admin->hasPermission('funded_advisor.partners.manage'),
            403
        );
        abort_unless($fundedAccountPartner->hasLogo(), 404);
        abort_unless(
            in_array(
                $fundedAccountPartner->logo_mime_type,
                ['image/jpeg', 'image/png', 'image/webp'],
                true
            ),
            404
        );

        return Storage::disk($fundedAccountPartner->logo_disk)->response(
            $fundedAccountPartner->logo_path,
            'funded-partner-logo',
            [
                'Cache-Control' => 'private, max-age=3600',
                'X-Content-Type-Options' => 'nosniff',
                'Content-Type' => $fundedAccountPartner->logo_mime_type,
            ],
            'inline'
        );
    }

    /** @return array<string, mixed> */
    private function validatePartner(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'website_url' => ['nullable', 'url:http,https', 'max:2048'],
            'affiliate_url' => ['nullable', 'url:http,https', 'max:2048'],
            'promo_code' => ['nullable', 'string', 'max:100'],
            'offer_text' => ['nullable', 'string', 'max:255'],
            'disclosure' => ['nullable', 'string', 'max:2000'],
            'supported_countries' => ['nullable', 'string', 'max:4000'],
            'account_sizes' => ['nullable', 'string', 'max:4000'],
            'challenge_types' => ['nullable', 'string', 'max:4000'],
            'priority' => ['required', 'integer', 'min:0', 'max:100000'],
            'is_featured' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'logo' => ['nullable', 'image', Rule::in(['jpg', 'jpeg', 'png', 'webp']), 'max:2048'],
            'remove_logo' => ['nullable', 'boolean'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function partnerAttributes(array $validated): array
    {
        return [
            'name' => trim((string) $validated['name']),
            'website_url' => $validated['website_url'] ?? null,
            'affiliate_url' => $validated['affiliate_url'] ?? null,
            'promo_code' => filled($validated['promo_code'] ?? null)
                ? trim((string) $validated['promo_code'])
                : null,
            'offer_text' => filled($validated['offer_text'] ?? null)
                ? trim((string) $validated['offer_text'])
                : null,
            'disclosure' => filled($validated['disclosure'] ?? null)
                ? trim((string) $validated['disclosure'])
                : null,
            'supported_countries' => $this->listValue($validated['supported_countries'] ?? null),
            'account_sizes' => $this->listValue($validated['account_sizes'] ?? null),
            'challenge_types' => $this->listValue($validated['challenge_types'] ?? null),
            'priority' => (int) $validated['priority'],
            'is_featured' => (bool) ($validated['is_featured'] ?? false),
            'is_active' => (bool) ($validated['is_active'] ?? false),
        ];
    }

    /** @return array<string, mixed> */
    private function storeLogo(Request $request, string $uuid): array
    {
        $file = $request->file('logo');

        if (! $file) {
            return [];
        }

        $extension = match ($file->getMimeType()) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => null,
        };

        abort_unless($extension !== null, 422);

        $path = $file->storeAs(
            'funded-account-partners/'.$uuid,
            'logo.'.$extension,
            'local'
        );

        return [
            'logo_disk' => 'local',
            'logo_path' => $path,
            'logo_mime_type' => $file->getMimeType(),
            'logo_size_bytes' => $file->getSize(),
        ];
    }

    private function deleteLogo(FundedAccountPartner $partner): void
    {
        if ($partner->hasLogo()) {
            Storage::disk($partner->logo_disk)->delete($partner->logo_path);
        }
    }

    /** @return array<int, string>|null */
    private function listValue(mixed $value): ?array
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $items = preg_split('/[\r\n,]+/', $value) ?: [];
        $items = array_values(array_unique(array_filter(
            array_map(static fn (string $item): string => trim($item), $items),
            static fn (string $item): bool => $item !== ''
        )));

        return $items === [] ? null : $items;
    }

    private function audit(
        Request $request,
        AdminUser $admin,
        string $action,
        FundedAccountPartner $partner,
        ?array $old,
        ?array $new
    ): void {
        AuditLog::record(
            'ADMIN',
            $admin->id,
            $action,
            'TRADING_TOOLS',
            FundedAccountPartner::class,
            $partner->id,
            $old,
            $new,
            $request->ip(),
            $request->userAgent()
        );
    }

    private function manager(): AdminUser
    {
        $admin = $this->admin();
        abort_unless($admin->hasPermission('funded_advisor.partners.manage'), 403);

        return $admin;
    }

    private function admin(): AdminUser
    {
        $admin = auth('admin')->user();
        abort_unless($admin instanceof AdminUser, 401);

        return $admin;
    }
}
