<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="TradeX OS brings education, market intelligence and risk-aware trading tools into one focused workspace.">
    <meta name="theme-color" content="#07111f">
    <title>TradeX OS | Learn. Plan. Trade with clarity.</title>
    <link rel="icon" type="image/png" href="{{ asset('admin/brand/tradex-favicon.png') }}">
    <link rel="stylesheet" href="{{ asset('site/css/tradex-public.css') }}">
</head>
<body>
    <div class="site-glow site-glow--one" aria-hidden="true"></div>
    <div class="site-glow site-glow--two" aria-hidden="true"></div>

    <header class="site-header">
        <a class="brand" href="#top" aria-label="TradeX OS home">
            <img src="{{ asset('admin/brand/tradex-logo.png') }}" alt="TradeX OS logo">
            <span>TradeX <em>OS</em></span>
        </a>
        <nav class="site-nav" aria-label="Main navigation">
            <a href="#about">About</a>
            <a href="#platform">Platform</a>
            <a href="#principles">Principles</a>
        </nav>
        <a class="header-link" href="{{ route('admin.login') }}">Team sign in <span aria-hidden="true">↗</span></a>
    </header>

    <main id="top">
        <section class="hero section-shell">
            <div class="hero-copy">
                <p class="eyebrow"><span></span> A focused trading operating system</p>
                <h1>Build a calmer,<br><i>more deliberate</i><br>trading process.</h1>
                <p class="hero-lede">TradeX OS connects structured education, market intelligence and practical planning tools in one considered workspace.</p>
                <div class="hero-actions">
                    <a class="button button--primary" href="#platform">Explore the platform <span aria-hidden="true">→</span></a>
                    <a class="button button--quiet" href="#about">How we think</a>
                </div>
                <p class="risk-note">Built for learning, planning and informed decision-making. Trading involves risk.</p>
            </div>
            <div class="hero-visual" aria-label="TradeX OS platform preview">
                <div class="orbit orbit--large"></div>
                <div class="orbit orbit--small"></div>
                <div class="visual-card visual-card--main">
                    <div class="card-top"><span class="status-dot"></span><span>MARKET PULSE</span><small>LIVE VIEW</small></div>
                    <div class="chart-bars" aria-hidden="true">
                        <i style="--h:34%"></i><i style="--h:48%"></i><i style="--h:39%"></i><i style="--h:64%"></i><i style="--h:56%"></i><i style="--h:78%"></i><i style="--h:60%"></i><i style="--h:89%"></i><i style="--h:73%"></i><i style="--h:96%"></i><i style="--h:84%"></i>
                    </div>
                    <div class="card-footer"><span>Observe</span><span>Plan</span><span>Review</span></div>
                </div>
                <div class="visual-card visual-card--floating">
                    <span class="mini-label">TODAY'S FOCUS</span>
                    <strong>Risk first.<br>Process always.</strong>
                    <div class="mini-line"><b></b><b></b><b></b></div>
                </div>
                <div class="visual-badge"><span>01</span><small>ONE<br>WORKSPACE</small></div>
            </div>
        </section>

        <section id="about" class="intro section-shell">
            <p class="eyebrow"><span></span> What TradeX OS is built for</p>
            <div class="intro-grid">
                <h2>Clear systems beat<br>constant noise.</h2>
                <div>
                    <p>TradeX OS is a digital workspace for people who want to approach markets with greater structure. It is designed to make the learning, preparation and review cycle easier to follow.</p>
                    <p>We believe useful tools should help you slow down, see the relevant information and keep your process consistent.</p>
                </div>
            </div>
        </section>

        <section id="platform" class="platform section-shell">
            <div class="section-heading">
                <p class="eyebrow"><span></span> Inside the platform</p>
                <h2>One connected<br>operating system.</h2>
            </div>
            <div class="feature-grid">
                <article class="feature-card feature-card--wide">
                    <span class="feature-number">01</span>
                    <div class="feature-icon feature-icon--learn">↗</div>
                    <h3>Structured learning</h3>
                    <p>Organised education content gives every lesson a place in a clear learning path.</p>
                    <div class="feature-lines" aria-hidden="true"><i></i><i></i><i></i><i></i></div>
                </article>
                <article class="feature-card">
                    <span class="feature-number">02</span>
                    <div class="feature-icon">⌁</div>
                    <h3>Market intelligence</h3>
                    <p>Screeners and market views help you explore relevant instruments with less clutter.</p>
                </article>
                <article class="feature-card">
                    <span class="feature-number">03</span>
                    <div class="feature-icon">◇</div>
                    <h3>Risk-aware tools</h3>
                    <p>Use practical planning tools to think through position risk before taking action.</p>
                </article>
            </div>
        </section>

        <section id="principles" class="principles section-shell">
            <div class="principles-panel">
                <div>
                    <p class="eyebrow"><span></span> Our approach</p>
                    <h2>Better decisions begin with a better process.</h2>
                </div>
                <div class="principle-list">
                    <article><b>01</b><div><h3>Clarity over complexity</h3><p>Information should be organised, not overwhelming.</p></div></article>
                    <article><b>02</b><div><h3>Process over prediction</h3><p>Good habits matter more than short-term market noise.</p></div></article>
                    <article><b>03</b><div><h3>Risk before reward</h3><p>Every plan should begin with understanding what can go wrong.</p></div></article>
                </div>
            </div>
        </section>

        <section class="closing section-shell">
            <p class="eyebrow"><span></span> TradeX OS</p>
            <h2>Learn with intent.<br><i>Plan with clarity.</i></h2>
            <a class="button button--primary" href="{{ route('admin.login') }}">Access the platform <span aria-hidden="true">→</span></a>
        </section>
    </main>

    <footer class="site-footer section-shell">
        <a class="brand" href="#top"><img src="{{ asset('admin/brand/tradex-logo.png') }}" alt=""><span>TradeX <em>OS</em></span></a>
        <p>Education, market intelligence and planning tools for a more structured trading process.</p>
        <p class="copyright">© {{ now()->year }} TradeX OS. All rights reserved.</p>
    </footer>
</body>
</html>
