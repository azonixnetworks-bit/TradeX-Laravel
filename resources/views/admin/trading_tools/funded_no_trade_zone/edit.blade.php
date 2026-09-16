@extends('admin.layouts.app')

@section('title', 'Edit Funded No Trade Rule | TradeX Administration')
@section('page-title', 'Edit Funded No Trade Rule')

@push('styles')
<link rel="stylesheet" href="{{ asset('admin/css/funded-account-advisor.css') }}">
@endpush

@section('content')
<div class="funded-advisor-page funded-advisor-editor-page">
    <div class="page-header funded-advisor-header">
        <div>
            <div class="page-eyebrow">ADMIN-31 · FUNDED NO TRADE ZONE</div>
            <h1>Edit <span>Company Rule</span></h1>
            <p>{{ $rule->partner?->name ?? 'Funded company' }} · {{ $rule->program_name ?: 'All programs' }} · {{ $rule->phase }}</p>
        </div>
        <a class="secondary-btn" href="{{ route('admin.funded_no_trade_zone.index') }}">Back to Rules</a>
    </div>

    @if($errors->any())
        <section class="panel funded-advisor-alert is-danger">
            <strong>Please correct the rule details.</strong>
            <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </section>
    @endif

    <form class="panel funded-advisor-partner-form" method="POST" action="{{ route('admin.funded_no_trade_zone.rules.update', $rule) }}">
        @csrf
        @method('PATCH')
        @include('admin.trading_tools.funded_no_trade_zone._form')
        <div class="funded-advisor-form-actions">
            <button class="primary-btn" type="submit">Save Rule</button>
            <a class="secondary-btn" href="{{ route('admin.funded_no_trade_zone.index') }}">Cancel</a>
        </div>
    </form>

    <section class="panel funded-advisor-danger-zone">
        <div>
            <strong>Archive Rule</strong>
            <p>Soft-delete this rule when it is no longer valid. Historical audit logs remain available.</p>
        </div>
        <form method="POST" action="{{ route('admin.funded_no_trade_zone.rules.destroy', $rule) }}" onsubmit="return confirm('Archive this funded no-trade rule?');">
            @csrf
            @method('DELETE')
            <button class="secondary-btn" type="submit">Archive Rule</button>
        </form>
    </section>
</div>
@endsection
