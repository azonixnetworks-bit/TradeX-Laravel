<?php

namespace App\Http\Controllers\Admin\TradeIdeas;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TradeIdeas\CloseTradeIdeaRequest;
use App\Http\Requests\Admin\TradeIdeas\StoreTradeIdeaRequest;
use App\Http\Requests\Admin\TradeIdeas\StoreTradeIdeaUpdateRequest;
use App\Http\Requests\Admin\TradeIdeas\UpdateTradeIdeaPublicationRequest;
use App\Http\Requests\Admin\TradeIdeas\UpdateTradeIdeaRequest;
use App\Models\AdminUser;
use App\Models\AuditLog;
use App\Models\TradeIdea;
use App\Models\TradeIdeaDistribution;
use App\Models\TradeIdeaTarget;
use App\Models\TradeIdeaUpdate;
use App\Services\TradeIdeas\TradeIdeaDistributionManager;
use App\Services\TradeIdeas\TradeIdeaShareCardService;
use App\Services\TradeIdeas\TradeIdeaStructureValidator;
use App\Support\TradingViewUrl;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TradeIdeaController extends Controller
{
    public function index(
        Request $request
    ): View {
        $admin = $this->authenticatedAdmin();

        $this->requirePermission(
            $admin,
            'trade_ideas.view'
        );

        $status = strtoupper(
            trim(
                (string) $request->query(
                    'status',
                    ''
                )
            )
        );

        $category = strtoupper(
            trim(
                (string) $request->query(
                    'category',
                    ''
                )
            )
        );

        $marketFilter = strtoupper(
            trim(
                (string) $request->query(
                    'market_filter',
                    ''
                )
            )
        );

        $query = TradeIdea::query()
            ->with('targets');

        if (
            in_array(
                $status,
                [
                    TradeIdea::STATUS_DRAFT,
                    TradeIdea::STATUS_PUBLISHED,
                    TradeIdea::STATUS_CLOSED,
                ],
                true
            )
        ) {
            $query->where(
                'status',
                $status
            );
        }

        if ($marketFilter !== '') {
            $this->applyMarketFilter(
                $query,
                $marketFilter
            );
        } elseif (
            in_array(
                $category,
                [
                    TradeIdea::CATEGORY_FOREX,
                    TradeIdea::CATEGORY_CRYPTO,
                    TradeIdea::CATEGORY_DERIV,
                    TradeIdea::CATEGORY_STOCKS,
                ],
                true
            )
        ) {
            // Backward compatibility for existing category query links.
            $query->where(
                'category',
                $category
            );
        }

        $tradeIdeas = $query
            ->latestFirst()
            ->get();

        $stats = [
            'total' => TradeIdea::query()->count(),

            'draft' => TradeIdea::query()
                ->where(
                    'status',
                    TradeIdea::STATUS_DRAFT
                )
                ->count(),

            'published' => TradeIdea::query()
                ->where(
                    'status',
                    TradeIdea::STATUS_PUBLISHED
                )
                ->count(),

            'closed' => TradeIdea::query()
                ->where(
                    'status',
                    TradeIdea::STATUS_CLOSED
                )
                ->count(),
        ];

        $permissions = $this->permissions(
            $admin
        );

        return view(
            'admin.trade_ideas.index',
            compact(
                'tradeIdeas',
                'stats',
                'permissions',
                'status',
                'category',
                'marketFilter'
            )
        );
    }

    private function applyMarketFilter(
        \Illuminate\Database\Eloquent\Builder $query,
        string $marketFilter
    ): void {
        match ($marketFilter) {
            'TODAY' => $this->applyTodayFilter($query),

            'FOREX' => $query->where(
                'category',
                TradeIdea::CATEGORY_FOREX
            ),

            'CRYPTO' => $query->where(
                'category',
                TradeIdea::CATEGORY_CRYPTO
            ),

            'DERIV' => $query->where(
                'category',
                TradeIdea::CATEGORY_DERIV
            ),

            'STOCKS_CSE' => $query
                ->where(
                    'category',
                    TradeIdea::CATEGORY_STOCKS
                )
                ->where(
                    'market_region',
                    TradeIdea::REGION_SRI_LANKA
                ),

            'STOCKS_INDIA' => $query
                ->where(
                    'category',
                    TradeIdea::CATEGORY_STOCKS
                )
                ->where(
                    'market_region',
                    TradeIdea::REGION_INDIA
                ),

            'STOCKS_INTERNATIONAL' => $query
                ->where(
                    'category',
                    TradeIdea::CATEGORY_STOCKS
                )
                ->where(
                    'market_region',
                    TradeIdea::REGION_INTERNATIONAL
                ),

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

        $this->requirePermission(
            $admin,
            'trade_ideas.create'
        );

        return view(
            'admin.trade_ideas.create'
        );
    }

    public function store(
        StoreTradeIdeaRequest $request
    ): RedirectResponse {
        $admin = $this->authenticatedAdmin();

        $this->requirePermission(
            $admin,
            'trade_ideas.create'
        );

        $validated = $request->validated();

        $tradeIdeaUuid = Str::uuid()->toString();

        $chartImage = $this->storeChartImage(
            $request->file('chart_image'),
            $tradeIdeaUuid
        );

        try {
            $tradeIdea = DB::transaction(function () use (
                $validated,
                $request,
                $admin,
                $tradeIdeaUuid,
                $chartImage
            ): TradeIdea {
                $tradeIdea = TradeIdea::query()->create([
                    'uuid' => $tradeIdeaUuid,
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
                    'current_stop_loss' => null,
                    'entry_triggered_at' => null,
                    'entry_price_actual' => null,

                    'status' => TradeIdea::STATUS_DRAFT,
                    'published_at' => null,
                    'closed_at' => null,
                    'close_outcome' => null,
                    'close_price' => null,
                    'close_note' => null,

                    'created_by_admin_id' => $admin->id,
                    'updated_by_admin_id' => $admin->id,
                ]);

                $this->replaceTargets(
                    $tradeIdea,
                    $validated['targets'] ?? []
                );

                $tradeIdea->load(
                    'targets'
                );

                AuditLog::record(
                    actorType: 'ADMIN',
                    actorId: $admin->id,
                    action: 'TRADE_IDEA_CREATED',
                    module: 'TRADE_IDEAS',
                    targetType: 'TRADE_IDEA',
                    targetId: $tradeIdea->id,
                    newValues: $this->auditSnapshot(
                        $tradeIdea
                    ),
                    ipAddress: $request->ip(),
                    userAgent: $request->userAgent()
                );

                return $tradeIdea;
            });
        } catch (\Throwable $exception) {
            $this->deleteChartImage(
                $chartImage['disk'] ?? null,
                $chartImage['path'] ?? null
            );

            throw $exception;
        }

        return redirect()
            ->route(
                'admin.trade_ideas.show',
                [
                    'tradeIdea' => $tradeIdea,
                ]
            )
            ->with(
                'success',
                'Trade Idea created successfully as draft.'
            );
    }

    public function show(
        TradeIdea $tradeIdea
    ): View {
        $admin = $this->authenticatedAdmin();

        $this->requirePermission(
            $admin,
            'trade_ideas.view'
        );

        $tradeIdea->load([
            'targets',
            'updates.target',
            'updates.createdByAdmin',
            'createdByAdmin',
            'updatedByAdmin',
        ]);

        $permissions = $this->permissions(
            $admin
        );

        $distributions =
            $tradeIdea->distributions()
                ->with(
                    'createdByAdmin'
                )
                ->limit(
                    30
                )
                ->get();

        $distributionHealth = app(
            TradeIdeaDistributionManager::class
        )->health();

        return view(
            'admin.trade_ideas.show',
            compact(
                'tradeIdea',
                'permissions',
                'distributions',
                'distributionHealth'
            )
        );
    }

    public function chartImage(
        TradeIdea $tradeIdea
    ): StreamedResponse {
        $admin = $this->authenticatedAdmin();

        $this->requirePermission(
            $admin,
            'trade_ideas.view'
        );

        abort_unless(
            $tradeIdea->hasChartImage(),
            404
        );

        abort_unless(
            $tradeIdea->chart_image_disk === 'private',
            404
        );

        abort_unless(
            Storage::disk('private')->exists(
                $tradeIdea->chart_image_path
            ),
            404
        );

        $mimeType = $tradeIdea->chart_image_mime_type
            ?: 'application/octet-stream';

        abort_unless(
            in_array(
                $mimeType,
                [
                    'image/jpeg',
                    'image/png',
                    'image/webp',
                ],
                true
            ),
            404
        );

        return response()->stream(
            function () use (
                $tradeIdea
            ): void {
                $stream = Storage::disk('private')
                    ->readStream(
                        $tradeIdea->chart_image_path
                    );

                abort_if(
                    $stream === false,
                    404
                );

                try {
                    fpassthru(
                        $stream
                    );
                } finally {
                    fclose(
                        $stream
                    );
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

    public function shareCard(
        TradeIdea $tradeIdea
    ): \Illuminate\Http\Response {
        $admin = $this->authenticatedAdmin();

        $this->requirePermission(
            $admin,
            'trade_ideas.view'
        );

        $svg = app(
            TradeIdeaShareCardService::class
        )->renderSvg(
            $tradeIdea
        );

        return response(
            $svg,
            200,
            [
                'Content-Type' =>
                    'image/svg+xml; charset=UTF-8',

                'Cache-Control' =>
                    'private, no-store, no-cache, must-revalidate, max-age=0',

                'Pragma' =>
                    'no-cache',

                'Expires' =>
                    '0',

                'X-Content-Type-Options' =>
                    'nosniff',

                'Content-Disposition' =>
                    'inline; filename="tradex-trade-idea.svg"',
            ]
        );
    }

    public function distribute(
        Request $request,
        TradeIdea $tradeIdea
    ): RedirectResponse {
        $admin = $this->authenticatedAdmin();

        $this->requirePermission(
            $admin,
            'trade_ideas.distribute'
        );

        abort_unless(
            $tradeIdea->isPublished()
            || $tradeIdea->isClosed(),
            422,
            'Only published or closed Trade Ideas can be distributed.'
        );

        $summary = app(
            TradeIdeaDistributionManager::class
        )->queueManual(
            $tradeIdea->fresh([
                'targets',
            ]),
            $admin
        );

        AuditLog::record(
            actorType: 'ADMIN',
            actorId: $admin->id,
            action: 'TRADE_IDEA_DISTRIBUTION_QUEUED',
            module: 'TRADE_IDEAS',
            targetType: 'TRADE_IDEA',
            targetId: $tradeIdea->id,
            newValues: [
                'created' =>
                    $summary['created'],

                'existing' =>
                    $summary['existing'],

                'configured_destinations' =>
                    $summary['configured_destinations'],
            ],
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        if (
            $summary['configured_destinations']
            === 0
        ) {
            return redirect()
                ->route(
                    'admin.trade_ideas.show',
                    [
                        'tradeIdea' =>
                            $tradeIdea,
                    ]
                )
                ->with(
                    'error',
                    'No ready Telegram or WhatsApp distribution destination is configured.'
                );
        }

        return redirect()
            ->route(
                'admin.trade_ideas.show',
                [
                    'tradeIdea' =>
                        $tradeIdea,
                ]
            )
            ->with(
                'success',
                $summary['created'] > 0
                    ? 'Trade Idea distribution queued successfully.'
                    : 'This exact Trade Idea payload is already queued or sent to the configured destinations.'
            );
    }

    public function retryDistribution(
        Request $request,
        TradeIdea $tradeIdea,
        TradeIdeaDistribution $distribution
    ): RedirectResponse {
        $admin = $this->authenticatedAdmin();

        $this->requirePermission(
            $admin,
            'trade_ideas.distribute'
        );

        abort_unless(
            $distribution->trade_idea_id
                === $tradeIdea->id,
            404
        );

        $retried = app(
            TradeIdeaDistributionManager::class
        )->retry(
            $distribution
        );

        if ($retried) {
            AuditLog::record(
                actorType: 'ADMIN',
                actorId: $admin->id,
                action: 'TRADE_IDEA_DISTRIBUTION_RETRIED',
                module: 'TRADE_IDEAS',
                targetType: 'TRADE_IDEA_DISTRIBUTION',
                targetId: $distribution->id,
                newValues: [
                    'trade_idea_id' =>
                        $tradeIdea->id,

                    'channel' =>
                        $distribution->channel,

                    'status' =>
                        TradeIdeaDistribution::STATUS_PENDING,
                ],
                ipAddress: $request->ip(),
                userAgent: $request->userAgent()
            );
        }

        return redirect()
            ->route(
                'admin.trade_ideas.show',
                [
                    'tradeIdea' =>
                        $tradeIdea,
                ]
            )
            ->with(
                $retried
                    ? 'success'
                    : 'error',
                $retried
                    ? 'Failed Trade Idea distribution queued for retry.'
                    : 'This distribution is already processing or has already been sent.'
            );
    }

    public function edit(
        TradeIdea $tradeIdea
    ): View {
        $admin = $this->authenticatedAdmin();

        $this->requirePermission(
            $admin,
            'trade_ideas.update'
        );

        abort_unless(
            $tradeIdea->isDraft(),
            422,
            'Published or closed Trade Ideas cannot be edited. Add an audited update instead.'
        );

        $tradeIdea->load(
            'targets'
        );

        return view(
            'admin.trade_ideas.edit',
            compact(
                'tradeIdea'
            )
        );
    }

    public function update(
        UpdateTradeIdeaRequest $request,
        TradeIdea $tradeIdea
    ): RedirectResponse {
        $admin = $this->authenticatedAdmin();

        $this->requirePermission(
            $admin,
            'trade_ideas.update'
        );

        $validated = $request->validated();

        $incomingChartImage = $this->storeChartImage(
            $request->file('chart_image'),
            $tradeIdea->uuid
        );

        $oldImageToDelete = null;

        try {
            DB::transaction(function () use (
                $validated,
                $request,
                $tradeIdea,
                $admin,
                $incomingChartImage,
                &$oldImageToDelete
            ): void {
                $locked = TradeIdea::query()
                    ->lockForUpdate()
                    ->findOrFail(
                        $tradeIdea->id
                    );

                abort_unless(
                    $locked->isDraft(),
                    422,
                    'Published or closed Trade Ideas cannot be edited.'
                );

                $locked->load(
                    'targets'
                );

                $before = $this->auditSnapshot(
                    $locked
                );

                $locked->fill([
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
                ]);

                if (
                    ($validated['remove_chart_image'] ?? false)
                    || $incomingChartImage !== null
                ) {
                    if ($locked->hasChartImage()) {
                        $oldImageToDelete = [
                            'disk' => $locked->chart_image_disk,
                            'path' => $locked->chart_image_path,
                        ];
                    }

                    $locked->chart_image_disk =
                        $incomingChartImage['disk'] ?? null;

                    $locked->chart_image_path =
                        $incomingChartImage['path'] ?? null;

                    $locked->chart_image_mime_type =
                        $incomingChartImage['mime_type'] ?? null;

                    $locked->chart_image_size_bytes =
                        $incomingChartImage['size_bytes'] ?? null;
                }

                $locked->save();

                $this->replaceTargets(
                    $locked,
                    $validated['targets'] ?? []
                );

                $locked->load(
                    'targets'
                );

                [
                    $oldValues,
                    $newValues,
                ] = $this->auditChanges(
                    $before,
                    $this->auditSnapshot(
                        $locked
                    )
                );

                if (
                    $oldValues !== []
                    || $newValues !== []
                ) {
                    AuditLog::record(
                        actorType: 'ADMIN',
                        actorId: $admin->id,
                        action: 'TRADE_IDEA_UPDATED',
                        module: 'TRADE_IDEAS',
                        targetType: 'TRADE_IDEA',
                        targetId: $locked->id,
                        oldValues: $oldValues,
                        newValues: $newValues,
                        ipAddress: $request->ip(),
                        userAgent: $request->userAgent()
                    );
                }
            });
        } catch (\Throwable $exception) {
            $this->deleteChartImage(
                $incomingChartImage['disk'] ?? null,
                $incomingChartImage['path'] ?? null
            );

            throw $exception;
        }

        if ($oldImageToDelete !== null) {
            $this->deleteChartImage(
                $oldImageToDelete['disk'],
                $oldImageToDelete['path']
            );
        }

        return redirect()
            ->route(
                'admin.trade_ideas.show',
                [
                    'tradeIdea' => $tradeIdea,
                ]
            )
            ->with(
                'success',
                'Trade Idea updated successfully.'
            );
    }

    public function publication(
        UpdateTradeIdeaPublicationRequest $request,
        TradeIdea $tradeIdea
    ): RedirectResponse {
        $admin = $this->authenticatedAdmin();

        $this->requirePermission(
            $admin,
            'trade_ideas.publish'
        );

        $reason = $request->auditReason();

        DB::transaction(function () use (
            $request,
            $tradeIdea,
            $admin,
            $reason
        ): void {
            $locked = TradeIdea::query()
                ->lockForUpdate()
                ->findOrFail(
                    $tradeIdea->id
                );

            abort_unless(
                $locked->isDraft(),
                422,
                'Only a draft Trade Idea can be published.'
            );

            $locked->load(
                'targets'
            );

            // Trade Structure is optional, but supplied values must remain
            // structurally valid even if the database was modified outside
            // the normal request path.
            $structureErrors = app(
                TradeIdeaStructureValidator::class
            )->errors(
                $locked->direction,
                $locked->entry_price_low,
                $locked->entry_price_high,
                $locked->stop_loss,
                $locked->targets
                    ->map(
                        fn (TradeIdeaTarget $target): array => [
                            'price' => $target->price,
                        ]
                    )
                    ->all()
            );

            if ($structureErrors !== []) {
                throw ValidationException::withMessages(
                    $structureErrors
                );
            }

            $before = $this->auditSnapshot(
                $locked
            );

            $locked->status = TradeIdea::STATUS_PUBLISHED;
            $locked->published_at = now();

            // Initial Stop Loss is optional.
            $locked->current_stop_loss = $locked->stop_loss;

            $locked->updated_by_admin_id = $admin->id;
            $locked->save();

            $locked->load(
                'targets'
            );

            [
                $oldValues,
                $newValues,
            ] = $this->auditChanges(
                $before,
                $this->auditSnapshot(
                    $locked
                )
            );

            if ($reason !== null) {
                $newValues['reason'] = $reason;
            }

            AuditLog::record(
                actorType: 'ADMIN',
                actorId: $admin->id,
                action: 'TRADE_IDEA_PUBLISHED',
                module: 'TRADE_IDEAS',
                targetType: 'TRADE_IDEA',
                targetId: $locked->id,
                oldValues: $oldValues,
                newValues: $newValues,
                ipAddress: $request->ip(),
                userAgent: $request->userAgent()
            );
        });

        if (
            config(
                'tradex_trade_ideas.auto.on_publish',
                false
            )
        ) {
            $this->queueAutomaticDistribution(
                TradeIdeaDistribution::EVENT_INITIAL,
                $tradeIdea->fresh([
                    'targets',
                ]),
                $admin,
                null,
                $request
            );
        }

        return redirect()
            ->route(
                'admin.trade_ideas.show',
                [
                    'tradeIdea' => $tradeIdea,
                ]
            )
            ->with(
                'success',
                'Trade Idea published successfully. Core setup is now locked.'
            );
    }

    public function addUpdate(
        StoreTradeIdeaUpdateRequest $request,
        TradeIdea $tradeIdea
    ): RedirectResponse {
        $admin = $this->authenticatedAdmin();

        $this->requirePermission(
            $admin,
            'trade_ideas.update'
        );

        $validated = $request->validated();

        $timelineId = DB::transaction(function () use (
            $validated,
            $request,
            $tradeIdea,
            $admin
        ): int {
            $locked = TradeIdea::query()
                ->lockForUpdate()
                ->findOrFail(
                    $tradeIdea->id
                );

            abort_unless(
                $locked->isPublished(),
                422,
                'Timeline updates can only be added to a published/open Trade Idea.'
            );

            $type = $validated['type'];
            $price = $validated['price'] ?? null;
            $target = null;

            if (
                $type
                === TradeIdeaUpdate::TYPE_ENTRY_TRIGGERED
            ) {
                abort_if(
                    $locked->entry_triggered_at !== null,
                    422,
                    'Entry has already been marked as triggered.'
                );

                $locked->entry_triggered_at = now();
                $locked->entry_price_actual = $price;
            } elseif (
                $type
                === TradeIdeaUpdate::TYPE_STOP_MOVED
            ) {
                $locked->current_stop_loss = $price;
            } elseif (
                $type
                === TradeIdeaUpdate::TYPE_TARGET_HIT
            ) {
                $target = TradeIdeaTarget::query()
                    ->where(
                        'trade_idea_id',
                        $locked->id
                    )
                    ->lockForUpdate()
                    ->findOrFail(
                        (int) $validated['target_id']
                    );

                abort_if(
                    $target->isHit(),
                    422,
                    'This target has already been marked as hit.'
                );

                $target->hit_at = now();
                $target->hit_price =
                    $price ?? $target->price;

                $target->hit_by_admin_id =
                    $admin->id;

                $target->save();

                $price = $target->hit_price;
            }

            $locked->updated_by_admin_id = $admin->id;
            $locked->save();

            $timeline = TradeIdeaUpdate::query()->create([
                'trade_idea_id' => $locked->id,
                'trade_idea_target_id' => $target?->id,
                'type' => $type,
                'message' => $this->timelineMessage(
                    $type,
                    $validated['message'] ?? null,
                    $price,
                    $target
                ),
                'price' => $price,
                'created_by_admin_id' => $admin->id,
            ]);

            AuditLog::record(
                actorType: 'ADMIN',
                actorId: $admin->id,
                action: 'TRADE_IDEA_TIMELINE_UPDATE_ADDED',
                module: 'TRADE_IDEAS',
                targetType: 'TRADE_IDEA',
                targetId: $locked->id,
                newValues: [
                    'update_id' => $timeline->id,
                    'type' => $timeline->type,
                    'target_id' => $timeline->trade_idea_target_id,
                    'price' => $timeline->price,

                    'message_sha256' => $timeline->message !== null
                        ? hash(
                            'sha256',
                            $timeline->message
                        )
                        : null,

                    'message_length' => $timeline->message !== null
                        ? mb_strlen(
                            $timeline->message
                        )
                        : 0,
                ],
                ipAddress: $request->ip(),
                userAgent: $request->userAgent()
            );

            return $timeline->id;
        });

        if (
            config(
                'tradex_trade_ideas.auto.on_update',
                false
            )
        ) {
            $timeline =
                TradeIdeaUpdate::query()
                    ->findOrFail(
                        $timelineId
                    );

            $this->queueAutomaticDistribution(
                TradeIdeaDistribution::EVENT_UPDATE,
                $tradeIdea->fresh([
                    'targets',
                ]),
                $admin,
                $timeline,
                $request
            );
        }

        return redirect()
            ->route(
                'admin.trade_ideas.show',
                [
                    'tradeIdea' => $tradeIdea,
                ]
            )
            ->with(
                'success',
                'Trade Idea timeline updated successfully.'
            );
    }

    public function close(
        CloseTradeIdeaRequest $request,
        TradeIdea $tradeIdea
    ): RedirectResponse {
        $admin = $this->authenticatedAdmin();

        $this->requirePermission(
            $admin,
            'trade_ideas.close'
        );

        $validated = $request->validated();

        $closeTimelineId = DB::transaction(function () use (
            $validated,
            $request,
            $tradeIdea,
            $admin
        ): int {
            $locked = TradeIdea::query()
                ->lockForUpdate()
                ->findOrFail(
                    $tradeIdea->id
                );

            abort_unless(
                $locked->isPublished(),
                422,
                'Only a published/open Trade Idea can be closed.'
            );

            $locked->load(
                'targets'
            );

            $before = $this->auditSnapshot(
                $locked
            );

            $locked->status = TradeIdea::STATUS_CLOSED;
            $locked->closed_at = now();
            $locked->close_outcome = $validated['outcome'];
            $locked->close_price = $validated['close_price'] ?? null;
            $locked->close_note = $validated['note'];
            $locked->updated_by_admin_id = $admin->id;
            $locked->save();

            $closeTimeline =
                TradeIdeaUpdate::query()->create([
                    'trade_idea_id' => $locked->id,
                    'trade_idea_target_id' => null,
                    'type' => TradeIdeaUpdate::TYPE_CLOSED,
                    'message' => $validated['note'],
                    'price' => $validated['close_price'] ?? null,
                    'created_by_admin_id' => $admin->id,
                ]);

            $locked->load(
                'targets'
            );

            [
                $oldValues,
                $newValues,
            ] = $this->auditChanges(
                $before,
                $this->auditSnapshot(
                    $locked
                )
            );

            AuditLog::record(
                actorType: 'ADMIN',
                actorId: $admin->id,
                action: 'TRADE_IDEA_CLOSED',
                module: 'TRADE_IDEAS',
                targetType: 'TRADE_IDEA',
                targetId: $locked->id,
                oldValues: $oldValues,
                newValues: $newValues,
                ipAddress: $request->ip(),
                userAgent: $request->userAgent()
            );

            return $closeTimeline->id;
        });

        if (
            config(
                'tradex_trade_ideas.auto.on_close',
                false
            )
        ) {
            $closeTimeline =
                TradeIdeaUpdate::query()
                    ->findOrFail(
                        $closeTimelineId
                    );

            $this->queueAutomaticDistribution(
                TradeIdeaDistribution::EVENT_CLOSED,
                $tradeIdea->fresh([
                    'targets',
                ]),
                $admin,
                $closeTimeline,
                $request
            );
        }

        return redirect()
            ->route(
                'admin.trade_ideas.show',
                [
                    'tradeIdea' => $tradeIdea,
                ]
            )
            ->with(
                'success',
                'Trade Idea closed successfully.'
            );
    }

    public function destroy(
        Request $request,
        TradeIdea $tradeIdea
    ): RedirectResponse {
        $admin = $this->authenticatedAdmin();

        $this->requirePermission(
            $admin,
            'trade_ideas.delete'
        );

        $imageToDelete = null;

        DB::transaction(function () use (
            $request,
            $tradeIdea,
            $admin,
            &$imageToDelete
        ): void {
            $locked = TradeIdea::query()
                ->lockForUpdate()
                ->findOrFail(
                    $tradeIdea->id
                );

            abort_unless(
                $locked->isDraft(),
                422,
                'Only a draft Trade Idea can be deleted. Published history must be retained.'
            );

            $locked->load(
                'targets'
            );

            $before = $this->auditSnapshot(
                $locked
            );

            if ($locked->hasChartImage()) {
                $imageToDelete = [
                    'disk' => $locked->chart_image_disk,
                    'path' => $locked->chart_image_path,
                ];
            }

            $locked->updated_by_admin_id = $admin->id;
            $locked->save();
            $locked->delete();

            AuditLog::record(
                actorType: 'ADMIN',
                actorId: $admin->id,
                action: 'TRADE_IDEA_DELETED',
                module: 'TRADE_IDEAS',
                targetType: 'TRADE_IDEA',
                targetId: $locked->id,
                oldValues: $before,
                newValues: [
                    'deleted_at' => $locked->deleted_at?->toISOString(),
                ],
                ipAddress: $request->ip(),
                userAgent: $request->userAgent()
            );
        });

        if ($imageToDelete !== null) {
            $this->deleteChartImage(
                $imageToDelete['disk'],
                $imageToDelete['path']
            );
        }

        return redirect()
            ->route(
                'admin.trade_ideas.index'
            )
            ->with(
                'success',
                'Draft Trade Idea deleted successfully.'
            );
    }

    /**
     * @param array<int, array{price:mixed}> $targets
     */
    private function replaceTargets(
        TradeIdea $tradeIdea,
        array $targets
    ): void {
        $tradeIdea->targets()
            ->delete();

        foreach (
            array_values(
                $targets
            )
            as $index => $target
        ) {
            TradeIdeaTarget::query()->create([
                'trade_idea_id' => $tradeIdea->id,
                'label' => 'TP'.($index + 1),
                'price' => $target['price'],
                'sort_order' => $index + 1,
                'hit_at' => null,
                'hit_price' => null,
                'hit_by_admin_id' => null,
            ]);
        }
    }

    private function timelineMessage(
        string $type,
        ?string $message,
        mixed $price,
        ?TradeIdeaTarget $target
    ): string {
        $message = trim(
            (string) $message
        );

        if ($message !== '') {
            return $message;
        }

        return match ($type) {
            TradeIdeaUpdate::TYPE_ENTRY_TRIGGERED =>
                'Entry triggered at '.$price.'.',

            TradeIdeaUpdate::TYPE_STOP_MOVED =>
                'Stop Loss moved to '.$price.'.',

            TradeIdeaUpdate::TYPE_TARGET_HIT =>
                ($target?->label ?? 'Target')
                .' hit at '
                .$price
                .'.',

            default =>
                'Trade Idea updated.',
        };
    }

    /**
     * @return array{
     *   disk:string,
     *   path:string,
     *   mime_type:string,
     *   size_bytes:int
     * }|null
     */
    private function storeChartImage(
        ?UploadedFile $image,
        string $tradeIdeaUuid
    ): ?array {
        if ($image === null) {
            return null;
        }

        $mimeType = strtolower(
            (string) $image->getMimeType()
        );

        $extension = match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => null,
        };

        abort_if(
            $extension === null,
            422,
            'Unsupported chart image type.'
        );

        $path = 'trade-ideas/'
            .$tradeIdeaUuid
            .'/charts/'
            .Str::uuid()->toString()
            .'.'
            .$extension;

        $stream = fopen(
            $image->getRealPath(),
            'rb'
        );

        abort_if(
            $stream === false,
            422,
            'Unable to read chart image.'
        );

        try {
            $stored = Storage::disk('private')
                ->put(
                    $path,
                    $stream
                );
        } finally {
            fclose(
                $stream
            );
        }

        abort_unless(
            $stored,
            500,
            'Unable to store chart image.'
        );

        return [
            'disk' => 'private',
            'path' => $path,
            'mime_type' => $mimeType,
            'size_bytes' => (int) $image->getSize(),
        ];
    }

    private function deleteChartImage(
        ?string $disk,
        ?string $path
    ): void {
        if (
            $disk !== 'private'
            || $path === null
            || trim(
                $path
            ) === ''
        ) {
            return;
        }

        Storage::disk('private')
            ->delete(
                $path
            );
    }

    private function queueAutomaticDistribution(
        string $eventType,
        TradeIdea $tradeIdea,
        AdminUser $admin,
        ?TradeIdeaUpdate $update,
        Request $request
    ): void {
        try {
            $manager = app(
                TradeIdeaDistributionManager::class
            );

            match ($eventType) {
                TradeIdeaDistribution::EVENT_INITIAL =>
                    $manager->queueInitial(
                        $tradeIdea,
                        $admin
                    ),

                TradeIdeaDistribution::EVENT_UPDATE =>
                    $manager->queueUpdate(
                        $tradeIdea,
                        $update
                            ?? throw new \LogicException(
                                'Timeline update is required.'
                            ),
                        $admin
                    ),

                TradeIdeaDistribution::EVENT_CLOSED =>
                    $manager->queueClosed(
                        $tradeIdea,
                        $update,
                        $admin
                    ),

                default =>
                    null,
            };
        } catch (\Throwable $exception) {
            /*
            | Distribution is a secondary external concern. Never roll back or
            | convert an already-committed Publish / Update / Close into a 500
            | merely because a provider queue could not be prepared.
            |
            | Do not persist raw exception text because provider configuration
            | or transport details may be sensitive.
            */
            AuditLog::record(
                actorType: 'ADMIN',
                actorId: $admin->id,
                action: 'TRADE_IDEA_AUTO_DISTRIBUTION_QUEUE_FAILED',
                module: 'TRADE_IDEAS',
                targetType: 'TRADE_IDEA',
                targetId: $tradeIdea->id,
                newValues: [
                    'event_type' =>
                        $eventType,

                    'error_code' =>
                        'QUEUE_PREPARATION_FAILED',
                ],
                ipAddress: $request->ip(),
                userAgent: $request->userAgent()
            );
        }
    }

    private function authenticatedAdmin(): AdminUser
    {
        $admin = auth(
            'admin'
        )->user();

        abort_unless(
            $admin instanceof AdminUser,
            401
        );

        return $admin;
    }

    private function requirePermission(
        AdminUser $admin,
        string $permission
    ): void {
        abort_unless(
            $admin->hasPermission(
                $permission
            ),
            403
        );
    }

    private function permissions(
        AdminUser $admin
    ): array {
        return [
            'view' => $admin->hasPermission(
                'trade_ideas.view'
            ),

            'create' => $admin->hasPermission(
                'trade_ideas.create'
            ),

            'update' => $admin->hasPermission(
                'trade_ideas.update'
            ),

            'delete' => $admin->hasPermission(
                'trade_ideas.delete'
            ),

            'publish' => $admin->hasPermission(
                'trade_ideas.publish'
            ),

            'close' => $admin->hasPermission(
                'trade_ideas.close'
            ),

            'distribute' => $admin->hasPermission(
                'trade_ideas.distribute'
            ),
        ];
    }

    private function auditSnapshot(
        TradeIdea $tradeIdea
    ): array {
        if (! $tradeIdea->relationLoaded('targets')) {
            $tradeIdea->load(
                'targets'
            );
        }

        return [
            'uuid' => $tradeIdea->uuid,
            'title' => $tradeIdea->title,
            'summary' => $tradeIdea->summary,

            'analysis_sha256' => $tradeIdea->analysis !== null
                ? hash(
                    'sha256',
                    $tradeIdea->analysis
                )
                : null,

            'analysis_length' => $tradeIdea->analysis !== null
                ? mb_strlen(
                    $tradeIdea->analysis
                )
                : 0,

            'tradingview_url' => $tradeIdea->tradingview_url,

            'chart_image_disk' => $tradeIdea->chart_image_disk,

            'chart_image_path_sha256' => $tradeIdea->chart_image_path !== null
                ? hash(
                    'sha256',
                    $tradeIdea->chart_image_path
                )
                : null,

            'chart_image_mime_type' => $tradeIdea->chart_image_mime_type,
            'chart_image_size_bytes' => $tradeIdea->chart_image_size_bytes,

            'category' => $tradeIdea->category,
            'market_region' => $tradeIdea->market_region,
            'symbol' => $tradeIdea->symbol,
            'instrument_name' => $tradeIdea->instrument_name,
            'direction' => $tradeIdea->direction,
            'timeframe' => $tradeIdea->timeframe,

            'entry_price_low' => $tradeIdea->entry_price_low,
            'entry_price_high' => $tradeIdea->entry_price_high,
            'stop_loss' => $tradeIdea->stop_loss,
            'current_stop_loss' => $tradeIdea->current_stop_loss,
            'entry_triggered_at' => $tradeIdea->entry_triggered_at?->toISOString(),
            'entry_price_actual' => $tradeIdea->entry_price_actual,

            'status' => $tradeIdea->status,
            'published_at' => $tradeIdea->published_at?->toISOString(),
            'closed_at' => $tradeIdea->closed_at?->toISOString(),
            'close_outcome' => $tradeIdea->close_outcome,
            'close_price' => $tradeIdea->close_price,

            'close_note_sha256' => $tradeIdea->close_note !== null
                ? hash(
                    'sha256',
                    $tradeIdea->close_note
                )
                : null,

            'targets' => $tradeIdea->targets
                ->map(
                    fn (TradeIdeaTarget $target): array => [
                        'label' => $target->label,
                        'price' => $target->price,
                        'sort_order' => $target->sort_order,
                        'hit_at' => $target->hit_at?->toISOString(),
                        'hit_price' => $target->hit_price,
                    ]
                )
                ->values()
                ->all(),

            'created_by_admin_id' => $tradeIdea->created_by_admin_id,
            'updated_by_admin_id' => $tradeIdea->updated_by_admin_id,
        ];
    }

    private function auditChanges(
        array $before,
        array $after
    ): array {
        $oldValues = [];
        $newValues = [];

        foreach (
            array_unique(
                array_merge(
                    array_keys(
                        $before
                    ),
                    array_keys(
                        $after
                    )
                )
            )
            as $key
        ) {
            $old = $before[$key] ?? null;
            $new = $after[$key] ?? null;

            if ($old === $new) {
                continue;
            }

            $oldValues[$key] = $old;
            $newValues[$key] = $new;
        }

        return [
            $oldValues,
            $newValues,
        ];
    }
}
