{{-- TradeX Admin — ADMIN-05B Responsive Trade Idea Share Card --}}

@extends('admin.layouts.app')

@section('title', $tradeIdea->symbol . ' Share Card | TradeX')
@section('page-title', 'Trade Ideas')

@php
    $query = [
        'variant' => $variant,
    ];

    if ($referralCode) {
        $query['referral_code'] = $referralCode;
    }

    if ($affiliateUrl) {
        $query['affiliate_url'] = $affiliateUrl;
    }

    $svgUrl = route(
        'admin.trade_ideas.share_card.svg',
        [
            'tradeIdea' => $tradeIdea,
        ]
    ) . '?' . http_build_query($query);

    $ratio = $dimensions['width'] . ' / ' . $dimensions['height'];
@endphp

@push('styles')
<style>
    .share-card-layout {
        display: grid;
        grid-template-columns: minmax(0, 1.25fr) minmax(300px, .75fr);
        gap: 22px;
        align-items: start;
    }

    .share-card-stage {
        padding: 18px;
        min-width: 0;
    }

    .share-card-frame {
        width: min(100%, 620px);
        margin: 0 auto;
        aspect-ratio: {{ $ratio }};
        border-radius: 18px;
        overflow: hidden;
        background: #050607;
        border: 1px solid rgba(255, 255, 255, .08);
        box-shadow: 0 28px 70px rgba(0, 0, 0, .32);
    }

    .share-card-frame img {
        display: block;
        width: 100%;
        height: 100%;
        object-fit: contain;
        background: #050607;
    }

    .share-card-side {
        display: grid;
        gap: 16px;
        position: sticky;
        top: 88px;
    }

    .share-card-panel {
        padding: 20px;
    }

    .share-card-panel h3 {
        margin: 5px 0 7px;
        font-size: 15px;
    }

    .share-card-panel p {
        margin: 0 0 15px;
        font-size: 11px;
        line-height: 1.65;
        opacity: .58;
    }

    .share-card-field {
        margin-bottom: 13px;
    }

    .share-card-field label {
        display: block;
        margin-bottom: 6px;
        font-size: 9px;
        font-weight: 800;
        letter-spacing: .06em;
        opacity: .62;
    }

    .share-card-field input,
    .share-card-field select {
        width: 100%;
        box-sizing: border-box;
        padding: 10px 11px;
        border: 1px solid rgba(255, 255, 255, .09);
        border-radius: 10px;
        background: rgba(255, 255, 255, .025);
        color: inherit;
        font: inherit;
        font-size: 11px;
        outline: none;
    }

    .share-card-field input:focus,
    .share-card-field select:focus {
        border-color: rgba(218, 178, 88, .55);
        box-shadow: 0 0 0 3px rgba(218, 178, 88, .07);
    }

    .share-card-actions {
        display: grid;
        gap: 9px;
    }

    .share-card-action {
        width: 100%;
        box-sizing: border-box;
        padding: 10px 12px;
        border-radius: 10px;
        font: inherit;
        font-size: 10px;
        font-weight: 800;
        cursor: pointer;
        text-align: center;
        text-decoration: none;
    }

    .share-card-primary {
        border: 1px solid rgba(218, 178, 88, .25);
        background: rgba(218, 178, 88, .09);
        color: #e5c16e;
    }

    .share-card-secondary {
        border: 1px solid rgba(255, 255, 255, .09);
        background: rgba(255, 255, 255, .035);
        color: inherit;
    }

    .share-card-note {
        padding: 12px;
        border: 1px solid rgba(218, 178, 88, .14);
        border-radius: 10px;
        background: rgba(218, 178, 88, .035);
        font-size: 9px;
        line-height: 1.6;
        opacity: .72;
    }

    .share-card-status {
        display: none;
        margin-top: 10px;
        font-size: 10px;
        line-height: 1.5;
        opacity: .68;
    }

    .share-card-status.visible {
        display: block;
    }

    @media (max-width: 980px) {
        .share-card-layout {
            grid-template-columns: 1fr;
        }

        .share-card-side {
            position: static;
        }
    }

    @media (max-width: 580px) {
        .share-card-stage {
            padding: 10px;
        }

        .share-card-frame {
            border-radius: 12px;
        }

        .share-card-panel {
            padding: 16px;
        }
    }
</style>
@endpush

@section('content')

<div class="page-header">
    <div>
        <div class="page-eyebrow">
            ADMIN-05B · SHARE CARD
        </div>

        <h1>
            {{ $tradeIdea->symbol }}
            <span>
                Share Card
            </span>
        </h1>

        <p>
            Responsive preview using the original Trade Idea chart image without distortion.
        </p>
    </div>

    <div>
        <a
            href="{{ route(
                'admin.trade_ideas.show',
                [
                    'tradeIdea' => $tradeIdea,
                ]
            ) }}"
            class="secondary-btn"
        >
            ← Back to Trade Idea
        </a>
    </div>
</div>

<div class="share-card-layout">

    <section class="panel share-card-stage">
        <div
            class="share-card-frame"
            id="share-card-frame"
        >
            <img
                id="share-card-image"
                src="{{ $svgUrl }}"
                alt="{{ $tradeIdea->symbol }} Trade Idea Share Card"
            >
        </div>
    </section>

    <aside class="share-card-side">

        <section class="panel share-card-panel">
            <div class="panel-eyebrow">
                PREVIEW SETTINGS
            </div>

            <h3>
                Card Format
            </h3>

            <p>
                Admin preview values are for testing only. Production user sharing must inject the authenticated user's referral code automatically.
            </p>

            <form
                method="GET"
                action="{{ route(
                    'admin.trade_ideas.share_card.preview',
                    [
                        'tradeIdea' => $tradeIdea,
                    ]
                ) }}"
            >
                <div class="share-card-field">
                    <label for="variant">
                        FORMAT
                    </label>

                    <select
                        id="variant"
                        name="variant"
                        required
                    >
                        @foreach($variants as $option)
                            <option
                                value="{{ $option }}"
                                {{ $variant === $option ? 'selected' : '' }}
                            >
                                @if($option === 'square')
                                    Square · 1080 × 1080
                                @elseif($option === 'story')
                                    Story · 1080 × 1920
                                @else
                                    Portrait · 1080 × 1350
                                @endif
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="share-card-field">
                    <label for="referral_code">
                        DEMO REFERRAL CODE · OPTIONAL
                    </label>

                    <input
                        id="referral_code"
                        name="referral_code"
                        maxlength="40"
                        value="{{ old(
                            'referral_code',
                            $referralCode
                        ) }}"
                        placeholder="TX-ABC123"
                    >
                </div>

                <div class="share-card-field">
                    <label for="affiliate_url">
                        DEMO AFFILIATE URL · OPTIONAL
                    </label>

                    <input
                        id="affiliate_url"
                        name="affiliate_url"
                        maxlength="500"
                        value="{{ old(
                            'affiliate_url',
                            $affiliateUrl
                        ) }}"
                        placeholder="https://example.com/join?ref=TX-ABC123"
                    >
                </div>

                <button
                    type="submit"
                    class="share-card-action share-card-primary"
                >
                    Refresh Preview
                </button>
            </form>
        </section>

        <section class="panel share-card-panel">
            <div class="panel-eyebrow">
                EXPORT
            </div>

            <h3>
                PNG / Share
            </h3>

            <p>
                SVG is converted locally in the browser to a PNG at the exact selected resolution.
            </p>

            <div
                class="share-card-actions"
                id="share-card-actions"
                data-svg-url="{{ $svgUrl }}"
                data-width="{{ $dimensions['width'] }}"
                data-height="{{ $dimensions['height'] }}"
                data-filename="tradex-{{ strtolower($tradeIdea->symbol) }}-{{ $variant }}.png"
            >
                <button
                    type="button"
                    id="download-png"
                    class="share-card-action share-card-primary"
                >
                    Download PNG
                </button>

                <button
                    type="button"
                    id="share-png"
                    class="share-card-action share-card-secondary"
                >
                    Share PNG
                </button>

                <a
                    href="{{ $svgUrl }}"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="share-card-action share-card-secondary"
                >
                    Open SVG
                </a>
            </div>

            <div
                id="share-card-status"
                class="share-card-status"
            ></div>
        </section>

        <div class="share-card-note">
            Uploaded chart/image uses <strong>preserveAspectRatio="meet"</strong>. It is never stretched or distorted; differing image ratios are safely letterboxed inside the TradeX card.
        </div>

    </aside>

</div>

@endsection

@push('scripts')
<script>
(() => {
    'use strict';

    const actions =
        document.getElementById(
            'share-card-actions'
        );

    if (!actions) {
        return;
    }

    const svgUrl =
        actions.dataset.svgUrl;

    const width =
        Number(
            actions.dataset.width
        );

    const height =
        Number(
            actions.dataset.height
        );

    const filename =
        actions.dataset.filename
        || 'tradex-trade-idea.png';

    const downloadButton =
        document.getElementById(
            'download-png'
        );

    const shareButton =
        document.getElementById(
            'share-png'
        );

    const status =
        document.getElementById(
            'share-card-status'
        );

    const shareTitle =
        @json($tradeIdea->symbol . ' Trade Idea');

    const showStatus = (message) => {
        if (!status) {
            return;
        }

        status.textContent =
            message;

        status.classList.add(
            'visible'
        );
    };

    const toPngBlob = async () => {
        const response =
            await fetch(
                svgUrl,
                {
                    credentials: 'same-origin',
                    cache: 'no-store',
                }
            );

        if (!response.ok) {
            throw new Error(
                'Unable to load Share Card SVG.'
            );
        }

        const svgText =
            await response.text();

        const svgBlob =
            new Blob(
                [
                    svgText,
                ],
                {
                    type: 'image/svg+xml;charset=utf-8',
                }
            );

        const objectUrl =
            URL.createObjectURL(
                svgBlob
            );

        try {
            const image =
                new Image();

            await new Promise(
                (resolve, reject) => {
                    image.onload =
                        resolve;

                    image.onerror =
                        () => reject(
                            new Error(
                                'Unable to render Share Card.'
                            )
                        );

                    image.src =
                        objectUrl;
                }
            );

            const canvas =
                document.createElement(
                    'canvas'
                );

            canvas.width =
                width;

            canvas.height =
                height;

            const context =
                canvas.getContext(
                    '2d'
                );

            if (!context) {
                throw new Error(
                    'Canvas is not available in this browser.'
                );
            }

            context.drawImage(
                image,
                0,
                0,
                width,
                height
            );

            return await new Promise(
                (resolve, reject) => {
                    canvas.toBlob(
                        (blob) => {
                            if (!blob) {
                                reject(
                                    new Error(
                                        'PNG conversion failed.'
                                    )
                                );

                                return;
                            }

                            resolve(
                                blob
                            );
                        },
                        'image/png',
                        0.96
                    );
                }
            );
        } finally {
            URL.revokeObjectURL(
                objectUrl
            );
        }
    };

    downloadButton?.addEventListener(
        'click',
        async () => {
            downloadButton.disabled =
                true;

            showStatus(
                'Generating PNG…'
            );

            try {
                const blob =
                    await toPngBlob();

                const url =
                    URL.createObjectURL(
                        blob
                    );

                const anchor =
                    document.createElement(
                        'a'
                    );

                anchor.href =
                    url;

                anchor.download =
                    filename;

                document.body.appendChild(
                    anchor
                );

                anchor.click();
                anchor.remove();

                setTimeout(
                    () => URL.revokeObjectURL(
                        url
                    ),
                    1000
                );

                showStatus(
                    'PNG generated successfully.'
                );
            } catch (error) {
                showStatus(
                    error instanceof Error
                        ? error.message
                        : 'PNG generation failed.'
                );
            } finally {
                downloadButton.disabled =
                    false;
            }
        }
    );

    shareButton?.addEventListener(
        'click',
        async () => {
            shareButton.disabled =
                true;

            showStatus(
                'Preparing Share Card…'
            );

            try {
                const blob =
                    await toPngBlob();

                const file =
                    new File(
                        [
                            blob,
                        ],
                        filename,
                        {
                            type: 'image/png',
                        }
                    );

                if (
                    navigator.share
                    && navigator.canShare
                    && navigator.canShare(
                        {
                            files: [
                                file,
                            ],
                        }
                    )
                ) {
                    await navigator.share(
                        {
                            title: shareTitle,
                            text: 'TradeX OS Trade Idea',
                            files: [
                                file,
                            ],
                        }
                    );

                    showStatus(
                        'Share sheet opened.'
                    );

                    return;
                }

                showStatus(
                    'Direct file sharing is not supported by this browser. Use Download PNG instead.'
                );
            } catch (error) {
                if (
                    error instanceof DOMException
                    && error.name === 'AbortError'
                ) {
                    showStatus(
                        'Share cancelled.'
                    );

                    return;
                }

                showStatus(
                    error instanceof Error
                        ? error.message
                        : 'Share preparation failed.'
                );
            } finally {
                shareButton.disabled =
                    false;
            }
        }
    );
})();
</script>
@endpush
