<?php

namespace App\Http\Controllers\Admin\EconomicNews;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\EconomicNews\UpsertEconomicNewsSourceRequest;
use App\Models\AdminUser;
use App\Models\EconomicNewsSource;
use App\Services\Admin\EconomicNews\AdminEconomicNewsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class EconomicNewsSourceController extends Controller
{
    public function index(): Response
    {
        $this->manager();

        return response()->view(
            'admin.economic_news.sources.index',
            [
                'sources' => EconomicNewsSource::query()
                    ->withCount(['events', 'updates'])
                    ->orderBy('priority')
                    ->orderBy('name')
                    ->get(),
            ],
            200,
            $this->noStoreHeaders()
        );
    }

    public function store(
        UpsertEconomicNewsSourceRequest $request,
        AdminEconomicNewsService $service
    ): RedirectResponse {
        $service->createSource($this->manager(), $request->validated(), $request);

        return redirect()
            ->route('admin.economic_news.sources.index')
            ->with('success', 'Economic news source created securely.');
    }

    public function update(
        UpsertEconomicNewsSourceRequest $request,
        EconomicNewsSource $economicNewsSource,
        AdminEconomicNewsService $service
    ): RedirectResponse {
        $service->updateSource(
            $economicNewsSource,
            $this->manager(),
            $request->validated(),
            $request
        );

        return redirect()
            ->route('admin.economic_news.sources.index')
            ->with('success', 'Economic news source updated securely.');
    }

    public function destroy(
        Request $request,
        EconomicNewsSource $economicNewsSource,
        AdminEconomicNewsService $service
    ): RedirectResponse {
        $service->deleteSource($economicNewsSource, $this->manager(), $request);

        return redirect()
            ->route('admin.economic_news.sources.index')
            ->with('success', 'Unused economic news source deleted.');
    }

    private function manager(): AdminUser
    {
        $admin = auth('admin')->user();
        abort_unless($admin instanceof AdminUser, 401);
        abort_unless(
            $admin->isSuperAdmin() || $admin->hasPermission('economic_news.sources.manage'),
            403
        );

        return $admin;
    }

    /** @return array<string, string> */
    private function noStoreHeaders(): array
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
