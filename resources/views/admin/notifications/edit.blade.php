@extends('admin.layouts.app')

@section('title', 'Edit Notification Draft')
@section('page-title', 'Edit Notification Draft')

@section('content')
<div class="page-header">
    <div>
        <div class="page-eyebrow">DRAFT {{ $notificationMessage->uuid }}</div>
        <h1>Edit <span>Notification</span></h1>
        <p>
            Published or scheduled notifications are immutable and cannot reach this edit workflow.
        </p>
    </div>

    <a
        class="secondary-btn"
        href="{{ route(
            'admin.notifications.show',
            $notificationMessage
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
            'admin.notifications.update',
            $notificationMessage
        ) }}"
    >
        @csrf
        @method('PATCH')

        @include(
            'admin.notifications.partials.form'
        )

        <div style="margin-top:22px;">
            <button
                class="primary-btn"
                type="submit"
            >
                Save Draft Changes
            </button>
        </div>
    </form>
</section>
@endsection
