@extends('site.layout')

@section('content')
<section class="hero">
    <div class="container hero-grid">
        <div>
            <p class="eyebrow">{{ $page['eyebrow'] }}</p>
            <h1>{{ $page['h1'] }}</h1>
            <p class="hero-copy">{{ $page['intro'] }}</p>
            <div class="hero-actions">
                <a class="button button-primary" href="{{ route('site.features') }}">Explore TradeX Features</a>
                <a class="button button-secondary" href="{{ route('site.trading-tools') }}">View Trading Tools</a>
            </div>
            <p class="microcopy">Built around trader preparation, risk discipline, market intelligence, and continuous learning.</p>
        </div>

        <aside class="hero-panel" aria-label="TradeX product pillars">
            <div class="status-pill"><span></span> TradeX Platform</div>
            <h2>One workflow. Four core pillars.</h2>
            <div class="metric-grid">
                <div><strong>01</strong><span>Market Intelligence</span></div>
                <div><strong>02</strong><span>Risk & Planning</span></div>
                <div><strong>03</strong><span>Trading Education</span></div>
                <div><strong>04</strong><span>Daily Discipline</span></div>
            </div>
        </aside>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="section-heading">
            <p class="eyebrow">Why TradeX</p>
            <h2>Designed around what traders repeatedly need to do.</h2>
        </div>
        <div class="card-grid">
            @foreach($page['sections'] as $section)
                <article class="feature-card">
                    <h3>{{ $section['heading'] }}</h3>
                    <p>{{ $section['body'] }}</p>
                    <ul>
                        @foreach($section['items'] as $item)
                            <li>{{ $item }}</li>
                        @endforeach
                    </ul>
                </article>
            @endforeach
        </div>
    </div>
</section>

<section class="section section-alt">
    <div class="container split">
        <div>
            <p class="eyebrow">Economic News Intelligence</p>
            <h2>From calendar event to affected pair and directional context.</h2>
            <p>TradeX economic news workflows connect event impact with affected instruments, bullish/bearish/neutral bias, confidence, volatility, and the written reason behind the analysis.</p>
            <a class="text-link" href="{{ route('site.economic-calendar') }}">Explore economic calendar intelligence →</a>
        </div>
        <div class="signal-stack" aria-label="Example intelligence fields">
            <div><span>Impact</span><strong>High / Medium</strong></div>
            <div><span>Direction</span><strong>Bullish / Bearish / Neutral</strong></div>
            <div><span>Context</span><strong>Confidence + Volatility</strong></div>
            <div><span>Explainability</span><strong>Reason / Analysis</strong></div>
        </div>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="section-heading">
            <p class="eyebrow">Common Questions</p>
            <h2>What traders should know about TradeX.</h2>
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

<section class="cta-section">
    <div class="container cta-card">
        <p class="eyebrow">TradeX</p>
        <h2>The OS for Every Trader.</h2>
        <p>Explore the connected workflows behind market intelligence, risk planning, education, and trading discipline.</p>
        <a class="button button-primary" href="{{ route('site.features') }}">See the Platform</a>
    </div>
</section>
@endsection
