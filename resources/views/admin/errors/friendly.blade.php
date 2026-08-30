<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#03070d">
    <meta name="robots" content="noindex,nofollow,noarchive">

    <title>{{ $title }} · TradeX Administration</title>

    <link
        rel="icon"
        type="image/png"
        href="{{ asset('admin/brand/tradex-favicon.png') }}"
    >

    <style>
        :root {
            --bg: #030811;
            --surface: #071625;
            --surface-2: #0a1b2d;
            --line: rgba(255,255,255,.08);
            --gold: #d9a83c;
            --gold-light: #f1cf73;
            --text: #f4f7fb;
            --muted: #91a4b8;
            --danger: #ff6b78;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 28px;
            color: var(--text);
            font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background:
                radial-gradient(circle at 15% 15%, rgba(26,121,255,.13), transparent 30%),
                radial-gradient(circle at 85% 85%, rgba(217,168,60,.10), transparent 30%),
                var(--bg);
        }

        .error-shell {
            width: min(760px, 100%);
            overflow: hidden;
            border: 1px solid var(--line);
            border-radius: 28px;
            background: linear-gradient(145deg, rgba(8,25,42,.98), rgba(3,10,18,.98));
            box-shadow: 0 40px 110px rgba(0,0,0,.46);
        }

        .error-brand {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 24px 28px;
            border-bottom: 1px solid var(--line);
        }

        .error-brand img {
            width: 92px;
            height: 64px;
            object-fit: contain;
            object-position: left center;
        }

        .brand-copy strong,
        .brand-copy span { display: block; }

        .brand-copy strong {
            letter-spacing: 1.8px;
            font-size: 15px;
        }

        .brand-copy span {
            margin-top: 4px;
            color: #60758a;
            font-size: 9px;
            font-weight: 800;
            letter-spacing: 1.5px;
        }

        .error-body { padding: 44px 46px 46px; }

        .status {
            color: var(--gold);
            font-size: 11px;
            font-weight: 850;
            letter-spacing: 2.4px;
            text-transform: uppercase;
        }

        h1 {
            margin: 12px 0 12px;
            font-size: clamp(34px, 6vw, 52px);
            line-height: 1.04;
            letter-spacing: -1.5px;
        }

        p {
            margin: 0;
            max-width: 620px;
            color: var(--muted);
            font-size: 15px;
            line-height: 1.75;
        }

        .reference {
            margin-top: 22px;
            padding: 13px 15px;
            border: 1px solid rgba(217,168,60,.18);
            border-radius: 12px;
            background: rgba(217,168,60,.06);
            color: #c9d4df;
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
            font-size: 12px;
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 30px;
        }

        .btn {
            min-height: 48px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0 19px;
            border-radius: 12px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 800;
        }

        .btn-primary {
            color: #10151b;
            background: linear-gradient(135deg, #c79535, var(--gold-light));
        }

        .btn-secondary {
            color: #d5dfeb;
            border: 1px solid var(--line);
            background: var(--surface-2);
        }

        .security-note {
            margin-top: 26px;
            color: #60758a;
            font-size: 11px;
            line-height: 1.6;
        }

        @media (max-width: 600px) {
            body { padding: 14px; }
            .error-body { padding: 32px 24px 34px; }
            .error-brand { padding: 20px 22px; }
            .actions { flex-direction: column; }
            .btn { width: 100%; }
        }
    </style>
</head>
<body data-tradex-admin-friendly-error="v1">
<section class="error-shell" role="alert" aria-live="assertive">
    <div class="error-brand">
        <img
            src="{{ asset('admin/brand/tradex-logo.webp') }}"
            alt="TradeX OS"
        >
        <div class="brand-copy">
            <strong>TRADEX</strong>
            <span>ADMINISTRATION</span>
        </div>
    </div>

    <div class="error-body">
        <div class="status">HTTP {{ $status }} · {{ strtoupper($type) }}</div>
        <h1>{{ $title }}</h1>
        <p>{{ $message }}</p>

        @if($reference)
            <div class="reference">
                Support reference: {{ $reference }}
            </div>
        @endif

        <div class="actions">
            @if($status === 401)
                <a class="btn btn-primary" href="{{ route('admin.login') }}">
                    Sign In Again
                </a>
            @else
                <a class="btn btn-primary" href="{{ route('admin.dashboard') }}">
                    Go to Dashboard
                </a>
            @endif

            @if(url()->previous() !== url()->current())
                <a class="btn btn-secondary" href="{{ url()->previous() }}">
                    Go Back
                </a>
            @endif
        </div>

        <div class="security-note">
            Technical stack traces and sensitive request data are never displayed on this page.
        </div>
    </div>
</section>
</body>
</html>
