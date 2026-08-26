@extends('admin.layouts.app')

@section('title', $policy->title)
@section('page-title', $policy->title)

@section('content')
<div class="page-header">
    <div>
        <div class="page-eyebrow">
            {{ $policy->policy_type }}
            · v{{ $policy->version_number }}
            · {{ $derivedStatus }}
        </div>

        <h1>{{ $policy->title }}</h1>

        <p>
            {{ $definition['display_name'] ?? $policy->policy_type }}
            · Audience: {{ $definition['audience'] ?? 'UNSPECIFIED' }}
        </p>
    </div>

    <div
        style="
            display:flex;
            gap:10px;
            flex-wrap:wrap;
        "
    >
        @if($isDraft && $canManage)
            <a
                class="secondary-btn"
                href="{{ route(
                    'admin.legal.edit',
                    $policy
                ) }}"
            >
                Edit Draft
            </a>
        @endif

        <a
            class="secondary-btn"
            href="{{ route('admin.legal.index') }}"
        >
            Back
        </a>
    </div>
</div>

@if(session('success'))
    <section
        class="panel"
        style="padding:14px;margin-bottom:18px;"
    >
        {{ session('success') }}
    </section>
@endif

<div class="user-stat-grid">
    <div class="user-stat-card">
        <span>Status</span>
        <strong>{{ $derivedStatus }}</strong>
    </div>

    <div class="user-stat-card">
        <span>Version</span>
        <strong>v{{ $policy->version_number }}</strong>
    </div>

    <div class="user-stat-card">
        <span>Draft Revision</span>
        <strong>{{ $policy->revision_version }}</strong>
    </div>

    <div class="user-stat-card">
        <span>Acceptance Runtime</span>
        <strong>{{ $acceptanceRuntime }}</strong>
    </div>
</div>

<section
    class="panel users-panel"
    style="margin-bottom:18px;"
>
    <div class="panel-header">
        <div>
            <div class="panel-eyebrow">INTEGRITY METADATA</div>
            <h2>Version Record</h2>
        </div>
    </div>

    <div class="users-table-wrap">
        <table class="users-table">
            <tbody>
                <tr>
                    <th>Policy UUID</th>
                    <td>{{ $policy->uuid }}</td>
                </tr>
                <tr>
                    <th>Content SHA-256</th>
                    <td>
                        <code>{{ $policy->content_sha256 }}</code>
                    </td>
                </tr>
                <tr>
                    <th>Published At</th>
                    <td>
                        {{ $policy->published_at?->format('Y-m-d H:i:s') ?? 'Not published' }}
                    </td>
                </tr>
                <tr>
                    <th>Effective At</th>
                    <td>
                        {{ $policy->effective_at?->format('Y-m-d H:i:s') ?? 'Not assigned' }}
                    </td>
                </tr>
                <tr>
                    <th>Created By</th>
                    <td>
                        {{ $policy->createdByAdmin?->name ?? 'Unavailable' }}
                    </td>
                </tr>
                <tr>
                    <th>Published By</th>
                    <td>
                        {{ $policy->publishedByAdmin?->name ?? 'Not published' }}
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</section>

@if($policy->summary)
<section
    class="panel"
    style="padding:20px;margin-bottom:18px;"
>
    <h2>Summary</h2>
    <p style="margin-top:12px;">
        {{ $policy->summary }}
    </p>
</section>
@endif

<section
    class="panel"
    style="padding:22px;margin-bottom:18px;"
>
    <div class="panel-eyebrow">ESCAPED PLAIN-TEXT RENDER</div>
    <h2 style="margin-top:8px;">Policy Text</h2>

    <div
        style="
            white-space:pre-wrap;
            margin-top:18px;
            line-height:1.7;
        "
    >{{ $policy->content }}</div>
</section>

@if($isDraft && $canPublish)
<section
    class="panel"
    style="padding:22px;margin-bottom:18px;"
>
    <h2>Publish & Lock This Version</h2>

    <p>
        Publication is irreversible inside ADMIN-17. The content, title,
        summary, effective date and integrity hash become immutable.
    </p>

    <form
        method="POST"
        action="{{ route(
            'admin.legal.publish',
            $policy
        ) }}"
    >
        @csrf

        <input
            type="hidden"
            name="revision_version"
            value="{{ $policy->revision_version }}"
        >

        <div class="form-group">
            <label>Effective At</label>
            <input
                type="datetime-local"
                name="effective_at"
                value="{{ old('effective_at') }}"
            >
            <small>
                Leave blank for immediate effect. Backdating is rejected.
            </small>
            @error('effective_at')
                <small>{{ $message }}</small>
            @enderror
        </div>

        <div
            style="
                margin-top:18px;
                display:flex;
                gap:10px;
                align-items:flex-start;
            "
        >
            <input
                id="publish_confirmation"
                type="checkbox"
                name="publish_confirmation"
                value="1"
                required
            >

            <label for="publish_confirmation">
                I confirm that the legal text and jurisdictional review are
                complete, and I understand this version becomes immutable.
            </label>
        </div>

        @error('publish_confirmation')
            <small>{{ $message }}</small>
        @enderror

        <div style="margin-top:20px;">
            <button
                class="primary-btn"
                type="submit"
            >
                Publish Immutable Version
            </button>
        </div>
    </form>
</section>
@endif

@if(!$isDraft)
<section
    class="panel"
    style="padding:18px;margin-bottom:18px;"
>
    <strong>Immutable publication:</strong>
    this version cannot be edited or deleted. A policy change requires a new
    versioned draft.
</section>
@endif

<section class="panel users-panel">
    <div class="panel-header">
        <div>
            <div class="panel-eyebrow">VERSION HISTORY</div>
            <h2>{{ $definition['display_name'] ?? $policy->policy_type }}</h2>
        </div>

        <div class="panel-count">
            {{ $history->count() }} versions
        </div>
    </div>

    <div class="users-table-wrap">
        <table class="users-table">
            <thead>
                <tr>
                    <th>Version</th>
                    <th>Status</th>
                    <th>Effective</th>
                    <th>Fingerprint</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @foreach($history as $version)
                <tr>
                    <td>v{{ $version->version_number }}</td>
                    <td>
                        @if($version->isDraft())
                            DRAFT
                        @elseif(
                            $version->effective_at
                            && $version->effective_at->isFuture()
                        )
                            SCHEDULED
                        @elseif($currentPolicy && $version->is($currentPolicy))
                            CURRENT
                        @else
                            PUBLISHED HISTORY
                        @endif
                    </td>
                    <td>
                        {{ $version->effective_at?->format('Y-m-d H:i') ?? '—' }}
                    </td>
                    <td>
                        <code>{{ substr($version->content_sha256, 0, 16) }}…</code>
                    </td>
                    <td>
                        <a
                            class="secondary-btn"
                            href="{{ route(
                                'admin.legal.show',
                                $version
                            ) }}"
                        >
                            Inspect
                        </a>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</section>
@endsection
