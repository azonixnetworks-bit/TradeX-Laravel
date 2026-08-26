@extends('admin.layouts.app')

@section('title', 'Create Legal Policy Draft')
@section('page-title', 'Create Legal Policy Draft')

@section('content')
<div class="page-header">
    <div>
        <div class="page-eyebrow">{{ $policyType }}</div>
        <h1>Create <span>{{ $definition['display_name'] }}</span> Draft</h1>
        <p>
            New legal text remains editable until a separate publish-authority
            action locks the version.
        </p>
    </div>

    <a
        class="secondary-btn"
        href="{{ route('admin.legal.index') }}"
    >
        Back
    </a>
</div>

<section
    class="panel"
    style="padding:18px;margin-bottom:18px;"
>
    <strong>Do not paste secrets or private customer data.</strong>
    Policy wording should be reviewed for the applicable jurisdictions before
    publication.
</section>

<section
    class="panel"
    style="padding:22px;"
>
    <form
        method="POST"
        action="{{ route('admin.legal.store') }}"
    >
        @csrf

        <input
            type="hidden"
            name="policy_type"
            value="{{ $policyType }}"
        >

        <div class="form-group">
            <label>Title</label>
            <input
                type="text"
                name="title"
                maxlength="180"
                value="{{ old(
                    'title',
                    $suggestedTitle
                ) }}"
                required
            >
            @error('title')
                <small>{{ $message }}</small>
            @enderror
        </div>

        <div
            class="form-group"
            style="margin-top:18px;"
        >
            <label>Summary</label>
            <textarea
                name="summary"
                maxlength="500"
                rows="3"
            >{{ old('summary') }}</textarea>
            @error('summary')
                <small>{{ $message }}</small>
            @enderror
        </div>

        <div
            class="form-group"
            style="margin-top:18px;"
        >
            <label>Legal Policy Text</label>
            <textarea
                name="content"
                rows="24"
                maxlength="200000"
                required
            >{{ old('content') }}</textarea>
            <small>
                Stored and rendered as escaped plain text. HTML is not executed.
            </small>
            @error('content')
                <small>{{ $message }}</small>
            @enderror
        </div>

        <div style="margin-top:22px;">
            <button
                class="primary-btn"
                type="submit"
            >
                Save Draft
            </button>
        </div>
    </form>
</section>
@endsection
