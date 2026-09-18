@extends('admin.layouts.app')

@section('title', 'Add Funded No Trade Rule | TradeX Administration')
@section('page-title', 'Add Funded No Trade Rule')

@push('styles')
<link rel="stylesheet" href="{{ asset('admin/css/funded-account-advisor.css') }}">
@endpush

@section('content')
<div class="funded-advisor-page funded-advisor-editor-page">
    <div class="page-header funded-advisor-header">
        <div>
            <div class="page-eyebrow">ADMIN-31 · FUNDED NO TRADE ZONE</div>
            <h1>Add <span>Company Rule</span></h1>
            <p>Capture a verified funded-account news trading restriction for TradeX compliance calculations.</p>
        </div>
        <a class="secondary-btn" href="{{ route('admin.funded_no_trade_zone.index') }}">Back to Rules</a>
    </div>

    @if($errors->any())
        <section class="panel funded-advisor-alert is-danger">
            <strong>Please correct the rule details.</strong>
            <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </section>
    @endif

    <form class="panel funded-advisor-partner-form" method="POST" action="{{ route('admin.funded_no_trade_zone.rules.store') }}">
        @csrf
        @include('admin.trading_tools.funded_no_trade_zone._form')
        <div class="funded-advisor-form-actions">
            <button class="primary-btn" type="submit">Create Rule</button>
            <a class="secondary-btn" href="{{ route('admin.funded_no_trade_zone.index') }}">Cancel</a>
        </div>
    </form>
</div>
@endsection
