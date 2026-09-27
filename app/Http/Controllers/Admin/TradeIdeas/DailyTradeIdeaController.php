<?php

namespace App\Http\Controllers\Admin\TradeIdeas;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TradeIdeas\StoreTradeIdeaRequest;
use App\Http\Requests\Admin\TradeIdeas\UpdateTradeIdeaRequest;
use App\Models\AdminUser;
use App\Models\AuditLog;
use App\Models\DailyTradeIdea;
use App\Models\DailyTradeIdeaTarget;
use App\Support\TradingViewUrl;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DailyTradeIdeaController extends Controller
{
    public function index(Request $request): View
    {
        $admin = $this->authenticatedAdmin();
        $this->requirePermission($admin, 'trade_ideas.view');

        $status = strtoupper(trim((string) $request->query('status', '')));
        $category = strtoupper(trim((string) $request->query('category', '')));
        $marketFilter = strtoupper(trim((string) $request->query('market_filter', '')));

        $query = DailyTradeIdea::query()->with('targets');

        if (in_array($status, [
            DailyTradeIdea::STATUS_DRAFT,
            DailyTradeIdea::STATUS_PUBLISHED,
            DailyTradeIdea::STATUS_ARCHIVED,
        ], true)) {
            $query->where('status', $status);
        }

        if ($marketFilter !== '') {
            $this->applyMarketFilter($query, $marketFilter);
        } elseif (in_array($category, ['FOREX', 'CRYPTO', 'DERIV', 'STOCKS'], true)) {
            // Backward compatibility for existing category query links.
            $query->where('category', $category);
        }

        $dailyTradeIdeas = $query->latestFirst()->get();

        $stats = [
            'total' => DailyTradeIdea::query()->count(),
            'draft' => DailyTradeIdea::query()
                ->where('status', DailyTradeIdea::STATUS_DRAFT)
                ->count(),
            'published' => DailyTradeIdea::query()
                ->where('status', DailyTradeIdea::STATUS_PUBLISHED)
                ->count(),
            'archived' => DailyTradeIdea::query()
                ->where('status', DailyTradeIdea::STATUS_ARCHIVED)
                ->count(),
        ];

        $permissions = [
            'create' => $admin->hasPermission('trade_ideas.create'),
            'update' => $admin->hasPermission('trade_ideas.update'),
            'publish' => $admin->hasPermission('trade_ideas.publish'),
            'archive' => $admin->hasPermission('trade_ideas.close'),
        ];

        return view('admin.trade_ideas.daily.index', compact(
            'dailyTradeIdeas',
            'stats',
            'permissions',
            'status',
            'category',
            'marketFilter'
        ));
    }

    private function applyMarketFilter(
        \Illuminate\Database\Eloquent\Builder $query,
        string $marketFilter
    ): void {
        match ($marketFilter) {
            'TODAY' => $this->applyTodayFilter($query),
            'FOREX' => $query->where('category', 'FOREX'),
            'CRYPTO' => $query->where('category', 'CRYPTO'),
            'DERIV' => $query->where('category', 'DERIV'),
            'STOCKS_CSE' => $query
                ->where('category', 'STOCKS')
                ->where('market_region', 'SRI_LANKA'),
            'STOCKS_INTERNATIONAL' => $query
                ->where('category', 'STOCKS')
                ->where('market_region', 'INTERNATIONAL'),
            default => null,
        };
    }

    private function applyTodayFilter(
        \Illuminate\Database\Eloquent\Builder $query
    ): void {
        $timezone = trim(
            (string) config(
                'tradex_reporting.audit_timezone',
                config('app.timezone', 'UTC')
            )
        );

        try {
            $now = CarbonImmutable::now(
                $timezone !== ''
                    ? $timezone
                    : 'UTC'
            );
        } catch (\Throwable) {
            $now = CarbonImmutable::now('UTC');
        }

        $query->whereBetween(
            'created_at',
            [
                $now->startOfDay()->utc(),
                $now->endOfDay()->utc(),
            ]
        );
    }

    public function create(): View
    {
        $admin = $this->authenticatedAdmin();
        $this->requirePermission($admin, 'trade_ideas.create');

        return view('admin.trade_ideas.daily.create');
    }

    public function store(StoreTradeIdeaRequest $request): RedirectResponse
    {
        $admin = $this->authenticatedAdmin();
        $this->requirePermission($admin, 'trade_ideas.create');

        $validated = $request->validated();
        $uuid = Str::uuid()->toString();
        $chartImage = $this->storeChartImage($request->file('chart_image'), $uuid);

        try {
            $idea = DB::transaction(function () use (
                $validated,
                $request,
                $admin,
                $uuid,
                $chartImage
            ): DailyTradeIdea {
                $idea = DailyTradeIdea::query()->create([
                    'uuid' => $uuid,
                    'title' => $validated['title'],
                    'summary' => $validated['summary'] ?? null,
                    'analysis' => $validated['analysis'] ?? null,
                    'tradingview_url' => TradingViewUrl::normalize(
                        $validated['tradingview_url'] ?? null
                    ),
                    'chart_image_disk' => $chartImage['disk'] ?? null,
                    'chart_image_path' => $chartImage['path'] ?? null,
                    'chart_image_mime_type' => $chartImage['mime_type'] ?? null,
                    'chart_image_size_bytes' => $chartImage['size_bytes'] ?? null,
                    'category' => $validated['category'],
                    'market_region' => $validated['market_region'] ?? null,
                    'symbol' => $validated['symbol'],
                    'instrument_name' => $validated['instrument_name'] ?? null,
                    'direction' => $validated['direction'],
                    'timeframe' => $validated['timeframe'],
                    'entry_price_low' => $validated['entry_price_low'] ?? null,
                    'entry_price_high' => $validated['entry_price_high'] ?? null,
                    'stop_loss' => $validated['stop_loss'] ?? null,
                    'status' => DailyTradeIdea::STATUS_DRAFT,
                    'published_at' => null,
                    'archived_at' => null,
                    'created_by_admin_id' => $admin->id,
                    'updated_by_admin_id' => $admin->id,
                ]);

                $this->replaceTargets($idea, $validated['targets'] ?? []);
                $idea->load('targets');

                AuditLog::record(
                    actorType: 'ADMIN',
                    actorId: $admin->id,
                    action: 'DAILY_TRADE_IDEA_CREATED',
                    module: 'TRADE_IDEAS',
                    targetType: 'DAILY_TRADE_IDEA',
                    targetId: $idea->id,
                    newValues: $this->auditSnapshot($idea),
                    ipAddress: $request->ip(),
                    userAgent: $request->userAgent()
                );

                return $idea;
            });
        } catch (\Throwable $exception) {
            $this->deleteChartImage(
                $chartImage['disk'] ?? null,
                $chartImage['path'] ?? null
            );

            throw $exception;
        }

        return redirect()
            ->route('admin.daily_trade_ideas.show', ['dailyTradeIdea' => $idea])
            ->with('success', 'Daily Trade Idea created successfully as draft.');
    }

    public function show(DailyTradeIdea $dailyTradeIdea): View
    {
        $admin = $this->authenticatedAdmin();
        $this->requirePermission($admin, 'trade_ideas.view');

        $dailyTradeIdea->load([
            'targets',
            'createdByAdmin',
            'updatedByAdmin',
        ]);

        $permissions = [
            'update' => $admin->hasPermission('trade_ideas.update'),
            'publish' => $admin->hasPermission('trade_ideas.publish'),
            'archive' => $admin->hasPermission('trade_ideas.close'),
        ];

        return view('admin.trade_ideas.daily.show', compact(
            'dailyTradeIdea',
            'permissions'
        ));
    }

    public function edit(DailyTradeIdea $dailyTradeIdea): View
    {
        $admin = $this->authenticatedAdmin();
        $this->requirePermission($admin, 'trade_ideas.update');

        abort_unless($dailyTradeIdea->isDraft(), 409, 'Only draft Daily Trade Ideas can be edited.');

        $dailyTradeIdea->load('targets');

        return view('admin.trade_ideas.daily.edit', compact('dailyTradeIdea'));
    }

    public function update(
        UpdateTradeIdeaRequest $request,
        DailyTradeIdea $dailyTradeIdea
    ): RedirectResponse {
        $admin = $this->authenticatedAdmin();
        $this->requirePermission($admin, 'trade_ideas.update');

        abort_unless($dailyTradeIdea->isDraft(), 409, 'Only draft Daily Trade Ideas can be edited.');

        $validated = $request->validated();
        $oldChart = [
            'disk' => $dailyTradeIdea->chart_image_disk,
            'path' => $dailyTradeIdea->chart_image_path,
        ];

        $newChart = null;

        if ($request->file('chart_image') instanceof UploadedFile) {
            $newChart = $this->storeChartImage(
                $request->file('chart_image'),
                $dailyTradeIdea->uuid
            );
        }

        $removeChart = (bool) ($validated['remove_chart_image'] ?? false);

        try {
            DB::transaction(function () use (
                $dailyTradeIdea,
                $validated,
                $request,
                $admin,
                $newChart,
                $removeChart
            ): void {
                $attributes = [
                    'title' => $validated['title'],
                    'summary' => $validated['summary'] ?? null,
                    'analysis' => $validated['analysis'] ?? null,
                    'tradingview_url' => TradingViewUrl::normalize(
                        $validated['tradingview_url'] ?? null
                    ),
                    'category' => $validated['category'],
                    'market_region' => $validated['market_region'] ?? null,
                    'symbol' => $validated['symbol'],
                    'instrument_name' => $validated['instrument_name'] ?? null,
                    'direction' => $validated['direction'],
                    'timeframe' => $validated['timeframe'],
                    'entry_price_low' => $validated['entry_price_low'] ?? null,
                    'entry_price_high' => $validated['entry_price_high'] ?? null,
                    'stop_loss' => $validated['stop_loss'] ?? null,
                    'updated_by_admin_id' => $admin->id,
                ];

                if ($newChart !== null) {
                    $attributes = array_merge($attributes, [
                        'chart_image_disk' => $newChart['disk'],
                        'chart_image_path' => $newChart['path'],
                        'chart_image_mime_type' => $newChart['mime_type'],
                        'chart_image_size_bytes' => $newChart['size_bytes'],
                    ]);
                } elseif ($removeChart) {
                    $attributes = array_merge($attributes, [
                        'chart_image_disk' => null,
                        'chart_image_path' => null,
                        'chart_image_mime_type' => null,
                        'chart_image_size_bytes' => null,
                    ]);
                }

                $before = $this->auditSnapshot($dailyTradeIdea->fresh('targets'));

                $dailyTradeIdea->update($attributes);
                $this->replaceTargets($dailyTradeIdea, $validated['targets'] ?? []);
                $dailyTradeIdea->load('targets');

                AuditLog::record(
                    actorType: 'ADMIN',
                    actorId: $admin->id,
                    action: 'DAILY_TRADE_IDEA_UPDATED',
                    module: 'TRADE_IDEAS',
                    targetType: 'DAILY_TRADE_IDEA',
                    targetId: $dailyTradeIdea->id,
                    oldValues: $before,
                    newValues: $this->auditSnapshot($dailyTradeIdea),
                    ipAddress: $request->ip(),
                    userAgent: $request->userAgent()
                );
            });
        } catch (\Throwable $exception) {
            if ($newChart !== null) {
                $this->deleteChartImage($newChart['disk'], $newChart['path']);
            }

            throw $exception;
        }

        if ($newChart !== null || $removeChart) {
            $this->deleteChartImage($oldChart['disk'], $oldChart['path']);
        }

        return redirect()
            ->route('admin.daily_trade_ideas.show', ['dailyTradeIdea' => $dailyTradeIdea])
            ->with('success', 'Daily Trade Idea draft updated successfully.');
    }

    public function publish(Request $request, DailyTradeIdea $dailyTradeIdea): RedirectResponse
    {
        $admin = $this->authenticatedAdmin();
        $this->requirePermission($admin, 'trade_ideas.publish');

        abort_unless($dailyTradeIdea->isDraft(), 409, 'Only draft Daily Trade Ideas can be published.');

        $before = $this->auditSnapshot($dailyTradeIdea->fresh('targets'));

        $dailyTradeIdea->update([
            'status' => DailyTradeIdea::STATUS_PUBLISHED,
            'published_at' => now(),
            'archived_at' => null,
            'updated_by_admin_id' => $admin->id,
        ]);

        AuditLog::record(
            actorType: 'ADMIN',
            actorId: $admin->id,
            action: 'DAILY_TRADE_IDEA_PUBLISHED',
            module: 'TRADE_IDEAS',
            targetType: 'DAILY_TRADE_IDEA',
            targetId: $dailyTradeIdea->id,
            oldValues: $before,
            newValues: $this->auditSnapshot($dailyTradeIdea->fresh('targets')),
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        return redirect()
            ->route('admin.daily_trade_ideas.show', ['dailyTradeIdea' => $dailyTradeIdea])
            ->with('success', 'Daily Trade Idea published.');
    }

    public function archive(Request $request, DailyTradeIdea $dailyTradeIdea): RedirectResponse
    {
        $admin = $this->authenticatedAdmin();
        $this->requirePermission($admin, 'trade_ideas.close');

        abort_unless(
            $dailyTradeIdea->isPublished(),
            409,
            'Only published Daily Trade Ideas can be archived.'
        );

        $before = $this->auditSnapshot($dailyTradeIdea->fresh('targets'));

        $dailyTradeIdea->update([
            'status' => DailyTradeIdea::STATUS_ARCHIVED,
            'archived_at' => now(),
            'updated_by_admin_id' => $admin->id,
        ]);

        AuditLog::record(
            actorType: 'ADMIN',
            actorId: $admin->id,
            action: 'DAILY_TRADE_IDEA_ARCHIVED',
            module: 'TRADE_IDEAS',
            targetType: 'DAILY_TRADE_IDEA',
            targetId: $dailyTradeIdea->id,
            oldValues: $before,
            newValues: $this->auditSnapshot($dailyTradeIdea->fresh('targets')),
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        return redirect()
            ->route('admin.daily_trade_ideas.show', ['dailyTradeIdea' => $dailyTradeIdea])
            ->with('success', 'Daily Trade Idea archived.');
    }

    public function chartImage(DailyTradeIdea $dailyTradeIdea): StreamedResponse
    {
        $admin = $this->authenticatedAdmin();
        $this->requirePermission($admin, 'trade_ideas.view');

        abort_unless($dailyTradeIdea->hasChartImage(), 404);
        abort_unless($dailyTradeIdea->chart_image_disk === 'private', 404);
        abort_unless(
            Storage::disk('private')->exists($dailyTradeIdea->chart_image_path),
            404
        );

        $mimeType = $dailyTradeIdea->chart_image_mime_type ?: 'application/octet-stream';

        abort_unless(in_array($mimeType, [
            'image/jpeg',
            'image/png',
            'image/webp',
        ], true), 404);

        return response()->stream(
            function () use ($dailyTradeIdea): void {
                $stream = Storage::disk('private')
                    ->readStream($dailyTradeIdea->chart_image_path);

                abort_if($stream === false, 404);

                try {
                    fpassthru($stream);
                } finally {
                    fclose($stream);
                }
            },
            200,
            [
                'Content-Type' => $mimeType,
                'Cache-Control' => 'private, no-store, no-cache, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
                'Expires' => '0',
                'X-Content-Type-Options' => 'nosniff',
                'Content-Disposition' => 'inline',
            ]
        );
    }

    private function replaceTargets(DailyTradeIdea $idea, array $targets): void
    {
        $idea->targets()->delete();

        foreach (array_values($targets) as $index => $target) {
            DailyTradeIdeaTarget::query()->create([
                'daily_trade_idea_id' => $idea->id,
                'sort_order' => $index + 1,
                'price' => $target['price'],
            ]);
        }
    }

    private function storeChartImage(?UploadedFile $file, string $uuid): ?array
    {
        if (! $file instanceof UploadedFile) {
            return null;
        }

        $disk = 'private';
        $extension = strtolower((string) $file->extension());
        $filename = Str::uuid()->toString().'.'.$extension;
        $path = $file->storeAs('daily-trade-ideas/'.$uuid, $filename, $disk);

        return [
            'disk' => $disk,
            'path' => $path,
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
        ];
    }

    private function deleteChartImage(?string $disk, ?string $path): void
    {
        if ($disk === null || $path === null || $disk !== 'private') {
            return;
        }

        Storage::disk($disk)->delete($path);
    }

    private function auditSnapshot(DailyTradeIdea $idea): array
    {
        $idea->loadMissing('targets');

        return [
            'uuid' => $idea->uuid,
            'title' => $idea->title,
            'category' => $idea->category,
            'market_region' => $idea->market_region,
            'symbol' => $idea->symbol,
            'direction' => $idea->direction,
            'timeframe' => $idea->timeframe,
            'entry_price_low' => $idea->entry_price_low,
            'entry_price_high' => $idea->entry_price_high,
            'stop_loss' => $idea->stop_loss,
            'targets' => $idea->targets
                ->map(fn (DailyTradeIdeaTarget $target): string => (string) $target->price)
                ->values()
                ->all(),
            'status' => $idea->status,
            'published_at' => $idea->published_at?->toISOString(),
            'archived_at' => $idea->archived_at?->toISOString(),
            'has_chart_image' => $idea->hasChartImage(),
        ];
    }

    private function authenticatedAdmin(): AdminUser
    {
        $admin = auth('admin')->user();

        abort_unless($admin instanceof AdminUser, 401);

        return $admin;
    }

    private function requirePermission(AdminUser $admin, string $permission): void
    {
        abort_unless($admin->hasPermission($permission), 403);
    }
}
