<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <meta name="theme-color" content="#03070d">

    <title>TradeX Admin Two-Factor Authentication</title>

    <link
        rel="icon"
        type="image/png"
        href="{{ asset('admin/brand/tradex-favicon.png') }}"
    >

    <link
        rel="stylesheet"
        href="{{ asset('admin/css/branding.css') }}"
    >

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
            background:
                radial-gradient(circle at 50% 12%, rgba(28, 78, 128, .18), transparent 34%),
                #03070d;
            color: #ffffff;
            font-family:
                Inter,
                -apple-system,
                BlinkMacSystemFont,
                "Segoe UI",
                sans-serif;
        }

        .card {
            width: min(480px, 100%);
            padding: 34px;
            border: 1px solid rgba(255,255,255,.09);
            border-radius: 24px;
            background: #07111e;
            box-shadow: 0 35px 90px rgba(0,0,0,.48);
        }

        .brand-lockup {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            margin-bottom: 22px;
            padding-bottom: 20px;
            border-bottom: 1px solid rgba(255,255,255,.06);
        }

        .brand-lockup img {
            width: 116px;
            max-height: 112px;
            object-fit: contain;
            object-position: left center;
        }

        .brand-lockup span {
            color: #71869b;
            font-size: 9px;
            font-weight: 800;
            letter-spacing: 1.7px;
            text-align: right;
        }

        .eyebrow {
            color: #dcae45;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 2.5px;
        }

        h1 {
            margin: 12px 0 10px;
            font-size: 32px;
        }

        p {
            color: #93a4b8;
            line-height: 1.7;
        }

        label {
            display: block;
            margin-top: 22px;
            margin-bottom: 8px;
            font-weight: 700;
        }

        input {
            width: 100%;
            padding: 15px 16px;
            border: 1px solid rgba(255,255,255,.12);
            border-radius: 12px;
            background: #030a12;
            color: #ffffff;
            font-size: 22px;
            letter-spacing: 8px;
            text-align: center;
        }

        input:focus {
            outline: none;
            border-color: rgba(220,174,69,.58);
            box-shadow: 0 0 0 4px rgba(220,174,69,.08);
        }

        button {
            width: 100%;
            margin-top: 18px;
            padding: 14px 18px;
            border: 0;
            border-radius: 12px;
            background:
                linear-gradient(135deg, #c9972d, #f0cb6a);
            color: #09111b;
            font-weight: 900;
            cursor: pointer;
        }

        .error {
            margin-top: 8px;
            color: #ff7784;
        }

        .meta {
            margin-top: 18px;
            font-size: 12px;
            line-height: 1.6;
            color: #6f8298;
        }

        @media (max-width: 520px) {
            .card {
                padding: 26px 22px;
            }

            .brand-lockup {
                align-items: flex-start;
            }

            .brand-lockup img {
                width: 96px;
            }

            h1 {
                font-size: 28px;
            }
        }
    </style>

    <link
        rel="stylesheet"
        href="{{ asset('admin/css/auth-theme.css') }}"
    >
</head>

<body>
    <div
        id="tradexLoader"
        class="tradex-loader"
        role="status"
        aria-label="Loading TradeX security"
        aria-live="polite"
    >
        <div class="tradex-loader-card">
            <img
                src="{{ asset('admin/brand/tradex-favicon.png') }}"
                alt=""
                width="64"
                height="64"
            >
            <span>TRADEX ADMIN SECURITY</span>
        </div>
    </div>

    <main class="card">
        <div class="brand-lockup">
            <img
                src="{{ asset('admin/brand/tradex-logo.webp') }}"
                alt="TradeX — Learn. Trade. Grow."
                width="310"
                height="320"
            >
            <span>SECURE<br>ADMINISTRATION</span>
        </div>

        <div class="eyebrow">
            TRADEX ADMIN SECURITY
        </div>

        <h1>Two-factor authentication</h1>

        <p>
            Enter the fresh code from your authenticator app.
            This challenge expires in {{ $challengeMinutes }} minutes.
        </p>

        <form
            method="POST"
            action="{{ route('admin.totp.challenge.submit') }}"
        >
            @csrf

            <label for="code">
                Authenticator code
            </label>

            <input
                id="code"
                name="code"
                type="text"
                inputmode="numeric"
                pattern="[0-9]*"
                maxlength="6"
                autocomplete="one-time-code"
                autofocus
                required
            >

            @error('code')
                <div class="error">
                    {{ $message }}
                </div>
            @enderror

            <button type="submit">
                Verify & Sign In
            </button>
        </form>

        <div class="meta">
            Password verification has already succeeded.
            TOTP verification is required before an admin session is created.
        </div>
    </main>

    <script
        src="{{ asset('admin/js/branding.js') }}">
    </script>
</body>
</html>
