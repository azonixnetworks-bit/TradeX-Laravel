@extends('admin.layouts.app')

@section('title', 'Forex Billing Calendar')
@section('page-title', 'Forex Billing Calendar')

@section('content')
<div class="page-header">
    <div>
        <div class="page-eyebrow">
            COPY TRADING · FOREX OFFICIAL CALENDAR
        </div>
        <h1>Billing <span>Calendar</span></h1>
        <p>
            Forex proration requires one explicit official record for every
            calendar date in the billing month. TradeX does not guess weekends
            or broker holidays.
        </p>
    </div>
</div>

@if(session('success'))
    <div class="admin-form-callout" style="margin-bottom:18px;">
        <div class="admin-form-callout-icon">✓</div>
        <div><strong>{{ session('success') }}</strong></div>
    </div>
@endif

@if($errors->any())
    <div class="admin-form-callout" style="margin-bottom:18px;">
        <div class="admin-form-callout-icon">!</div>
        <div>{{ $errors->first() }}</div>
    </div>
@endif

@if($canManage)
<form
    method="POST"
    action="{{ route('admin.copy_trading.billing.calendar.store') }}"
    class="panel admin-form-panel"
    style="margin-bottom:18px;"
>
    @csrf

    <div class="form-grid">
        <div class="form-group">
            <label>Date</label>
            <input
                type="date"
                name="calendar_date"
                value="{{ old('calendar_date') }}"
                required
            >
        </div>

        <div class="form-group">
            <label>Official Trading Day?</label>
            <select name="is_billable_day" required>
                <option value="1">Yes</option>
                <option value="0">No</option>
            </select>
        </div>

        <div class="form-group">
            <label>Source</label>
            <select name="source" required>
                <option value="ADMIN_MANUAL">ADMIN_MANUAL</option>
                <option value="BROKER_API">BROKER_API</option>
                <option value="SYSTEM_IMPORT">SYSTEM_IMPORT</option>
            </select>
        </div>

        <div class="form-group">
            <label>Source Reference</label>
            <input
                type="text"
                name="source_reference"
                maxlength="191"
                value="{{ old('source_reference') }}"
            >
        </div>
    </div>

    <div class="admin-form-actions">
        <button class="primary-btn" type="submit">
            Save Official Day
        </button>
    </div>
</form>
@endif

<section class="panel users-panel">
    <div class="panel-header">
        <div>
            <div class="panel-eyebrow">OFFICIAL DAY REGISTER</div>
            <h2>Forex Calendar Evidence</h2>
        </div>
    </div>

    <div style="overflow-x:auto;">
        <table class="users-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Trading Day</th>
                    <th>Source</th>
                    <th>Locked</th>
                </tr>
            </thead>
            <tbody>
            @forelse($days as $day)
                <tr>
                    <td>
                        {{ $day->calendar_date?->format('Y-m-d') }}
                    </td>
                    <td>
                        {{ $day->is_billable_day ? 'YES' : 'NO' }}
                    </td>
                    <td>{{ $day->source }}</td>
                    <td>
                        {{ $day->locked_at?->format('Y-m-d H:i') ?? 'NO' }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td
                        colspan="4"
                        style="text-align:center;padding:24px;"
                    >
                        No official calendar rows yet.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div style="padding:16px 20px;">
        {{ $days->links() }}
    </div>
</section>
@endsection
