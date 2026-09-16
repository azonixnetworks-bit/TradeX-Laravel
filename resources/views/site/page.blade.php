@extends('site.layout')

@section('content')
<section class="page-hero">
    <div class="container narrow">
        <nav class="breadcrumbs" aria-label="Breadcrumb">
            <a href="{{ route('site.home') }}">TradeX</a><span>/</span><span aria-current="page">{{ $page['eyebrow'] }}</span>
        </nav>
        <p class="eyebrow">{{ $page['eyebrow'] }}</p>
        <h1>{{ $page['h1'] }}</h1>
        <p class="hero-copy">{{ $page['intro'] }}</p>
    </div>
</section>

<section class="section">
    <div class="container narrow content-stack">
        @foreach($page['sections'] as $section)
            <article class="content-block">
                <h2>{{ $section['heading'] }}</h2>
                <p>{{ $section['body'] }}</p>
                @if(!empty($section['items']))
                    <ul class="check-list">
                        @foreach($section['items'] as $item)
                            <li>{{ $item }}</li>
                        @endforeach
                    </ul>
                @endif
            </article>
        @endforeach
    </div>
</section>

@if(!empty($page['faq']))
<section class="section section-alt">
    <div class="container narrow">
        <div class="section-heading">
            <p class="eyebrow">FAQ</p>
            <h2>Questions about {{ $page['eyebrow'] }}.</h2>
        </div>
        <div class="faq-list">
            @foreach($page['faq'] as $faq)
                <details>
                    <summary>{{ $faq['question'] }}</summary>
                    <p>{{ $faq['answer'] }}</p>
                </details>
            @endforeach
        </div>
    </div>
</section>
@endif

<section class="cta-section">
    <div class="container narrow cta-card">
        <p class="eyebrow">Explore TradeX</p>
        <h2>Connect this workflow with the rest of the trader operating system.</h2>
        <div class="hero-actions">
            <a class="button button-primary" href="{{ route('site.features') }}">All Features</a>
            <a class="button button-secondary" href="{{ route('site.trading-tools') }}">Trading Tools</a>
        </div>
    </div>
</section>
@endsection
