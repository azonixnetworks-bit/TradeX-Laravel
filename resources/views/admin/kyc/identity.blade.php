@extends('admin.layouts.app')

@section('title', 'Sensitive KYC Identity')
@section('page-title', 'Sensitive KYC Identity')

@section('content')
<div class="page-header">
    <div>
        <div class="page-eyebrow">AUDITED SENSITIVE ACCESS</div>
        <h1>Full Identity <span>Data</span></h1>
        <p>Access to this page has been recorded in the KYC audit log.</p>
    </div>

    <a class="secondary-btn" href="{{ route('admin.kyc.show', $submission->getKey()) }}">Back to Review</a>
</div>

<section class="panel" style="padding:22px;margin-bottom:18px;">
    <p><strong>Submission:</strong> {{ $submission->uuid }}</p>
    <p><strong>User:</strong> {{ $submission->user?->name ?? '—' }} ({{ $submission->user?->email ?? '—' }})</p>
    <p>Do not copy this identity information into tickets, chat messages, screenshots, audit notes or external systems unless an approved compliance process requires it.</p>
</section>

<section class="panel users-panel">
    <div class="panel-header">
        <div>
            <div class="panel-eyebrow">DECRYPTED IDENTITY PAYLOAD</div>
            <h2>Submitted Fields</h2>
        </div>
    </div>

    <div class="users-table-wrap">
        <table class="users-table">
            <tbody>
            @foreach($identity_data as $field => $value)
                <tr>
                    <th>{{ ucwords(str_replace('_', ' ', $field)) }}</th>
                    <td><strong>{{ $value }}</strong></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</section>
@endsection
