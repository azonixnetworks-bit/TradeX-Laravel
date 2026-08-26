@extends('admin.layouts.app')

@section('title', 'Edit Legal Policy Draft')
@section('page-title', 'Edit Legal Policy Draft')

@section('content')
<div class="page-header">
    <div>
        <div class="page-eyebrow">
            {{ $policy->policy_type }}
            · v{{ $policy->version_number }}
        </div>

        <h1>Edit <span>{{ $definition['display_name'] ?? $policy->title }}</span></h1>

        <p>
            Draft revision {{ $policy->revision_version }}.
            Once published, this version becomes immutable.
        </p>
    </div>

    <a
        class="secondary-btn"
        href="{{ route(
            'admin.legal.show',
            $policy
        ) }}"
    >
        Back
    </a>
</div>

<section
    class="panel"
    style="padding:22px;"
>
    <form
        method="POST"
        action="{{ route(
            'admin.legal.update',
            $policy
        ) }}"
    >
        @csrf
        @method('PATCH')

        <input
            type="hidden"
            name="revision_version"
            value="{{ $policy->revision_version }}"
        >

        <div class="form-group">
            <label>Title</label>
            <input
                type="text"
                name="title"
                maxlength="180"
                value="{{ old(
                    'title',
                    $policy->title
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
            >{{ old(
                'summary',
                $policy->summary
            ) }}</textarea>
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
            >{{ old(
                'content',
                $policy->content
            ) }}</textarea>
            @error('content')
                <small>{{ $message }}</small>
            @enderror
        </div>

        <div style="margin-top:22px;">
            <button
                class="primary-btn"
                type="submit"
            >
                Save Draft Revision
            </button>
        </div>
    </form>
</section>
@endsection
