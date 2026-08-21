{{-- TradeX Admin — ADMIN-04 Create Our Channel Video --}}

@extends('admin.layouts.app')

@section('title', 'Add Our Channel Video | TradeX')
@section('page-title', 'Our Channel')

@push('styles')

<style>
    .channel-form{max-width:980px}.form-section{padding:22px;border-bottom:1px solid rgba(255,255,255,.06)}.form-section:last-child{border-bottom:0}
    .form-section h2{margin:0 0 6px;font-size:16px}.form-section>p{margin:0 0 18px;font-size:11px;opacity:.55;line-height:1.6}
    .form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:15px}.form-field.full{grid-column:1/-1}.form-field label{display:block;margin-bottom:7px;font-size:10px;font-weight:800;letter-spacing:.04em}
    .form-field input,.form-field textarea,.form-field select{width:100%;box-sizing:border-box;padding:11px 12px;border:1px solid rgba(255,255,255,.08);border-radius:10px;background:rgba(255,255,255,.025);color:inherit;font:inherit;font-size:12px}.form-field textarea{min-height:115px;resize:vertical}.hint{margin-top:6px;font-size:9px;opacity:.48;line-height:1.5}.field-error{margin-top:6px;color:#ff8d8d;font-size:10px}.check-row{display:flex;align-items:center;gap:8px;padding-top:8px}.check-row input{width:auto}
    .form-actions{display:flex;justify-content:flex-end;gap:10px;padding:20px 22px}@media(max-width:720px){.form-grid{grid-template-columns:1fr}.form-field.full{grid-column:auto}}
</style>

@endpush

@section('content')
<div class="page-header">
    <div>
        <div class="page-eyebrow">ADMIN-04 · OUR CHANNEL</div>
        <h1>Add <span>YouTube Video</span></h1>
        <p>The video remains hosted by YouTube. TradeX stores only curated catalogue metadata.</p>
    </div>

    <div>
        <a class="secondary-btn" href="{{ route('admin.our_channel.index') }}">← Back</a>
    </div>
</div>

<section class="panel channel-form">
    <form method="POST" action="{{ route('admin.our_channel.store') }}">
        @csrf

        <div class="form-section">
            <h2>Video Details</h2>
            <p>Add the trusted YouTube URL and learner-facing content metadata.</p>

            <div class="form-grid">
                <div class="form-field full">
                    <label for="title">TITLE</label>
                    <input id="title" name="title" value="{{ old('title') }}" maxlength="180" required>
                    @error('title')<div class="field-error">{{ $message }}</div>@enderror
                </div>

                <div class="form-field full">
                    <label for="youtube_url">YOUTUBE URL</label>
                    <input id="youtube_url" name="youtube_url" value="{{ old('youtube_url') }}" placeholder="https://www.youtube.com/watch?v=..." maxlength="2048" required>
                    <div class="hint">HTTPS youtube.com, youtu.be, Shorts, Live and Embed links are normalized server-side.</div>
                    @error('youtube_url')<div class="field-error">{{ $message }}</div>@enderror
                </div>

                <div class="form-field">
                    <label for="language_code">LANGUAGE CODE</label>
                    <input id="language_code" name="language_code" value="{{ old('language_code', 'en') }}" maxlength="10" required>
                    <div class="hint">Examples: en, ta, si, hi, ml, te, ur.</div>
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
                    <label for="description">DESCRIPTION</label>
                    <textarea id="description" name="description" maxlength="20000">{{ old('description') }}</textarea>
                    @error('description')<div class="field-error">{{ $message }}</div>@enderror
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

        <div class="form-actions">
            <a class="secondary-btn" href="{{ route('admin.our_channel.index') }}">Cancel</a>
            <button class="primary-btn" type="submit">Create Draft</button>
        </div>
    </form>
</section>
@endsection
