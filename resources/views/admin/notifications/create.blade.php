@extends('admin.layouts.app')

@section('title', 'Create Notification')
@section('page-title', 'Create Notification')

@section('content')
<div class="page-header">
    <div>
        <div class="page-eyebrow">IN-APP BROADCAST</div>
        <h1>Create <span>Notification Draft</span></h1>
        <p>
            Draft first. Publication is a separate permission-gated action.
        </p>
    </div>

    <a
        class="secondary-btn"
        href="{{ route('admin.notifications.index') }}"
    >
        Back
    </a>
</div>

<section
    class="panel"
    style="padding:18px;margin-bottom:18px;"
>
    <strong>Delivery boundary:</strong>
    ADMIN-15 creates TradeX in-app notification inbox receipts only.
    It does not send FCM push, email, SMS, Telegram or WhatsApp.
</section>

<section
    class="panel"
    style="padding:22px;"
>
    <form
        method="POST"
        action="{{ route('admin.notifications.store') }}"
    >
        @csrf

        @include(
            'admin.notifications.partials.form'
        )

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
