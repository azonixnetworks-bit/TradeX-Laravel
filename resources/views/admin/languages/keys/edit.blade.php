@extends('admin.layouts.app')

@section('title', 'Edit Translation Source')
@section('page-title', 'Edit Translation Source')

@section('content')
<div class="page-header">
    <div>
        <div class="page-eyebrow">
            {{ $key->group_name }} · {{ $key->translation_key }}
        </div>

        <h1>Edit <span>Source Text</span></h1>

        <p>
            Key identity is immutable. Updating source text changes its
            fingerprint and makes old target translations stale.
        </p>
    </div>

    <a
        class="secondary-btn"
        href="{{ route('admin.languages.keys.index') }}"
    >
        Back
    </a>
</div>

@if(session('success'))
    <section class="panel" style="padding:14px;margin-bottom:18px;">
        {{ session('success') }}
    </section>
@endif

<section class="panel users-panel" style="margin-bottom:18px;">
    <div class="users-table-wrap">
        <table class="users-table">
            <tbody>
                <tr>
                    <th>Group</th>
                    <td>{{ $key->group_name }}</td>
                </tr>
                <tr>
                    <th>Stable Key</th>
                    <td><code>{{ $key->translation_key }}</code></td>
                </tr>
                <tr>
                    <th>Version</th>
                    <td>{{ $key->version }}</td>
                </tr>
                <tr>
                    <th>Source SHA-256</th>
                    <td><code>{{ $key->source_sha256 }}</code></td>
                </tr>
            </tbody>
        </table>
    </div>
</section>

<section class="panel" style="padding:22px;">
    <form
        method="POST"
        action="{{ route(
            'admin.languages.keys.update',
            $key
        ) }}"
    >
        @csrf
        @method('PATCH')

        <input
            type="hidden"
            name="version"
            value="{{ $key->version }}"
        >

        <div class="form-group">
            <label>English Source Text</label>
            <textarea
                name="source_text"
                rows="14"
                maxlength="10000"
                required
            >{{ old('source_text', $key->source_text) }}</textarea>
            @error('source_text')
                <small>{{ $message }}</small>
            @enderror
        </div>

        <div style="margin-top:20px;">
            <button class="primary-btn" type="submit">
                Save Source Revision
            </button>
        </div>
    </form>
</section>
@endsection
