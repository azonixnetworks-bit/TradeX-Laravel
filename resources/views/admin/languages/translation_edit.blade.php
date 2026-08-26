@extends('admin.layouts.app')

@section('title', $language->english_name.' Translation')
@section('page-title', $language->english_name.' Translation')

@section('content')
<div class="page-header">
    <div>
        <div class="page-eyebrow">
            {{ strtoupper($language->code) }}
            · {{ $key->group_name }}
            · {{ $key->translation_key }}
        </div>

        <h1>
            {{ $language->english_name }}
            <span>{{ $language->native_name }}</span>
        </h1>

        <p>
            Current derived state: {{ $derivedStatus }}
        </p>
    </div>

    <a
        class="secondary-btn"
        href="{{ route(
            'admin.languages.translations.index',
            $language
        ) }}"
    >
        Back
    </a>
</div>

@if(session('success'))
    <section class="panel" style="padding:14px;margin-bottom:18px;">
        {{ session('success') }}
    </section>
@endif

@if($derivedStatus === 'STALE')
<section
    class="panel"
    style="padding:18px;margin-bottom:18px;"
>
    <strong>STALE:</strong>
    English source text changed after this translation was drafted/reviewed.
    Update the target translation before review.
</section>
@endif

<section class="panel users-panel" style="margin-bottom:18px;">
    <div class="panel-header">
        <div>
            <div class="panel-eyebrow">CURRENT SOURCE</div>
            <h2>English</h2>
        </div>
    </div>

    <div class="users-table-wrap">
        <table class="users-table">
            <tbody>
                <tr>
                    <th>Stable Key</th>
                    <td><code>{{ $key->translation_key }}</code></td>
                </tr>

                <tr>
                    <th>Source SHA-256</th>
                    <td><code>{{ $key->source_sha256 }}</code></td>
                </tr>

                <tr>
                    <th>Source Version</th>
                    <td>{{ $key->version }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div
        style="
            padding:20px;
            white-space:pre-wrap;
            line-height:1.7;
        "
    >{{ $key->source_text }}</div>
</section>

<section class="panel" style="padding:22px;margin-bottom:18px;">
    <h2>Human Translation Draft</h2>

    <form
        method="POST"
        action="{{ route(
            'admin.languages.translations.update',
            [
                $language,
                $key,
            ]
        ) }}"
    >
        @csrf
        @method('PATCH')

        <input
            type="hidden"
            name="version"
            value="{{ $translation?->version ?? 0 }}"
        >

        <div class="form-group" style="margin-top:18px;">
            <label>{{ $language->english_name }} Text</label>

            <textarea
                name="translated_text"
                rows="16"
                maxlength="10000"
                dir="{{ strtolower($language->direction) === 'rtl' ? 'rtl' : 'ltr' }}"
                required
            >{{ old('translated_text', $translation?->translated_text) }}</textarea>

            @error('translated_text')
                <small>{{ $message }}</small>
            @enderror
        </div>

        <div style="margin-top:20px;">
            <button class="primary-btn" type="submit">
                Save Translation Draft
            </button>
        </div>
    </form>
</section>

@if($translation && filled($translation->translated_text))
<section class="panel" style="padding:22px;">
    <h2>Human Review</h2>

    <p>
        Reviewing certifies only that this translation was checked against the
        current English source fingerprint. Source changes later will make it
        stale automatically.
    </p>

    <table class="users-table" style="margin-top:14px;">
        <tbody>
            <tr>
                <th>Translation SHA-256</th>
                <td><code>{{ $translation->translation_sha256 }}</code></td>
            </tr>
            <tr>
                <th>Drafted Against Source</th>
                <td><code>{{ $translation->source_sha256_at_translation }}</code></td>
            </tr>
            <tr>
                <th>Saved Status</th>
                <td>{{ $translation->status }}</td>
            </tr>
            <tr>
                <th>Reviewed At</th>
                <td>{{ $translation->reviewed_at?->format('Y-m-d H:i:s') ?? 'Not reviewed' }}</td>
            </tr>
        </tbody>
    </table>

    <form
        method="POST"
        action="{{ route(
            'admin.languages.translations.review',
            [
                $language,
                $key,
            ]
        ) }}"
        style="margin-top:20px;"
    >
        @csrf

        <input
            type="hidden"
            name="version"
            value="{{ $translation->version }}"
        >

        <label
            style="
                display:flex;
                gap:10px;
                align-items:flex-start;
            "
        >
            <input
                type="checkbox"
                name="review_confirmation"
                value="1"
                required
            >

            <span>
                I confirm this translation has been human-reviewed against the
                current source text.
            </span>
        </label>

        @error('review_confirmation')
            <small>{{ $message }}</small>
        @enderror

        <div style="margin-top:18px;">
            <button
                class="primary-btn"
                type="submit"
                @disabled($derivedStatus === 'STALE')
            >
                Mark Reviewed
            </button>
        </div>
    </form>
</section>
@endif
@endsection
