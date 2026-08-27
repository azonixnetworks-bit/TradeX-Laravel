@extends('admin.layouts.app')

@section('title', 'Languages & Translation Management')
@section('page-title', 'Languages & Translation Management')

@section('content')
<div class="page-header">
    <div>
        <div class="page-eyebrow">HUMAN-REVIEWED LOCALIZATION CONTROL PLANE</div>
        <h1>Languages & <span>Translations</span></h1>
        <p>
            Manage the seven approved language profiles, source translation
            keys, draft translations, review state and stale detection.
        </p>
    </div>

    <a
        class="primary-btn"
        href="{{ route('admin.languages.keys.index') }}"
    >
        Translation Keys
    </a>
</div>

@if(session('success'))
    <section
        class="panel"
        style="padding:14px;margin-bottom:18px;"
    >
        {{ session('success') }}
    </section>
@endif

<section
    class="panel"
    style="padding:18px;margin-bottom:18px;"
>
    <strong>Runtime boundary:</strong>
    translation API status is {{ $runtimeTranslationApi }}.
    ADMIN-18 manages a verified translation catalog but does not falsely claim
    the mobile app is consuming these database translations yet.
</section>

<section
    class="panel"
    style="padding:18px;margin-bottom:18px;"
>
    <strong>Education-content boundary:</strong>
    {{ $educationContentLocalization }}.
    Existing Course / Module / Lesson schemas remain frozen; ADMIN-18 handles
    application translation keys only and does not duplicate education content.
</section>

<div class="user-stat-grid">
    <div class="user-stat-card">
        <span>Languages</span>
        <strong>{{ $languageCards->count() }}</strong>
    </div>

    <div class="user-stat-card">
        <span>Source Keys</span>
        <strong>{{ $totalKeys }}</strong>
    </div>

    <div class="user-stat-card">
        <span>Source Language</span>
        <strong>English</strong>
    </div>

    <div class="user-stat-card">
        <span>Review Model</span>
        <strong>Human Reviewed</strong>
    </div>
</div>

<div class="language-card-grid">
@foreach($languageCards as $card)
    @php
        $language = $card['language'];
    @endphp

    <section
        class="panel"
        style="padding:20px;"
    >
        <div class="panel-eyebrow">
            {{ strtoupper($language->code) }}
            ·
            {{ $language->direction }}
            @if($language->is_source)
                · SOURCE
            @endif
        </div>

        <h2 style="margin-top:8px;">
            {{ $language->english_name }}
            <span style="font-weight:400;">
                — {{ $language->native_name }}
            </span>
        </h2>

        <table
            class="users-table compact-meta-table"
            style="margin-top:14px;"
        >
            <tbody>
                <tr>
                    <th>Registration Config</th>
                    <td>
                        {{ $card['registration_allowed'] ? 'ALLOWED' : 'NOT ALLOWED' }}
                    </td>
                </tr>

                <tr>
                    <th>Translation Program</th>
                    <td>
                        {{ $language->is_translation_enabled ? 'ENABLED' : 'DISABLED' }}
                    </td>
                </tr>

                <tr>
                    <th>Total Keys</th>
                    <td>{{ $card['total_keys'] }}</td>
                </tr>

                <tr>
                    <th>Reviewed Current</th>
                    <td>{{ $card['reviewed_current'] }}</td>
                </tr>

                <tr>
                    <th>Draft</th>
                    <td>{{ $card['draft'] }}</td>
                </tr>

                <tr>
                    <th>Stale</th>
                    <td>{{ $card['stale'] }}</td>
                </tr>

                <tr>
                    <th>Missing</th>
                    <td>{{ $card['missing'] }}</td>
                </tr>

                <tr>
                    <th>Completion</th>
                    <td>{{ $card['completion_percent'] }}%</td>
                </tr>
            </tbody>
        </table>

        <div
            style="
                display:flex;
                gap:10px;
                flex-wrap:wrap;
                margin-top:18px;
            "
        >
            <a
                class="secondary-btn"
                href="{{ route(
                    'admin.languages.translations.index',
                    $language
                ) }}"
            >
                {{ $language->is_source ? 'Source Catalog' : 'Translations' }}
            </a>

            @if($canManage)
                <form
                    method="POST"
                    action="{{ route(
                        'admin.languages.update',
                        $language
                    ) }}"
                >
                    @csrf
                    @method('PATCH')

                    <input
                        type="hidden"
                        name="version"
                        value="{{ $language->version }}"
                    >

                    <input
                        type="hidden"
                        name="is_translation_enabled"
                        value="{{ $language->is_translation_enabled ? 0 : 1 }}"
                    >

                    <button
                        class="secondary-btn"
                        type="submit"
                        @disabled(
                            $language->is_source
                            && $language->is_translation_enabled
                        )
                    >
                        {{ $language->is_translation_enabled
                            ? 'Disable Translation Program'
                            : 'Enable Translation Program' }}
                    </button>
                </form>
            @endif
        </div>
    </section>
@endforeach
</div>
@endsection
