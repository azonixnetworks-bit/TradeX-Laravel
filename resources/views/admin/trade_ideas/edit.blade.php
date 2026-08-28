{{-- TradeX Admin — ADMIN-05 REV3 Edit Draft Long Term Trade Idea --}}

@extends('admin.layouts.app')

@section('title', 'Edit Long Term Trade Idea | TradeX')
@section('page-title', 'Long Term Trade Ideas')

@push('styles')

<style>
    .ti-form{max-width:1100px}.ti-section{padding:22px;border-bottom:1px solid rgba(255,255,255,.06)}
    .ti-section:last-child{border-bottom:0}.ti-section h2{margin:0 0 6px;font-size:16px}.ti-section>p{margin:0 0 18px;font-size:11px;opacity:.55;line-height:1.6}
    .ti-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:15px}.ti-field.full{grid-column:1/-1}.ti-field label{display:block;margin-bottom:7px;font-size:10px;font-weight:800;letter-spacing:.04em}
    .ti-field input,.ti-field textarea,.ti-field select{width:100%;box-sizing:border-box;padding:11px 12px;border:1px solid rgba(255,255,255,.08);border-radius:10px;background:rgba(255,255,255,.025);color:inherit;font:inherit;font-size:12px}.ti-field textarea{min-height:120px;resize:vertical}
    .ti-hint{margin-top:6px;font-size:9px;opacity:.5;line-height:1.5}.ti-error{margin-top:6px;color:#ff8d8d;font-size:10px}
    .target-list{display:grid;gap:9px}.target-row{display:grid;grid-template-columns:62px minmax(0,1fr) 90px;gap:9px;align-items:center}.target-label{font-size:10px;font-weight:800;color:#ddb65e}
    .target-remove{padding:9px;border-radius:8px;border:1px solid rgba(255,91,91,.2);background:rgba(255,91,91,.05);color:#ff8585;cursor:pointer}.target-add{margin-top:10px;padding:9px 12px;border:1px solid rgba(218,178,88,.22);background:rgba(218,178,88,.06);color:#ddb65e;border-radius:9px;cursor:pointer;font:inherit;font-size:10px;font-weight:800}
    .ti-actions{display:flex;justify-content:flex-end;gap:10px;padding:20px 22px}.region-hidden{display:none}
    @media(max-width:720px){.ti-grid{grid-template-columns:1fr}.ti-field.full{grid-column:auto}.target-row{grid-template-columns:50px minmax(0,1fr) 72px}}
</style>

@endpush

@section('content')
<div class="page-header">
    <div>
        <div class="page-eyebrow">ADMIN-05 REV3 · LONG TERM DRAFT SETUP</div>
        <h1>Edit <span>Long Term Trade Idea</span></h1>
        <p>
            Optional Trade Structure, chart media and context can be changed
            while the Long Term Trade Idea remains DRAFT.
        </p>
    </div>

    <div>
        <a
            class="secondary-btn"
            href="{{ route('admin.trade_ideas.show', ['tradeIdea'=>$tradeIdea]) }}"
        >
            ← Back
        </a>
    </div>
</div>

<section class="panel ti-form">
    <form
        method="POST"
        enctype="multipart/form-data"
        action="{{ route('admin.trade_ideas.update', ['tradeIdea'=>$tradeIdea]) }}"
    >
        @csrf
        @method('PATCH')

        @include('admin.trade_ideas._form')

        <div class="ti-actions">
            <a
                class="secondary-btn"
                href="{{ route('admin.trade_ideas.show', ['tradeIdea'=>$tradeIdea]) }}"
            >
                Cancel
            </a>

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