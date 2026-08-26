@extends('admin.layouts.app')

@section('title', 'Notifications')
@section('page-title', 'Notifications')

@section('content')
<div class="page-header">
    <div>
        <div class="page-eyebrow">IN-APP COMMUNICATION</div>
        <h1>Notifications & <span>Broadcasts</span></h1>
        <p>
            Create controlled TradeX in-app notifications for ALL, FREE or PREMIUM audiences.
        </p>
    </div>

    @if($canCreate)
        <a
            class="primary-btn"
            href="{{ route('admin.notifications.create') }}"
        >
            Create Draft
        </a>
    @endif
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
        <span>Total</span>
        <strong>{{ number_format($stats['total']) }}</strong>
    </div>

    <div class="user-stat-card">
        <span>Drafts</span>
        <strong>{{ number_format($stats['drafts']) }}</strong>
    </div>

    <div class="user-stat-card">
        <span>Scheduled</span>
        <strong>{{ number_format($stats['scheduled']) }}</strong>
    </div>

    <div class="user-stat-card">
        <span>Live</span>
        <strong>{{ number_format($stats['live']) }}</strong>
    </div>

    <div class="user-stat-card">
        <span>Expired</span>
        <strong>{{ number_format($stats['expired']) }}</strong>
    </div>
</div>

<section class="panel user-filter-panel">
    <form
        class="user-filter-form"
        method="GET"
        action="{{ route('admin.notifications.index') }}"
    >
        <div class="filter-item">
            <label>Search</label>
            <input
                type="text"
                name="search"
                maxlength="180"
                value="{{ request('search') }}"
                placeholder="Title or UUID"
            >
        </div>

        <div class="filter-item">
            <label>Status</label>
            <select name="status">
                <option value="">All</option>
                @foreach($statuses as $status)
                    <option
                        value="{{ $status }}"
                        @selected(request('status') === $status)
                    >
                        {{ $status }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="filter-item">
            <label>Audience</label>
            <select name="audience">
                <option value="">All</option>
                @foreach($audiences as $audience)
                    <option
                        value="{{ $audience }}"
                        @selected(request('audience') === $audience)
                    >
                        {{ $audience }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="filter-item">
            <label>Category</label>
            <select name="category">
                <option value="">All</option>
                @foreach($categories as $category)
                    <option
                        value="{{ $category }}"
                        @selected(request('category') === $category)
                    >
                        {{ $category }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="filter-item">
            <label>Priority</label>
            <select name="priority">
                <option value="">All</option>
                @foreach($priorities as $priority)
                    <option
                        value="{{ $priority }}"
                        @selected(request('priority') === $priority)
                    >
                        {{ $priority }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="filter-item">
            <label>Per Page</label>
            <select name="per_page">
                @foreach([10,20,50,100] as $size)
                    <option
                        value="{{ $size }}"
                        @selected(
                            (int) request('per_page', 20)
                            === $size
                        )
                    >
                        {{ $size }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="filter-actions">
            <button
                class="primary-btn"
                type="submit"
            >
                Apply
            </button>

            <a
                class="secondary-btn"
                href="{{ route('admin.notifications.index') }}"
            >
                Reset
            </a>
        </div>
    </form>
</section>

<section class="panel users-panel">
    <div class="panel-header">
        <div>
            <div class="panel-eyebrow">MESSAGE INVENTORY</div>
            <h2>Notification Messages</h2>
        </div>

        <div class="panel-count">
            {{ number_format($messages->total()) }} records
        </div>
    </div>

    <div class="users-table-wrap">
        <table class="users-table">
            <thead>
                <tr>
                    <th>Message</th>
                    <th>Audience</th>
                    <th>Category</th>
                    <th>Priority</th>
                    <th>Status</th>
                    <th>Recipients</th>
                    <th>Read</th>
                    <th>Publish</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse($messages as $message)
                @php
                    $status = app(
                        \App\Services\Admin\Notifications\AdminNotificationService::class
                    )->status($message);
                @endphp

                <tr>
                    <td>
                        <strong>{{ $message->title }}</strong><br>
                        <small>{{ $message->uuid }}</small>
                    </td>
                    <td>{{ $message->audience_access_level }}</td>
                    <td>{{ $message->category }}</td>
                    <td>{{ $message->priority }}</td>
                    <td><strong>{{ $status }}</strong></td>
                    <td>{{ number_format($message->receipts_count) }}</td>
                    <td>{{ number_format($message->read_receipts_count) }}</td>
                    <td>
                        {{ $message->published_at?->format('Y-m-d H:i') ?? 'Draft' }}
                    </td>
                    <td>
                        <a
                            class="secondary-btn"
                            href="{{ route(
                                'admin.notifications.show',
                                $message
                            ) }}"
                        >
                            Inspect
                        </a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9">
                        No notifications match the filters.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div style="padding-top:18px;">
        {{ $messages->links() }}
    </div>
</section>
@endsection
