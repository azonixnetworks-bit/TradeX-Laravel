@extends('admin.layouts.app')

@section('title', 'Legal & Policy Management')
@section('page-title', 'Legal & Policy Management')

@section('content')
<div class="page-header">
    <div>
        <div class="page-eyebrow">VERSIONED LEGAL GOVERNANCE</div>
        <h1>Legal & <span>Policy Management</span></h1>
        <p>
            Draft, review, publish and preserve immutable legal policy versions.
        </p>
    </div>
</div>

<section
    class="panel"
    style="padding:18px;margin-bottom:18px;"
>
    <strong>Legal-content boundary:</strong>
    this module manages approved text and version history. It does not invent
    legal wording or replace jurisdiction-specific legal review.
</section>

<section
    class="panel"
    style="padding:18px;margin-bottom:18px;"
>
    <strong>Customer acceptance runtime:</strong>
    {{ $acceptanceRuntime }}.
    The current repository does not yet contain a customer legal-acceptance
    API/ledger, so ADMIN-17 does not falsely claim acceptance enforcement.
</section>

<div class="legal-card-grid">
@foreach($policyCards as $card)
    <section
        class="panel"
        style="padding:20px;"
    >
        <div class="panel-eyebrow">
            {{ $card['type'] }}
        </div>

        <h2 style="margin-top:8px;">
            {{ $card['display_name'] }}
        </h2>

        <p>
            Audience: {{ $card['audience'] }}
        </p>

        <table
            class="users-table compact-meta-table"
            style="margin-top:14px;"
        >
            <tbody>
                <tr>
                    <th>Current</th>
                    <td>
                        @if($card['current'])
                            v{{ $card['current']->version_number }}
                        @else
                            Not published
                        @endif
                    </td>
                </tr>

                <tr>
                    <th>Draft</th>
                    <td>
                        @if($card['draft'])
                            v{{ $card['draft']->version_number }}
                        @else
                            None
                        @endif
                    </td>
                </tr>

                <tr>
                    <th>Scheduled</th>
                    <td>
                        @if($card['scheduled'])
                            v{{ $card['scheduled']->version_number }}
                        @else
                            None
                        @endif
                    </td>
                </tr>

                <tr>
                    <th>Published History</th>
                    <td>
                        {{ $card['published_count'] }}
                    </td>
                </tr>
            </tbody>
        </table>

        <div
            style="
                display:flex;
                flex-wrap:wrap;
                gap:10px;
                margin-top:18px;
            "
        >
            @if($card['draft'])
                <a
                    class="secondary-btn"
                    href="{{ route(
                        'admin.legal.show',
                        $card['draft']
                    ) }}"
                >
                    Open Draft
                </a>
            @elseif($canManage)
                <a
                    class="primary-btn"
                    href="{{ route(
                        'admin.legal.create',
                        $card['type']
                    ) }}"
                >
                    New Draft
                </a>
            @endif

            @if($card['current'])
                <a
                    class="secondary-btn"
                    href="{{ route(
                        'admin.legal.show',
                        $card['current']
                    ) }}"
                >
                    Current Version
                </a>
            @endif

            @if($card['scheduled'])
                <a
                    class="secondary-btn"
                    href="{{ route(
                        'admin.legal.show',
                        $card['scheduled']
                    ) }}"
                >
                    Scheduled Version
                </a>
            @endif
        </div>
    </section>
@endforeach
</div>
@endsection
