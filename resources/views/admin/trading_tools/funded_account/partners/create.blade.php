@extends('admin.layouts.app')

@section('title', 'Add Funded Partner | TradeX Administration')
@section('page-title', 'Add Funded Partner')

@push('styles')
<link rel="stylesheet" href="{{ asset('admin/css/funded-account-advisor.css') }}">
@endpush

@section('content')
<div class="funded-advisor-page funded-advisor-editor-page">
    <div class="page-header funded-advisor-header">
        <div>
            <div class="page-eyebrow">FUNDED ACCOUNT PARTNERS</div>
            <h1>Add <span>Partner</span></h1>
            <p>Create a TradeX funded-account recommendation and affiliate offer.</p>
        </div>
        <a class="secondary-btn" href="{{ route('admin.funded_advisor.partners.index') }}">Back to Partners</a>
    </div>

    @if($errors->any())
        <section class="panel funded-advisor-alert is-danger"><strong>Please correct the partner details.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></section>
    @endif

    <form class="panel funded-advisor-partner-form" method="POST" action="{{ route('admin.funded_advisor.partners.store') }}" enctype="multipart/form-data">
        @csrf
        @include('admin.trading_tools.funded_account.partners._form')
        <div class="funded-advisor-form-actions">
            <button class="primary-btn" type="submit">Create Partner</button>
            <a class="secondary-btn" href="{{ route('admin.funded_advisor.partners.index') }}">Cancel</a>
        </div>
    </form>
</div>
@endsection
