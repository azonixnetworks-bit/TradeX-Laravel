@extends('admin.layouts.app')

@section('title', 'Create Translation Key')
@section('page-title', 'Create Translation Key')

@section('content')
<div class="page-header">
    <div>
        <div class="page-eyebrow">NEW STABLE SOURCE KEY</div>
        <h1>Create <span>Translation Key</span></h1>
        <p>
            Group and key identity become immutable after creation.
        </p>
    </div>

    <a
        class="secondary-btn"
        href="{{ route('admin.languages.keys.index') }}"
    >
        Back
    </a>
</div>

<section class="panel" style="padding:22px;">
    <form
        method="POST"
        action="{{ route('admin.languages.keys.store') }}"
    >
        @csrf

        <div class="form-grid">
            <div class="form-group">
                <label>Group</label>
                <select name="group_name" required>
                    @foreach($groups as $code => $label)
                        <option
                            value="{{ $code }}"
                            @selected(old('group_name') === $code)
                        >
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
                @error('group_name')
                    <small>{{ $message }}</small>
                @enderror
            </div>

            <div class="form-group">
                <label>Stable Key</label>
                <input
                    type="text"
                    name="translation_key"
                    value="{{ old('translation_key') }}"
                    maxlength="180"
                    placeholder="dashboard.welcome"
                    required
                >
                <small>
                    Lowercase letters/numbers with dot, underscore or dash separators.
                </small>
                @error('translation_key')
                    <small>{{ $message }}</small>
                @enderror
            </div>
        </div>

        <div class="form-group" style="margin-top:18px;">
            <label>English Source Text</label>
            <textarea
                name="source_text"
                rows="12"
                maxlength="10000"
                required
            >{{ old('source_text') }}</textarea>
            @error('source_text')
                <small>{{ $message }}</small>
            @enderror
        </div>

        <div style="margin-top:20px;">
            <button class="primary-btn" type="submit">
                Create Source Key
            </button>
        </div>
    </form>
</section>
@endsection
