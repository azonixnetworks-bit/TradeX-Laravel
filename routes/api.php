<?php

use App\Http\Controllers\Api\Auth\ConfirmRegistrationTotpController;
use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\Auth\LogoutAllController;
use App\Http\Controllers\Api\Auth\LogoutController;
use App\Http\Controllers\Api\Auth\MeController;
use App\Http\Controllers\Api\Auth\RefreshController;
use App\Http\Controllers\Api\Auth\RegistrationStatusController;
use App\Http\Controllers\Api\Auth\ResendRegistrationEmailOtpController;
use App\Http\Controllers\Api\Auth\ResendRegistrationWhatsAppOtpController;
use App\Http\Controllers\Api\Auth\RevokeCurrentDeviceController;
use App\Http\Controllers\Api\Auth\SetupRegistrationTotpController;
use App\Http\Controllers\Api\Auth\StartRegistrationController;
use App\Http\Controllers\Api\Auth\VerifyRegistrationEmailController;
use App\Http\Controllers\Api\Auth\VerifyRegistrationWhatsAppController;
use App\Http\Controllers\Api\Education\AssignmentIndexController;
use App\Http\Controllers\Api\Education\AssignmentMaterialController;
use App\Http\Controllers\Api\Education\AssignmentShowController;
use App\Http\Controllers\Api\Education\AssignmentWhatsAppActionController;
use App\Http\Controllers\Api\Education\CourseIndexController;
use App\Http\Controllers\Api\Education\CourseShowController;
use App\Http\Controllers\Api\Education\IssueVideoPlaybackTokenController;
use App\Http\Controllers\Api\Education\LessonShowController;
use App\Http\Controllers\Api\Education\ModuleShowController;
use App\Http\Controllers\Api\Education\PlaybackAssetController;
use App\Http\Controllers\Api\Education\UpdateLessonProgressController;
use App\Http\Controllers\Api\Profile\ConfirmTelegramChangeController;
use App\Http\Controllers\Api\Profile\ConfirmWhatsAppChangeController;
use App\Http\Controllers\Api\Profile\ProfileController;
use App\Http\Controllers\Api\Profile\RequestTelegramChangeController;
use App\Http\Controllers\Api\Profile\RequestWhatsAppChangeController;
use App\Http\Controllers\Api\Profile\UpdateFinancialProfileController;
use App\Http\Controllers\Api\Profile\UpdateProfilePreferencesController;
use App\Http\Controllers\Api\TradeIdeas\TradeIdeaChartController;
use App\Http\Controllers\Api\TradeIdeas\TradeIdeaIndexController;
use App\Http\Controllers\Api\TradeIdeas\TradeIdeaShareActionController;
use App\Http\Controllers\Api\TradeIdeas\TradeIdeaShowController;
use App\Http\Controllers\Api\TradeIdeas\UserTradeIdeaShareCardController;
use App\Http\Controllers\Api\TradeIdeas\UserTradeIdeaUuidShareCardController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->name('api.v1.')
    ->group(function (): void {
        Route::prefix('auth')
            ->name('auth.')
            ->group(function (): void {
                Route::post(
                    '/login',
                    LoginController::class
                )
                    ->middleware(
                        'throttle:api-login'
                    )
                    ->name('login');

                Route::post(
                    '/refresh',
                    RefreshController::class
                )
                    ->middleware(
                        'throttle:api-refresh'
                    )
                    ->name('refresh');

                Route::prefix('register')
                    ->name('register.')
                    ->group(function (): void {
                        Route::post(
                            '/start',
                            StartRegistrationController::class
                        )
                            ->middleware(
                                'throttle:api-register-start'
                            )
                            ->name('start');

                        Route::post(
                            '/status',
                            RegistrationStatusController::class
                        )
                            ->middleware(
                                'throttle:api-register-verify'
                            )
                            ->name('status');

                        Route::post(
                            '/email/resend',
                            ResendRegistrationEmailOtpController::class
                        )
                            ->middleware(
                                'throttle:api-register-otp-send'
                            )
                            ->name('email.resend');

                        Route::post(
                            '/email/verify',
                            VerifyRegistrationEmailController::class
                        )
                            ->middleware(
                                'throttle:api-register-verify'
                            )
                            ->name('email.verify');

                        Route::post(
                            '/whatsapp/resend',
                            ResendRegistrationWhatsAppOtpController::class
                        )
                            ->middleware(
                                'throttle:api-register-otp-send'
                            )
                            ->name('whatsapp.resend');

                        Route::post(
                            '/whatsapp/verify',
                            VerifyRegistrationWhatsAppController::class
                        )
                            ->middleware(
                                'throttle:api-register-verify'
                            )
                            ->name('whatsapp.verify');

                        Route::post(
                            '/totp/setup',
                            SetupRegistrationTotpController::class
                        )
                            ->middleware(
                                'throttle:api-register-totp'
                            )
                            ->name('totp.setup');

                        Route::post(
                            '/totp/confirm',
                            ConfirmRegistrationTotpController::class
                        )
                            ->middleware(
                                'throttle:api-register-totp'
                            )
                            ->name('totp.confirm');
                    });
            });

        Route::get(
            '/education/playback/{token}/{path?}',
            PlaybackAssetController::class
        )
            ->where(
                'token',
                '[A-Fa-f0-9]{64}'
            )
            ->where(
                'path',
                '.*'
            )
            ->middleware(
                'throttle:api-education-playback'
            )
            ->name(
                'education.playback.asset'
            );

        Route::middleware([
            'auth:sanctum',
            'tradex.mobile',
        ])->group(function (): void {
            Route::prefix('auth')
                ->name('auth.')
                ->group(function (): void {
                    Route::get(
                        '/me',
                        MeController::class
                    )->name('me');

                    Route::post(
                        '/logout',
                        LogoutController::class
                    )->name('logout');

                    Route::post(
                        '/logout-all',
                        LogoutAllController::class
                    )->name('logout-all');

                    Route::post(
                        '/device/revoke',
                        RevokeCurrentDeviceController::class
                    )->name(
                        'device.revoke'
                    );
                });

            Route::prefix('profile')
                ->name('profile.')
                ->group(function (): void {
                    Route::get(
                        '/',
                        ProfileController::class
                    )
                        ->middleware(
                            'throttle:api-profile-read'
                        )
                        ->name('show');

                    Route::patch(
                        '/preferences',
                        UpdateProfilePreferencesController::class
                    )
                        ->middleware(
                            'throttle:api-profile-write'
                        )
                        ->name(
                            'preferences.update'
                        );

                    Route::patch(
                        '/financial',
                        UpdateFinancialProfileController::class
                    )
                        ->middleware(
                            'throttle:api-profile-sensitive'
                        )
                        ->name(
                            'financial.update'
                        );

                    Route::post(
                        '/whatsapp/change/request',
                        RequestWhatsAppChangeController::class
                    )
                        ->middleware(
                            'throttle:api-profile-sensitive'
                        )
                        ->name(
                            'whatsapp.change.request'
                        );

                    Route::post(
                        '/whatsapp/change/confirm',
                        ConfirmWhatsAppChangeController::class
                    )
                        ->middleware(
                            'throttle:api-profile-contact'
                        )
                        ->name(
                            'whatsapp.change.confirm'
                        );

                    Route::post(
                        '/telegram/change/request',
                        RequestTelegramChangeController::class
                    )
                        ->middleware(
                            'throttle:api-profile-sensitive'
                        )
                        ->name(
                            'telegram.change.request'
                        );

                    Route::post(
                        '/telegram/change/confirm',
                        ConfirmTelegramChangeController::class
                    )
                        ->middleware(
                            'throttle:api-profile-contact'
                        )
                        ->name(
                            'telegram.change.confirm'
                        );
                });

            Route::prefix('education')
                ->name('education.')
                ->group(function (): void {
                    Route::get(
                        '/courses',
                        CourseIndexController::class
                    )
                        ->middleware(
                            'throttle:api-education-read'
                        )
                        ->name('courses.index');

                    Route::get(
                        '/courses/{course}',
                        CourseShowController::class
                    )
                        ->whereUuid('course')
                        ->middleware(
                            'throttle:api-education-read'
                        )
                        ->name('courses.show');

                    Route::get(
                        '/modules/{module}',
                        ModuleShowController::class
                    )
                        ->whereUuid('module')
                        ->middleware(
                            'throttle:api-education-read'
                        )
                        ->name('modules.show');

                    Route::get(
                        '/lessons/{lesson}',
                        LessonShowController::class
                    )
                        ->whereUuid('lesson')
                        ->middleware(
                            'throttle:api-education-read'
                        )
                        ->name('lessons.show');

                    Route::get(
                        '/lessons/{lesson}/assignments',
                        AssignmentIndexController::class
                    )
                        ->whereUuid('lesson')
                        ->middleware(
                            'throttle:api-education-read'
                        )
                        ->name('lessons.assignments.index');

                    Route::patch(
                        '/lessons/{lesson}/progress',
                        UpdateLessonProgressController::class
                    )
                        ->whereUuid('lesson')
                        ->middleware(
                            'throttle:api-education-progress'
                        )
                        ->name('lessons.progress.update');

                    Route::get(
                        '/assignments/{assignment}',
                        AssignmentShowController::class
                    )
                        ->whereUuid('assignment')
                        ->middleware(
                            'throttle:api-education-read'
                        )
                        ->name('assignments.show');

                    Route::post(
                        '/assignments/{assignment}/whatsapp-action',
                        AssignmentWhatsAppActionController::class
                    )
                        ->whereUuid('assignment')
                        ->middleware(
                            'throttle:api-education-assignment-action'
                        )
                        ->name('assignments.whatsapp-action');

                    Route::get(
                        '/assignments/{assignment}/material',
                        AssignmentMaterialController::class
                    )
                        ->whereUuid('assignment')
                        ->middleware(
                            'throttle:api-education-assignment-material'
                        )
                        ->name('assignments.material');

                    Route::post(
                        '/videos/{video}/playback-token',
                        IssueVideoPlaybackTokenController::class
                    )
                        ->whereUuid('video')
                        ->middleware(
                            'throttle:api-education-token'
                        )
                        ->name('videos.playback-token');
                });

            Route::prefix('trade-ideas')
                ->name('trade-ideas.')
                ->group(function (): void {
                    Route::get(
                        '/',
                        TradeIdeaIndexController::class
                    )
                        ->middleware(
                            'throttle:api-trade-ideas-read'
                        )
                        ->name('index');

                    Route::get(
                        '/{tradeIdea}',
                        TradeIdeaShowController::class
                    )
                        ->whereUuid('tradeIdea')
                        ->middleware(
                            'throttle:api-trade-ideas-read'
                        )
                        ->name('show');

                    Route::get(
                        '/{tradeIdea}/chart',
                        TradeIdeaChartController::class
                    )
                        ->whereUuid('tradeIdea')
                        ->middleware(
                            'throttle:api-trade-ideas-media'
                        )
                        ->name('chart');

                    Route::get(
                        '/{tradeIdea}/share-card.svg',
                        UserTradeIdeaUuidShareCardController::class
                    )
                        ->whereUuid('tradeIdea')
                        ->middleware(
                            'throttle:api-trade-ideas-share'
                        )
                        ->name('share-card.uuid');

                    Route::post(
                        '/{tradeIdea}/share-action',
                        TradeIdeaShareActionController::class
                    )
                        ->whereUuid('tradeIdea')
                        ->middleware(
                            'throttle:api-trade-ideas-share'
                        )
                        ->name('share-action');
                });

            /*
            | ADMIN-05B compatibility route.
            | Kept unchanged for existing clients while API-06 uses UUIDs.
            */
            Route::get(
                '/trade-ideas/{tradeIdea}/share-card',
                [
                    UserTradeIdeaShareCardController::class,
                    'svg',
                ]
            )
                ->whereNumber(
                    'tradeIdea'
                )
                ->middleware(
                    'throttle:api-share-card'
                )
                ->name(
                    'trade-ideas.share-card'
                );
        });
    });
