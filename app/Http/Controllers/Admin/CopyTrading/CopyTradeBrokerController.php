<?php

namespace App\Http\Controllers\Admin\CopyTrading;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CopyTrading\StoreCopyTradeBrokerMarketRequest;
use App\Http\Requests\Admin\CopyTrading\StoreCopyTradeBrokerRequest;
use App\Http\Requests\Admin\CopyTrading\UpdateCopyTradeBrokerMarketRequest;
use App\Http\Requests\Admin\CopyTrading\UpdateCopyTradeBrokerRequest;
use App\Models\AdminUser;
use App\Models\CopyTradeBroker;
use App\Models\CopyTradeBrokerMarket;
use App\Services\Admin\CopyTrading\CopyTradeBrokerAdminService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CopyTradeBrokerController extends Controller
{
    public function index(Request $request): View
    {
        $admin = $this->admin();
        abort_unless($admin->hasPermission('copy_trading.view'), 403);

        $brokers = CopyTradeBroker::query()
            ->with('markets')
            ->withCount('accounts')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.copy_trading.brokers.index', [
            'brokers' => $brokers,
            'canManage' => $admin->hasPermission('copy_trading.brokers.manage'),
        ]);
    }

    public function create(): View
    {
        $admin = $this->admin();
        abort_unless($admin->hasPermission('copy_trading.brokers.manage'), 403);

        return view('admin.copy_trading.brokers.create', [
            'marketCategories' => (array) config(
                'tradex_copy_trading.market_categories',
                []
            ),
            'requiredCurrency' => (string) config(
                'tradex_copy_trading.required_currency',
                'USD'
            ),
        ]);
    }

    public function store(
        StoreCopyTradeBrokerRequest $request,
        CopyTradeBrokerAdminService $service
    ): RedirectResponse {
        $admin = $this->admin();

        $broker = $service->create(
            $request->validated(),
            $request->file('logo'),
            $admin,
            $request
        );

        return redirect()
            ->route('admin.copy_trading.brokers.edit', [
                'copyTradeBroker' => $broker,
            ])
            ->with('success', 'Copy Trading broker created successfully.');
    }

    public function edit(CopyTradeBroker $copyTradeBroker): View
    {
        $admin = $this->admin();
        abort_unless($admin->hasPermission('copy_trading.brokers.manage'), 403);

        $copyTradeBroker->load([
            'markets' => fn ($query) => $query->orderBy('market_category'),
        ]);

        return view('admin.copy_trading.brokers.edit', [
            'broker' => $copyTradeBroker,
            'marketCategories' => (array) config(
                'tradex_copy_trading.market_categories',
                []
            ),
            'requiredCurrency' => (string) config(
                'tradex_copy_trading.required_currency',
                'USD'
            ),
        ]);
    }

    public function update(
        UpdateCopyTradeBrokerRequest $request,
        CopyTradeBroker $copyTradeBroker,
        CopyTradeBrokerAdminService $service
    ): RedirectResponse {
        $service->update(
            $copyTradeBroker,
            $request->validated(),
            $request->file('logo'),
            $this->admin(),
            $request
        );

        return back()->with('success', 'Broker configuration updated.');
    }

    public function storeMarket(
        StoreCopyTradeBrokerMarketRequest $request,
        CopyTradeBroker $copyTradeBroker,
        CopyTradeBrokerAdminService $service
    ): RedirectResponse {
        try {
            $service->createMarket(
                $copyTradeBroker,
                $request->validated(),
                $this->admin(),
                $request
            );
        } catch (DomainException $exception) {
            return back()->withErrors([
                'market_category' => $exception->getMessage(),
            ]);
        }

        return back()->with('success', 'Broker market configuration added.');
    }

    public function updateMarket(
        UpdateCopyTradeBrokerMarketRequest $request,
        CopyTradeBroker $copyTradeBroker,
        CopyTradeBrokerMarket $copyTradeBrokerMarket,
        CopyTradeBrokerAdminService $service
    ): RedirectResponse {
        try {
            $service->updateMarket(
                $copyTradeBroker,
                $copyTradeBrokerMarket,
                $request->validated(),
                $this->admin(),
                $request
            );
        } catch (DomainException $exception) {
            return back()->withErrors([
                'broker_market' => $exception->getMessage(),
            ]);
        }

        return back()->with('success', 'Broker market configuration updated.');
    }

    public function logo(
        CopyTradeBroker $copyTradeBroker
    ): StreamedResponse {
        $admin = $this->admin();
        abort_unless($admin->hasPermission('copy_trading.view'), 403);
        abort_unless($copyTradeBroker->hasLogo(), 404);

        abort_unless(
            in_array(
                $copyTradeBroker->logo_mime_type,
                ['image/jpeg', 'image/png', 'image/webp'],
                true
            ),
            404
        );

        return Storage::disk($copyTradeBroker->logo_disk)->response(
            $copyTradeBroker->logo_path,
            'broker-logo',
            [
                'Cache-Control' => 'private, max-age=3600',
                'X-Content-Type-Options' => 'nosniff',
                'Content-Type' => $copyTradeBroker->logo_mime_type,
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
}
