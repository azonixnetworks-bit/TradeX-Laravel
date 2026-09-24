{{-- TradeX Admin — Our Channel Multi-Platform Publisher --}}

@extends('admin.layouts.app')

@section('title', 'Upload Channel Video | TradeX')
@section('page-title', 'Our Channel')

@push('styles')
<style>
    .channel-form{max-width:1080px}.form-section{padding:22px;border-bottom:1px solid rgba(255,255,255,.06)}.form-section:last-child{border-bottom:0}
    .form-section h2{margin:0 0 6px;font-size:16px}.form-section>p{margin:0 0 18px;font-size:11px;opacity:.58;line-height:1.65}
    .form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:15px}.form-field.full{grid-column:1/-1}.form-field label{display:block;margin-bottom:7px;font-size:10px;font-weight:800;letter-spacing:.04em}
    .form-field input,.form-field textarea,.form-field select{width:100%;box-sizing:border-box;padding:11px 12px;border:1px solid rgba(255,255,255,.08);border-radius:10px;background:rgba(255,255,255,.025);color:inherit;font:inherit;font-size:12px}.form-field textarea{min-height:115px;resize:vertical}
    .hint{margin-top:6px;font-size:9px;opacity:.5;line-height:1.55}.field-error{margin-top:6px;color:#ff8d8d;font-size:10px}.check-row{display:flex;align-items:center;gap:8px;padding-top:8px}.check-row input{width:auto}
    .platform-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}.platform-card{position:relative;display:block;padding:14px;border:1px solid rgba(255,255,255,.08);border-radius:12px;background:rgba(255,255,255,.022);cursor:pointer}.platform-card input{position:absolute;right:12px;top:12px;width:auto}.platform-card strong{display:block;padding-right:28px;font-size:12px}.platform-card p{margin:8px 0 0;font-size:9px;line-height:1.5;opacity:.53}.platform-badge{display:inline-block;margin-top:9px;padding:4px 7px;border:1px solid rgba(255,255,255,.09);border-radius:999px;font-size:8px;font-weight:900;letter-spacing:.04em}.platform-badge.ready{color:#7ee2a8;border-color:rgba(126,226,168,.25)}.platform-badge.warn{color:#e6be68;border-color:rgba(230,190,104,.25)}.platform-badge.manual{color:#aab3ff;border-color:rgba(170,179,255,.22)}
    .notice{padding:12px 14px;border:1px solid rgba(218,178,88,.17);border-radius:10px;background:rgba(218,178,88,.045);font-size:10px;line-height:1.6;color:#dbc27f}
    .form-actions{display:flex;justify-content:flex-end;gap:10px;padding:20px 22px}.draft-btn{border:1px solid rgba(255,255,255,.1);background:rgba(255,255,255,.035);color:inherit;border-radius:10px;padding:10px 14px;font:inherit;font-size:11px;font-weight:800;cursor:pointer}
    @media(max-width:860px){.platform-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:720px){.form-grid,.platform-grid{grid-template-columns:1fr}.form-field.full{grid-column:auto}.form-actions{flex-wrap:wrap}}
</style>
@endpush

@section('content')
<div class="page-header">
    <div>
        <div class="page-eyebrow">OUR CHANNEL · MULTI-PLATFORM PUBLISHER</div>
        <h1>Upload <span>Once</span></h1>
        <p>Store one protected master video and publish it through approved TradeX platform integrations.</p>
    </div>

    <div style="display:flex;gap:10px;flex-wrap:wrap">
        <a class="secondary-btn" href="{{ route('admin.our_channel.connections.index') }}">Channel Connections</a>
        <a class="secondary-btn" href="{{ route('admin.our_channel.index') }}">← Back</a>
    </div>
</div>

<section class="panel channel-form">
    <form method="POST" enctype="multipart/form-data" action="{{ route('admin.our_channel.store') }}">
        @csrf

        <div class="form-section">
            <h2>Master Video</h2>
            <p>MP4 is recommended for the widest cross-platform compatibility. The source stays on protected TradeX storage.</p>

            <div class="form-grid">
                <div class="form-field full">
                    <label for="title">TITLE</label>
                    <input id="title" name="title" value="{{ old('title') }}" maxlength="180" required>
                    @error('title')<div class="field-error">{{ $message }}</div>@enderror
                </div>

                <div class="form-field full">
                    <label for="video_file">MASTER VIDEO FILE</label>
                    <input id="video_file" type="file" name="video_file" accept="video/mp4,video/quicktime">
                    <div class="hint">Required for cross-platform publishing. MP4/MOV only. Production PHP/server upload limits must also allow the selected file size.</div>
                    @error('video_file')<div class="field-error">{{ $message }}</div>@enderror
                </div>

                <div class="form-field full">
                    <label for="youtube_url">EXISTING YOUTUBE URL · OPTIONAL</label>
                    <input id="youtube_url" name="youtube_url" value="{{ old('youtube_url') }}" placeholder="https://www.youtube.com/watch?v=..." maxlength="2048">
                    <div class="hint">Use this only when the video is already on YouTube. New YouTube uploads will save the provider URL after successful publishing.</div>
                    @error('youtube_url')<div class="field-error">{{ $message }}</div>@enderror
                </div>

                <div class="form-field">
                    <label for="language_code">LANGUAGE CODE</label>
                    <input id="language_code" name="language_code" value="{{ old('language_code', 'en') }}" maxlength="10" required>
                    @error('language_code')<div class="field-error">{{ $message }}</div>@enderror
                </div>

                <div class="form-field">
                    <label for="sort_order">SORT ORDER</label>
                    <input id="sort_order" type="number" min="0" max="1000000" name="sort_order" value="{{ old('sort_order', 0) }}">
                    @error('sort_order')<div class="field-error">{{ $message }}</div>@enderror
                </div>

                <div class="form-field full">
                    <label for="short_description">SHORT DESCRIPTION</label>
                    <textarea id="short_description" name="short_description" maxlength="500">{{ old('short_description') }}</textarea>
                    @error('short_description')<div class="field-error">{{ $message }}</div>@enderror
                </div>

                <div class="form-field full">
                    <label for="description">DESCRIPTION / CAPTION</label>
                    <textarea id="description" name="description" maxlength="20000">{{ old('description') }}</textarea>
                    @error('description')<div class="field-error">{{ $message }}</div>@enderror
                </div>

                <div class="form-field full">
                    <label for="hashtags">HASHTAGS</label>
                    <textarea id="hashtags" name="hashtags" maxlength="2000" placeholder="#TradeX #Trading #Education">{{ old('hashtags') }}</textarea>
                    <div class="hint">Each provider applies its own caption/tag limits; TradeX safely truncates where required.</div>
                    @error('hashtags')<div class="field-error">{{ $message }}</div>@enderror
                </div>

                <div class="form-field full">
                    <label class="check-row">
                        <input type="hidden" name="is_featured" value="0">
                        <input type="checkbox" name="is_featured" value="1" {{ old('is_featured') ? 'checked' : '' }}>
                        <span>Feature this video in learner discovery</span>
                    </label>
                    @error('is_featured')<div class="field-error">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>

        <div class="form-section">
            <h2>Publish Destinations</h2>
            <p>Select the destinations that should be tracked for this video. Enabled API providers are queued automatically.</p>

            @php
                $selectedPlatforms = old('platforms', array_keys($platforms));
                $selectedPlatforms = is_array($selectedPlatforms)
                    ? $selectedPlatforms
                    : [];
            @endphp

            <div class="platform-grid">
                @foreach($platforms as $code => $label)
                    @php
                        $state = $platformStates[$code] ?? ['mode'=>'MANUAL','enabled'=>false];
                        $mode = $state['mode'] ?? 'MANUAL';
                        $enabled = (bool) ($state['enabled'] ?? false);
                    @endphp

                    <label class="platform-card">
                        <input
                            type="checkbox"
                            name="platforms[]"
                            value="{{ $code }}"
                            {{ in_array($code, $selectedPlatforms, true) ? 'checked' : '' }}
                        >
                        <strong>{{ $label }}</strong>

                        @if($mode === 'AUTO')
                            <p>{{ $enabled ? 'Provider is enabled and will queue after publish.' : 'Target is saved, but credentials/setup must be enabled before automatic posting.' }}</p>
                            <span class="platform-badge {{ $enabled ? 'ready' : 'warn' }}">
                                {{ $enabled ? 'AUTO READY' : 'SETUP REQUIRED' }}
                            </span>
                        @elseif($mode === 'CONFIRM')
                            <p>TikTok requires creator info, manual privacy selection and explicit consent before each Direct Post.</p>
                            <span class="platform-badge manual">CONFIRM REQUIRED</span>
                        @else
                            <p>WhatsApp Channel remains a tracked manual destination until an official supported Channel publishing API is available.</p>
                            <span class="platform-badge manual">MANUAL</span>
                        @endif
                    </label>
                @endforeach
            </div>

            @error('platforms')<div class="field-error">{{ $message }}</div>@enderror
            @error('platforms.*')<div class="field-error">{{ $message }}</div>@enderror

            <div class="notice" style="margin-top:14px">
                TikTok and WhatsApp Channel are intentionally not background-automated. TradeX records them as action-required so the workflow stays compliant and auditable.
            </div>
        </div>

        <div class="form-actions">
            <a class="secondary-btn" href="{{ route('admin.our_channel.index') }}">Cancel</a>
            <button class="draft-btn" type="submit" name="intent" value="DRAFT">Save Draft</button>
            <button class="primary-btn" type="submit" name="intent" value="PUBLISH">Upload & Publish Enabled Platforms</button>
        </div>
    </form>
</section>
@endsection
