@extends('admin.layouts.app')

@section('title', $language->english_name.' Translations')
@section('page-title', $language->english_name.' Translations')

@section('content')
<div class="page-header">
    <div>
        <div class="page-eyebrow">
            {{ strtoupper($language->code) }}
            · {{ $language->direction }}
            @if($language->is_source)
                · SOURCE
            @endif
        </div>

        <h1>
            {{ $language->english_name }}
            <span>{{ $language->native_name }}</span>
        </h1>

        <p>
            Registration config:
            {{ $registrationAllowed ? 'ALLOWED' : 'NOT ALLOWED' }}
            · Runtime translation API: {{ $runtimeTranslationApi }}
        </p>
    </div>

    <a
        class="secondary-btn"
        href="{{ route('admin.languages.index') }}"
    >
        Languages
    </a>
</div>

<section
    class="panel"
    style="padding:18px;margin-bottom:18px;"
>
    @if($language->is_source)
        <strong>Source language:</strong>
        English text is maintained through stable Translation Keys.
    @else
        <strong>Accuracy workflow:</strong>
        Save a human translation as DRAFT, then explicitly REVIEW it against
        the current source fingerprint. When English source text changes, old
        reviewed translations automatically become STALE.
    @endif
</section>

<section class="panel" style="padding:18px;margin-bottom:18px;">
    <form
        method="GET"
        action="{{ route(
            'admin.languages.translations.index',
            $language
        ) }}"
    >
        <div class="form-grid">
            <div class="form-group">
                <label>Search</label>
                <input
                    type="text"
                    name="search"
                    maxlength="180"
                    value="{{ $filters['search'] }}"
                    placeholder="key or source text"
                >
            </div>

            <div class="form-group">
                <label>Group</label>
                <select name="group">
                    <option value="">All groups</option>
                    @foreach($groups as $code => $label)
                        <option
                            value="{{ $code }}"
                            @selected($filters['group'] === $code)
                        >
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>

            @if(!$language->is_source)
                <div class="form-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="">All status</option>
                        @foreach(['MISSING', 'DRAFT', 'REVIEWED', 'STALE'] as $status)
                            <option
                                value="{{ $status }}"
                                @selected($filters['status'] === $status)
                            >
                                {{ $status }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif
        </div>

        <div style="margin-top:14px;">
            <button class="secondary-btn" type="submit">
                Filter
            </button>
        </div>
    </form>
</section>

<section class="panel users-panel">
    <div class="panel-header">
        <div>
            <div class="panel-eyebrow">TRANSLATION WORKLIST</div>
            <h2>{{ $language->english_name }}</h2>
        </div>

        <div class="panel-count">
            {{ $keys->total() }} source keys
        </div>
    </div>

    <div class="users-table-wrap">
        <table class="users-table">
            <thead>
                <tr>
                    <th>Group / Key</th>
                    <th>English Source</th>
                    <th>Status</th>
                    <th>Translation</th>
                    <th></th>
                </tr>
            </thead>

            <tbody>
            @forelse($keys as $key)
                @php
                    $translation = $key->translations->first();

                    $status = $language->is_source
                        ? 'SOURCE'
                        : app(
                            \App\Services\Admin\Languages\AdminLanguageService::class
                        )->translationStatus(
                            $translation,
                            $key
                        );
                @endphp

                <tr>
                    <td>
                        <strong>{{ $key->group_name }}</strong>
                        <br>
                        <code>{{ $key->translation_key }}</code>
                    </td>

                    <td>
                        {{ \Illuminate\Support\Str::limit($key->source_text, 100) }}
                    </td>

                    <td>
                        <strong>{{ $status }}</strong>
                    </td>

                    <td>
                        @if($language->is_source)
                            {{ \Illuminate\Support\Str::limit($key->source_text, 100) }}
                        @elseif($translation && filled($translation->translated_text))
                            <span dir="{{ strtolower($language->direction) === 'rtl' ? 'rtl' : 'ltr' }}">
                                {{ \Illuminate\Support\Str::limit($translation->translated_text, 100) }}
                            </span>
                        @else
                            —
                        @endif
                    </td>

                    <td>
                        @if(!$language->is_source && $canManage)
                            <a
                                class="secondary-btn"
                                href="{{ route(
                                    'admin.languages.translations.edit',
                                    [
                                        $language,
                                        $key,
                                    ]
                                ) }}"
                            >
                                Translate
                            </a>
                        @elseif($language->is_source && $canManage)
                            <a
                                class="secondary-btn"
                                href="{{ route(
                                    'admin.languages.keys.edit',
                                    $key
                                ) }}"
                            >
                                Edit Source
                            </a>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5">
                        No translation keys match this filter.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div style="padding:18px;">
        {{ $keys->links() }}
    </div>
</section>
@endsection
