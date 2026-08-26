@extends('admin.layouts.app')

@section('title', 'Translation Keys')
@section('page-title', 'Translation Keys')

@section('content')
<div class="page-header">
    <div>
        <div class="page-eyebrow">ENGLISH SOURCE CATALOG</div>
        <h1>Translation <span>Keys</span></h1>
        <p>
            Stable application keys and source text. Changing source text
            automatically makes older target translations stale by fingerprint.
        </p>
    </div>

    <div style="display:flex;gap:10px;flex-wrap:wrap;">
        @if($canManage)
            <a
                class="primary-btn"
                href="{{ route('admin.languages.keys.create') }}"
            >
                New Source Key
            </a>
        @endif

        <a
            class="secondary-btn"
            href="{{ route('admin.languages.index') }}"
        >
            Languages
        </a>
    </div>
</div>

@if(session('success'))
    <section class="panel" style="padding:14px;margin-bottom:18px;">
        {{ session('success') }}
    </section>
@endif

<section class="panel" style="padding:18px;margin-bottom:18px;">
    <form
        method="GET"
        action="{{ route('admin.languages.keys.index') }}"
    >
        <div class="form-grid">
            <div class="form-group">
                <label>Search</label>
                <input
                    type="text"
                    name="search"
                    value="{{ $filters['search'] }}"
                    maxlength="180"
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
            <div class="panel-eyebrow">SOURCE INVENTORY</div>
            <h2>Stable Keys</h2>
        </div>

        <div class="panel-count">
            {{ $keys->total() }} keys
        </div>
    </div>

    <div class="users-table-wrap">
        <table class="users-table">
            <thead>
                <tr>
                    <th>Group</th>
                    <th>Key</th>
                    <th>Source Text</th>
                    <th>Source Hash</th>
                    <th>Translations</th>
                    <th></th>
                </tr>
            </thead>

            <tbody>
            @forelse($keys as $key)
                <tr>
                    <td>{{ $key->group_name }}</td>
                    <td><code>{{ $key->translation_key }}</code></td>
                    <td>{{ \Illuminate\Support\Str::limit($key->source_text, 90) }}</td>
                    <td><code>{{ substr($key->source_sha256, 0, 12) }}…</code></td>
                    <td>{{ $key->translations_count }}</td>
                    <td>
                        @if($canManage)
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
                    <td colspan="6">
                        No translation keys yet.
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
