@extends('admin.layouts.app')

@section('title', 'Affiliate Stages')
@section('page-title', 'Affiliate Stages')

@section('content')
<div class="page-header">
    <div>
        <div class="page-eyebrow">COMMERCIAL TABLE</div>
        <h1>Affiliate <span>Stages</span></h1>
        <p>Progressive direct-sale rates. Stage bands, codes, milestone counts and order are structurally locked; updates apply only to future sales.</p>
    </div>
    <a class="secondary-btn" href="{{ route('admin.affiliates.index') }}">Back to Affiliates</a>
</div>

@if(session('success'))<section class="panel" style="padding:14px 18px;margin-bottom:18px;">{{ session('success') }}</section>@endif
@if($errors->any())<section class="panel" style="padding:14px 18px;margin-bottom:18px;"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></section>@endif

@foreach($stages as $stage)
<section class="panel" style="padding:22px;margin-bottom:18px;">
    <div class="panel-header">
        <div><div class="panel-eyebrow">{{ $stage->code }}</div><h2>{{ $stage->name }}</h2></div>
        <div class="panel-count">Sales {{ number_format($stage->min_qualified_sales) }} – {{ $stage->max_qualified_sales ? number_format($stage->max_qualified_sales) : '∞' }}</div>
    </div>

    <p>
        Milestone: {{ $stage->milestone_sales ? number_format($stage->milestone_sales) . ' sales' : '—' }} ·
        Current rate: <strong>{{ number_format($stage->standard_commission_bps / 100, 2) }}%</strong> ·
        Gift: <strong>${{ number_format($stage->milestone_gift_cents / 100, 2) }}</strong> ·
        Cumulative gifts: ${{ number_format($stage->cumulative_gift_cents / 100, 2) }}
    </p>

    @if($canManage)
    <form method="POST" action="{{ route('admin.affiliates.stages.update', $stage) }}" style="display:grid;grid-template-columns:1fr 180px 180px;gap:12px;align-items:end;">
        @csrf
        @method('PATCH')
        <div><label>Stage name</label><input type="text" name="name" maxlength="80" value="{{ old('name', $stage->name) }}" required></div>
        <div><label>Standard commission %</label><input type="number" step="0.01" min="0" max="20" name="standard_commission_percent" value="{{ old('standard_commission_percent', number_format($stage->standard_commission_bps / 100, 2, '.', '')) }}" required></div>
        <div><label>Milestone gift USD</label><input type="number" step="0.01" min="0" name="milestone_gift_usd" value="{{ old('milestone_gift_usd', number_format($stage->milestone_gift_cents / 100, 2, '.', '')) }}" required></div>
        <div style="grid-column:1 / -1;"><label>Commercial rule</label><textarea name="commercial_rule" maxlength="500" required>{{ old('commercial_rule', $stage->commercial_rule) }}</textarea></div>
        <div style="grid-column:1 / -1;"><button class="primary-btn" type="submit">Save Commercial Settings</button></div>
    </form>
    @else
        <p>{{ $stage->commercial_rule }}</p>
    @endif
</section>
@endforeach
@endsection
