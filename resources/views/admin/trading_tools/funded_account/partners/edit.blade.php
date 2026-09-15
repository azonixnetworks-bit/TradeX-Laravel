@extends('admin.layouts.app')

@section('title', 'Edit Funded Partner | TradeX Administration')
@section('page-title', 'Edit Funded Partner')

@push('styles')
<link rel="stylesheet" href="{{ asset('admin/css/funded-account-advisor.css') }}">
@endpush

@section('content')
<div class="funded-advisor-page funded-advisor-editor-page">
    <div class="page-header funded-advisor-header">
        <div>
            <div class="page-eyebrow">FUNDED ACCOUNT PARTNERS</div>
            <h1>Edit <span>{{ $partner->name }}</span></h1>
            <p>Update the referral offer used in future Funded Account Advisor reports.</p>
        </div>
        <a class="secondary-btn" href="{{ route('admin.funded_advisor.partners.index') }}">Back to Partners</a>
    </div>

    @if($errors->any())
        <section class="panel funded-advisor-alert is-danger"><strong>Please correct the partner details.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></section>
    @endif

    <form class="panel funded-advisor-partner-form" method="POST" action="{{ route('admin.funded_advisor.partners.update', $partner) }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        @include('admin.trading_tools.funded_account.partners._form', ['partner' => $partner])
        <div class="funded-advisor-form-actions">
            <button class="primary-btn" type="submit">Save Partner</button>
            <a class="secondary-btn" href="{{ route('admin.funded_advisor.partners.index') }}">Cancel</a>
        </div>
    </form>

    <form class="panel funded-advisor-danger-zone" method="POST" action="{{ route('admin.funded_advisor.partners.destroy', $partner) }}" onsubmit="return confirm('Delete this partner? Historical reports keep their saved affiliate snapshots.');">
        @csrf
        @method('DELETE')
        <div><strong>Delete Partner</strong><p>Historical advisor reports are preserved with their saved partner-link snapshots.</p></div>
        <button class="secondary-btn" type="submit">Delete</button>
    </form>
</div>
@endsection
