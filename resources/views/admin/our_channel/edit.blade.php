{{-- TradeX Admin — Edit Our Channel Metadata --}}

@extends('admin.layouts.app')

@section('title', 'Edit Our Channel Video | TradeX')
@section('page-title', 'Our Channel')

@push('styles')
<style>
    .channel-form{max-width:980px}.form-section{padding:22px;border-bottom:1px solid rgba(255,255,255,.06)}.form-section:last-child{border-bottom:0}
    .form-section h2{margin:0 0 6px;font-size:16px}.form-section>p{margin:0 0 18px;font-size:11px;opacity:.55;line-height:1.6}
    .form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:15px}.form-field.full{grid-column:1/-1}.form-field label{display:block;margin-bottom:7px;font-size:10px;font-weight:800;letter-spacing:.04em}
    .form-field input,.form-field textarea{width:100%;box-sizing:border-box;padding:11px 12px;border:1px solid rgba(255,255,255,.08);border-radius:10px;background:rgba(255,255,255,.025);color:inherit;font:inherit;font-size:12px}.form-field textarea{min-height:115px;resize:vertical}
    .hint{margin-top:6px;font-size:9px;opacity:.48;line-height:1.5}.field-error{margin-top:6px;color:#ff8d8d;font-size:10px}.check-row{display:flex;align-items:center;gap:8px;padding-top:8px}.check-row input{width:auto}.source-box{padding:11px 12px;border:1px solid rgba(218,178,88,.16);border-radius:10px;background:rgba(218,178,88,.04);font-size:10px;line-height:1.6;color:#dbc17d}
    .form-actions{display:flex;justify-content:flex-end;gap:10px;padding:20px 22px}@media(max-width:720px){.form-grid{grid-template-columns:1fr}.form-field.full{grid-column:auto}}
</style>
@endpush

@section('content')
<div class="page-header">
    <div>
        <div class="page-eyebrow">OUR CHANNEL · METADATA</div>
        <h1>Edit <span>Video</span></h1>
        <p>Update TradeX metadata without silently changing external publication state.</p>
    </div>

    <div>
        <a class="secondary-btn" href="{{ route('admin.our_channel.index') }}">← Back</a>
    </div>
</div>

<section class="panel channel-form">
    <form method="POST" action="{{ route('admin.our_channel.update', ['channelVideo'=>$channelVideo]) }}">
        @csrf
        @method('PATCH')

        <div class="form-section">
            <h2>Video Details</h2>
            <p>The protected master file and per-platform publication history are immutable from this metadata form.</p>

            <div class="form-grid">
                <div class="form-field full">
                    <label for="title">TITLE</label>
                    <input id="title" name="title" value="{{ old('title', $channelVideo->title) }}" maxlength="180" required>
                    @error('title')<div class="field-error">{{ $message }}</div>@enderror
                </div>

                @if($channelVideo->hasSourceMedia())
                    <div class="form-field full">
                        <label>MASTER SOURCE</label>
                        <div class="source-box">
                            {{ $channelVideo->sourceDisplayName() ?: 'Protected master video' }}
                            @if($channelVideo->source_size_bytes)
                                · {{ number_format($channelVideo->source_size_bytes / 1048576, 1) }} MB
                            @endif
                            · stored privately
                        </div>
                    </div>
                @endif

                <div class="form-field full">
                    <label for="youtube_url">YOUTUBE URL · OPTIONAL</label>
                    <input id="youtube_url" name="youtube_url" value="{{ old('youtube_url', $channelVideo->youtube_url) }}" maxlength="2048">
                    <div class="hint">For a TradeX master upload, this may be filled automatically after a successful YouTube publication.</div>
                    @error('youtube_url')<div class="field-error">{{ $message }}</div>@enderror
                </div>

                <div class="form-field">
                    <label for="language_code">LANGUAGE CODE</label>
                    <input id="language_code" name="language_code" value="{{ old('language_code', $channelVideo->language_code) }}" maxlength="10" required>
                    @error('language_code')<div class="field-error">{{ $message }}</div>@enderror
                </div>

                <div class="form-field">
                    <label for="sort_order">SORT ORDER</label>
                    <input id="sort_order" type="number" min="0" max="1000000" name="sort_order" value="{{ old('sort_order', $channelVideo->sort_order) }}">
                    @error('sort_order')<div class="field-error">{{ $message }}</div>@enderror
                </div>

                <div class="form-field full">
                    <label for="short_description">SHORT DESCRIPTION</label>
                    <textarea id="short_description" name="short_description" maxlength="500">{{ old('short_description', $channelVideo->short_description) }}</textarea>
                    @error('short_description')<div class="field-error">{{ $message }}</div>@enderror
                </div>

                <div class="form-field full">
                    <label for="description">DESCRIPTION / CAPTION</label>
                    <textarea id="description" name="description" maxlength="20000">{{ old('description', $channelVideo->description) }}</textarea>
                    @error('description')<div class="field-error">{{ $message }}</div>@enderror
                </div>

                <div class="form-field full">
                    <label for="hashtags">HASHTAGS</label>
                    <textarea id="hashtags" name="hashtags" maxlength="2000">{{ old('hashtags', $channelVideo->hashtags) }}</textarea>
                    @error('hashtags')<div class="field-error">{{ $message }}</div>@enderror
                </div>

                <div class="form-field full">
                    <label class="check-row">
                        <input type="hidden" name="is_featured" value="0">
                        <input type="checkbox" name="is_featured" value="1" {{ old('is_featured', $channelVideo->is_featured) ? 'checked' : '' }}>
                        <span>Feature this video in learner discovery</span>
                    </label>
                    @error('is_featured')<div class="field-error">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>

        <div class="form-actions">
            <a class="secondary-btn" href="{{ route('admin.our_channel.index') }}">Cancel</a>
            <button class="primary-btn" type="submit">Save Metadata</button>
        </div>
    </form>
</section>
@endsection
