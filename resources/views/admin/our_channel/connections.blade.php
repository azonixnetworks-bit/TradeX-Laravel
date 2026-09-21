{{-- TradeX Admin — Our Channel Connections --}}

@extends('admin.layouts.app')

@section('title', 'Channel Connections | TradeX')
@section('page-title', 'Channel Connections')

@push('styles')
<style>
    .connection-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}.connection-card{padding:18px;border:1px solid rgba(255,255,255,.07);border-radius:14px;background:rgba(255,255,255,.022)}.connection-card h2{margin:5px 0 7px;font-size:16px}.connection-card p{margin:0;color:var(--text-secondary);font-size:10px;line-height:1.6}.connection-status{display:inline-flex;margin-top:11px;padding:5px 8px;border-radius:999px;border:1px solid rgba(255,255,255,.09);font-size:8px;font-weight:900;letter-spacing:.04em}.connection-status.ready{color:#78df9f;border-color:rgba(120,223,159,.23)}.connection-status.warn{color:#e7bf69;border-color:rgba(231,191,105,.23)}.connection-status.manual{color:#aab3ff;border-color:rgba(170,179,255,.22)}
    .connection-form{margin-top:18px}.connection-form .form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.connection-form .form-group.full{grid-column:1/-1}.connection-form label{display:block;margin-bottom:7px;font-size:10px;font-weight:800}.connection-form input,.connection-form select{width:100%;box-sizing:border-box;padding:11px 12px;border:1px solid rgba(255,255,255,.08);border-radius:10px;background:rgba(255,255,255,.025);color:inherit;font:inherit;font-size:12px}.connection-help{margin-top:6px;font-size:9px;line-height:1.5;opacity:.54}.connection-error{margin-top:6px;color:#ff8d8d;font-size:10px}.connection-actions{display:flex;justify-content:flex-end;gap:10px;margin-top:16px;flex-wrap:wrap}.connection-secondary{border:1px solid rgba(255,255,255,.1);background:rgba(255,255,255,.035);color:inherit;border-radius:10px;padding:10px 14px;font:inherit;font-size:10px;font-weight:800;cursor:pointer}
    .verified-box{margin-top:15px;padding:12px;border:1px solid rgba(120,223,159,.16);border-radius:10px;background:rgba(120,223,159,.035);font-size:10px;line-height:1.65}.verified-box strong{color:#78df9f}.secret-note{padding:12px 14px;margin-bottom:16px;border:1px solid rgba(218,178,88,.17);border-radius:10px;background:rgba(218,178,88,.04);color:#dbc17d;font-size:10px;line-height:1.6}
    @media(max-width:1050px){.connection-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:720px){.connection-grid,.connection-form .form-grid{grid-template-columns:1fr}.connection-form .form-group.full{grid-column:auto}}
</style>
@endpush

@section('content')
<div class="page-header">
    <div>
        <div class="page-eyebrow">OUR CHANNEL · PROVIDER CONNECTIONS</div>
        <h1>Channel <span>Connections</span></h1>
        <p>Connect approved publishing accounts without exposing provider secrets in the admin interface.</p>
    </div>

    <div style="display:flex;gap:10px;flex-wrap:wrap">
        <a class="secondary-btn" href="{{ route('admin.our_channel.index') }}">← Our Channel</a>
        <a class="primary-btn" href="{{ route('admin.our_channel.create') }}">Upload Video</a>
    </div>
</div>

@if(session('success'))
    <section class="panel" style="padding:14px;margin-bottom:18px">{{ session('success') }}</section>
@endif

<div class="secret-note">
    Telegram bot tokens are encrypted at rest using the TradeX application encryption key. Saved tokens are never rendered back to the browser, audit log, or status page.
</div>

<div class="connection-grid">
    @php($telegram = $connections['telegram'])
    <section class="connection-card" style="grid-column:1/-1">
        <div class="panel-eyebrow">TELEGRAM BOT API</div>
        <h2>Telegram Channel</h2>
        <p>Verify the bot identity, Channel destination and administrator post permission before enabling automatic video publishing.</p>

        <span class="connection-status {{ $telegram['enabled'] ? 'ready' : ($telegram['last_test_status'] === 'SUCCESS' ? 'ready' : 'warn') }}">
            @if($telegram['enabled'])
                AUTO READY
            @elseif($telegram['last_test_status'] === 'SUCCESS')
                VERIFIED · DISABLED
            @elseif($telegram['last_test_status'] === 'FAILED')
                TEST FAILED
            @else
                SETUP REQUIRED
            @endif
        </span>

        @if($telegram['last_test_status'] === 'SUCCESS')
            <div class="verified-box">
                <strong>Verified</strong><br>
                Bot: {{ $telegram['metadata']['bot_username'] ?? $telegram['metadata']['bot_display_name'] ?? 'Telegram Bot' }}<br>
                Channel: {{ $telegram['metadata']['channel_title'] ?? $telegram['metadata']['channel_username'] ?? $telegram['chat_id_masked'] ?? 'Configured Channel' }}<br>
                Permission: Can post messages<br>
                Last tested: {{ $telegram['last_tested_at']?->format('Y-m-d H:i:s') ?? '—' }}
            </div>
        @elseif($telegram['last_test_message'])
            <div class="verified-box" style="border-color:rgba(255,139,139,.18);background:rgba(255,139,139,.035)">
                {{ $telegram['last_test_message'] }}
            </div>
        @endif

        @if($canManage)
            <form class="connection-form" method="POST" action="{{ route('admin.our_channel.connections.telegram.update') }}">
                @csrf
                @method('PATCH')

                <input type="hidden" name="version" value="{{ $telegram['version'] }}">

                <div class="form-grid">
                    <div class="form-group">
                        <label for="telegram_bot_token">BOT TOKEN</label>
                        <input
                            id="telegram_bot_token"
                            type="password"
                            name="bot_token"
                            value=""
                            maxlength="255"
                            autocomplete="new-password"
                            placeholder="{{ $telegram['token_present'] ? 'Saved securely — leave blank to keep current token' : '123456789:AA...' }}"
                        >
                        <div class="connection-help">Never paste this token into screenshots or source code. Leave blank to keep the saved token.</div>
                        @error('bot_token')<div class="connection-error">{{ $message }}</div>@enderror
                    </div>

                    <div class="form-group">
                        <label for="telegram_chat_id">CHANNEL ID / @USERNAME</label>
                        <input
                            id="telegram_chat_id"
                            name="chat_id"
                            value="{{ old('chat_id', $telegram['chat_id_masked'] && str_starts_with($telegram['chat_id_masked'], '@') ? $telegram['chat_id_masked'] : '') }}"
                            maxlength="128"
                            placeholder="@tradex_channel or -1001234567890"
                            autocomplete="off"
                        >
                        <div class="connection-help">
                            @if($telegram['chat_id_masked'] && !str_starts_with($telegram['chat_id_masked'], '@'))
                                Saved destination: {{ $telegram['chat_id_masked'] }}. Leave blank to keep the saved numeric Channel ID.
                            @else
                                Use the public @username or Telegram numeric Channel ID.
                            @endif
                        </div>
                        @error('chat_id')<div class="connection-error">{{ $message }}</div>@enderror
                    </div>

                    <div class="form-group">
                        <label for="telegram_protect_content">CONTENT PROTECTION</label>
                        <select id="telegram_protect_content" name="protect_content">
                            <option value="0" @selected(!old('protect_content', $telegram['protect_content']))>Allow forwarding/saving where Telegram permits</option>
                            <option value="1" @selected((bool)old('protect_content', $telegram['protect_content']))>Protect content</option>
                        </select>
                        @error('protect_content')<div class="connection-error">{{ $message }}</div>@enderror
                    </div>

                    <div class="form-group">
                        <label for="telegram_enabled">AUTO PUBLISHING</label>
                        <select id="telegram_enabled" name="is_enabled">
                            <option value="0" @selected(!old('is_enabled', $telegram['control_enabled']))>Disabled</option>
                            <option value="1" @selected((bool)old('is_enabled', $telegram['control_enabled']))>Enabled after successful test</option>
                        </select>
                        <div class="connection-help">TradeX refuses to enable automatic publishing until this exact token + Channel combination passes the connection test.</div>
                        @error('is_enabled')<div class="connection-error">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="connection-actions">
                    <button class="connection-secondary" type="submit" name="action" value="SAVE">Save Configuration</button>
                    <button class="primary-btn" type="submit" name="action" value="SAVE_TEST">Save & Test Connection</button>
                </div>
            </form>
        @endif
    </section>

    <section class="connection-card">
        <div class="panel-eyebrow">GOOGLE OAUTH</div>
        <h2>YouTube</h2>
        <p>OAuth client + channel authorization. The current uploader already supports refresh-token publishing.</p>
        <span class="connection-status {{ $connections['youtube']['enabled'] ? 'ready' : 'warn' }}">{{ $connections['youtube']['enabled'] ? 'AUTO READY' : 'OAUTH SETUP NEXT' }}</span>
    </section>

    <section class="connection-card">
        <div class="panel-eyebrow">META GRAPH API</div>
        <h2>Instagram Reels</h2>
        <p>Professional Instagram account authorization and publishing permissions will be connected in the Meta OAuth phase.</p>
        <span class="connection-status {{ $connections['instagram']['enabled'] ? 'ready' : 'warn' }}">{{ $connections['instagram']['enabled'] ? 'AUTO READY' : 'META SETUP NEXT' }}</span>
    </section>

    <section class="connection-card">
        <div class="panel-eyebrow">META GRAPH API</div>
        <h2>Facebook Reels</h2>
        <p>Facebook Page authorization and Page access token management will share the Meta OAuth connection flow.</p>
        <span class="connection-status {{ $connections['facebook']['enabled'] ? 'ready' : 'warn' }}">{{ $connections['facebook']['enabled'] ? 'AUTO READY' : 'META SETUP NEXT' }}</span>
    </section>

    <section class="connection-card">
        <div class="panel-eyebrow">CONTENT POSTING API</div>
        <h2>TikTok</h2>
        <p>Interactive creator identity, privacy and explicit consent are required before Direct Post.</p>
        <span class="connection-status manual">CONFIRM FLOW REQUIRED</span>
    </section>

    <section class="connection-card">
        <div class="panel-eyebrow">WHATSAPP</div>
        <h2>WhatsApp Channel</h2>
        <p>Tracked as manual until an official supported Channel publishing API is available for this workflow.</p>
        <span class="connection-status manual">MANUAL</span>
    </section>
</div>
@endsection
