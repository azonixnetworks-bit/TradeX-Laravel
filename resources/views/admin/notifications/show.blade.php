@extends('admin.layouts.app')

@section('title', 'Notification Broadcast')
@section('page-title', 'Notification Broadcast')

@section('content')
<div class="page-header">
    <div>
        <div class="page-eyebrow">{{ $status }} · {{ $notificationMessage->uuid }}</div>
        <h1>{{ $notificationMessage->title }}</h1>
        <p>
            {{ $notificationMessage->category }}
            · {{ $notificationMessage->priority }}
            · {{ $notificationMessage->audience_access_level }}
        </p>
    </div>

    <div style="display:flex;gap:10px;flex-wrap:wrap;">
        @if($isDraft && $canCreate)
            <a
                class="secondary-btn"
                href="{{ route(
                    'admin.notifications.edit',
                    $notificationMessage
                ) }}"
            >
                Edit Draft
            </a>
        @endif

        <a
            class="secondary-btn"
            href="{{ route('admin.notifications.index') }}"
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
        <strong>{{ $status }}</strong>
    </div>

    <div class="user-stat-card">
        <span>Recipient Receipts</span>
        <strong>{{ number_format($recipientCount) }}</strong>
    </div>

    <div class="user-stat-card">
        <span>Read</span>
        <strong>{{ number_format($readCount) }}</strong>
    </div>

    <div class="user-stat-card">
        <span>Unread</span>
        <strong>{{ number_format($unreadCount) }}</strong>
    </div>
</div>

<section
    class="panel"
    style="padding:22px;margin-bottom:18px;"
>
    <h2>Message</h2>

    <div style="margin-top:14px;">
        {!! nl2br(e($notificationMessage->body)) !!}
    </div>
</section>

<section
    class="panel users-panel"
    style="margin-bottom:18px;"
>
    <div class="panel-header">
        <div>
            <div class="panel-eyebrow">SAFE METADATA</div>
            <h2>Broadcast Contract</h2>
        </div>
    </div>

    <div class="users-table-wrap">
        <table class="users-table">
            <tbody>
                <tr>
                    <th>Audience</th>
                    <td>{{ $notificationMessage->audience_access_level }}</td>
                </tr>
                <tr>
                    <th>Category</th>
                    <td>{{ $notificationMessage->category }}</td>
                </tr>
                <tr>
                    <th>Priority</th>
                    <td>{{ $notificationMessage->priority }}</td>
                </tr>
                <tr>
                    <th>Action</th>
                    <td>{{ $notificationMessage->action_type }}</td>
                </tr>
                <tr>
                    <th>Action Resource UUID</th>
                    <td>{{ $notificationMessage->action_resource_uuid ?: '—' }}</td>
                </tr>
                <tr>
                    <th>Created By</th>
                    <td>{{ $notificationMessage->createdByAdmin?->name ?? 'System / legacy' }}</td>
                </tr>
                <tr>
                    <th>Published / Scheduled</th>
                    <td>{{ $notificationMessage->published_at?->format('Y-m-d H:i:s') ?? 'Not published' }}</td>
                </tr>
                <tr>
                    <th>Expires</th>
                    <td>{{ $notificationMessage->expires_at?->format('Y-m-d H:i:s') ?? 'No expiry' }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</section>

@if($isDraft && $canSend)
<section
    class="panel"
    style="padding:22px;"
>
    <h2>Publish / Schedule In-App Broadcast</h2>

    <p>
        This creates a recipient receipt snapshot for currently ACTIVE users
        matching {{ $notificationMessage->audience_access_level }}.
        Maximum synchronous recipient safety limit:
        {{ number_format($maxRecipients) }}.
    </p>

    <p>
        The existing mobile notification API still re-checks the user's current
        FREE/PREMIUM access eligibility when the inbox is read.
    </p>

    <form
        method="POST"
        action="{{ route(
            'admin.notifications.send',
            $notificationMessage
        ) }}"
    >
        @csrf

        <div class="form-group">
            <label>Publish At</label>
            <input
                type="datetime-local"
                name="publish_at"
                value="{{ old('publish_at') }}"
            >
            <small>
                Leave blank for immediate publication. Future time schedules visibility without needing an arbitrary URL or external push provider.
            </small>
            @error('publish_at')
                <small>{{ $message }}</small>
            @enderror
        </div>

        <div style="margin-top:20px;">
            <button
                class="primary-btn"
                type="submit"
            >
                Publish / Schedule
            </button>
        </div>
    </form>
</section>
@endif

@if(!$isDraft)
<section
    class="panel"
    style="padding:18px;"
>
    <strong>Immutable dispatch:</strong>
    once recipient receipts are created, ADMIN-15 provides no edit, delete,
    recall or re-send mutation for this message.
</section>
@endif
@endsection
