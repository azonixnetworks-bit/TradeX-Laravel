<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <meta name="theme-color" content="#03070d">

    <title>
        TradeX Administration
    </title>

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

        :root {
            --bg: #03070d;
            --surface: #07111e;
            --surface-2: #0a1829;
            --gold: #dcae45;
            --gold-light: #f1ce72;
            --blue: #1477ff;
            --text: #ffffff;
            --muted: #8394a9;
            --danger: #ff5f6d;
            --success: #39d98a;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family:
                Inter,
                -apple-system,
                BlinkMacSystemFont,
                "Segoe UI",
                sans-serif;
            color: var(--text);
            background:
                radial-gradient(
                    circle at 18% 20%,
                    rgba(24, 86, 160, .17),
                    transparent 30%
                ),
                radial-gradient(
                    circle at 85% 80%,
                    rgba(218, 171, 65, .09),
                    transparent 28%
                ),
                #03070d;
            display: grid;
            place-items: center;
            padding: 30px;
        }

        .shell {
            width: min(1180px, 100%);
            min-height: 690px;
            display: grid;
            grid-template-columns: 1.08fr .92fr;
            border: 1px solid rgba(255,255,255,.07);
            border-radius: 30px;
            overflow: hidden;
            background: rgba(5, 14, 25, .90);
            box-shadow: 0 50px 120px rgba(0,0,0,.6);
        }

        .brand {
            position: relative;
            padding: 50px 64px 58px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            background:
                linear-gradient(
                    145deg,
                    rgba(11, 38, 69, .86),
                    rgba(3, 9, 16, .94)
                );
        }

        .brand::after {
            content: "";
            position: absolute;
            width: 420px;
            height: 420px;
            border-radius: 50%;
            right: -180px;
            bottom: -180px;
            background:
                radial-gradient(
                    circle,
                    rgba(221,174,69,.13),
                    transparent 70%
                );
            pointer-events: none;
        }

        .brand-logo-wrap {
            position: relative;
            z-index: 1;
            min-height: 230px;
            display: flex;
            align-items: flex-start;
        }

        .brand-logo-wrap .auth-brand-logo {
            width: min(292px, 78%);
            max-height: 255px;
        }

        .tag {
            color: var(--gold);
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 3px;
        }

        .brand h1 {
            max-width: 540px;
            margin: 20px 0 22px;
            font-size: clamp(42px, 5vw, 67px);
            line-height: 1.02;
            letter-spacing: -2px;
        }

        .brand h1 span {
            color: var(--gold-light);
        }

        .brand-copy {
            max-width: 500px;
            color: #8fa2b8;
            font-size: 16px;
            line-height: 1.8;
        }

        .bottom-copy {
            position: relative;
            z-index: 1;
            color: #6e8195;
            font-size: 11px;
            font-weight: 750;
            letter-spacing: 2.1px;
        }

        .login {
            padding: 70px 64px;
            display: flex;
            align-items: center;
        }

        .form {
            width: 100%;
        }

        .login-mobile-brand {
            display: none;
            margin-bottom: 24px;
        }

        .login-mobile-brand img {
            width: 158px;
            max-height: 150px;
            object-fit: contain;
            object-position: left center;
        }

        .eyebrow {
            color: var(--gold);
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 2.5px;
        }

        .form h2 {
            margin: 13px 0 8px;
            font-size: 38px;
        }

        .subtitle {
            margin-bottom: 35px;
            color: var(--muted);
        }

        label {
            display: block;
            margin: 18px 0 8px;
            color: #b4c1d0;
            font-size: 13px;
            font-weight: 600;
        }

        input[type="email"],
        input[type="password"] {
            width: 100%;
            height: 56px;
            padding: 0 17px;
            color: #ffffff;
            font-size: 15px;
            outline: none;
            border: 1px solid rgba(255,255,255,.09);
            border-radius: 13px;
            background: #091727;
            transition: .2s ease;
        }

        input:focus {
            border-color: rgba(25,118,255,.8);
            box-shadow: 0 0 0 4px rgba(20,119,255,.09);
        }

        .password-wrap {
            position: relative;
        }

        .toggle {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            border: 0;
            color: #8092a8;
            cursor: pointer;
            background: transparent;
        }

        .options {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin: 19px 0 25px;
        }

        .remember {
            display: flex;
            align-items: center;
            gap: 9px;
            color: #8598ae;
            font-size: 13px;
        }

        .login-btn {
            width: 100%;
            height: 57px;
            border: 0;
            border-radius: 13px;
            cursor: pointer;
            color: #080a0d;
            font-size: 15px;
            font-weight: 800;
            background:
                linear-gradient(
                    135deg,
                    #c9972d,
                    #f0cb6a
                );
            box-shadow: 0 15px 35px rgba(220,174,69,.15);
            transition: .2s ease;
        }

        .login-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 20px 40px rgba(220,174,69,.20);
        }

        .message {
            margin-bottom: 20px;
            padding: 13px 15px;
            border-radius: 11px;
            font-size: 13px;
        }

        .message.error {
            color: #ffabb2;
            border: 1px solid rgba(255,95,109,.17);
            background: rgba(255,95,109,.08);
        }

        .message.success {
            color: #86edbd;
            border: 1px solid rgba(57,217,138,.17);
            background: rgba(57,217,138,.08);
        }

        .field-error {
            margin-top: 7px;
            color: #ff8e98;
            font-size: 12px;
        }

        .security {
            margin-top: 30px;
            padding-top: 22px;
            border-top: 1px solid rgba(255,255,255,.06);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            color: #607389;
            font-size: 12px;
        }

        .security strong {
            color: #a98945;
            font-size: 9px;
            letter-spacing: 1.6px;
        }

        @media(max-width: 900px) {
            body {
                padding: 18px;
            }

            .shell {
                grid-template-columns: 1fr;
                min-height: auto;
            }

            .brand {
                display: none;
            }

            .login {
                padding: 46px 34px;
            }

            .login-mobile-brand {
                display: block;
            }
        }

        @media(max-width: 520px) {
            .login {
                padding: 36px 24px;
            }

            .form h2 {
                font-size: 32px;
            }

            .security {
                align-items: flex-start;
                flex-direction: column;
            }
        }

    </style>

</head>

<body>

<div
    id="tradexLoader"
    class="tradex-loader"
    role="status"
    aria-label="Loading TradeX Administration"
    aria-live="polite"
>
    <div class="tradex-loader-card">
        <img
            src="{{ asset('admin/brand/tradex-favicon.png') }}"
            alt=""
            width="64"
            height="64"
        >
        <span>TRADEX ADMINISTRATION</span>
    </div>
</div>

<div class="shell">

    <section class="brand">

        <div class="brand-logo-wrap">
            <img
                class="auth-brand-logo"
                src="{{ asset('admin/brand/tradex-logo.webp') }}"
                alt="TradeX — Learn. Trade. Grow."
                width="310"
                height="320"
            >
        </div>

        <div>

            <div class="tag">
                ADMINISTRATION
            </div>

            <h1>
                Control the
                <span>
                    TradeX ecosystem.
                </span>
            </h1>

            <div class="brand-copy">
                Securely manage customers,
                education, Trade Ideas,
                affiliates, payments,
                finance and platform
                operations from one
                premium workspace.
            </div>

        </div>

        <div class="bottom-copy">
            LEARN • TRADE • GROW
        </div>

    </section>

    <section class="login">

        <form
            class="form"
            method="POST"
            action="{{ route('admin.login.submit') }}"
        >

            @csrf

            <div class="login-mobile-brand">
                <img
                    src="{{ asset('admin/brand/tradex-logo.webp') }}"
                    alt="TradeX — Learn. Trade. Grow."
                    width="310"
                    height="320"
                >
            </div>

            <div class="eyebrow">
                SECURE ADMIN ACCESS
            </div>

            <h2>
                Welcome back
            </h2>

            <div class="subtitle">
                Sign in to TradeX Administration.
            </div>

            @if(session('error'))
                <div class="message error">
                    {{ session('error') }}
                </div>
            @endif

            @if(session('success'))
                <div class="message success">
                    {{ session('success') }}
                </div>
            @endif

            <label for="email">
                Email Address
            </label>

            <input
                id="email"
                type="email"
                name="email"
                value="{{ old('email') }}"
                autocomplete="username"
                autofocus
                required
            >

            @error('email')
                <div class="field-error">
                    {{ $message }}
                </div>
            @enderror

            <label for="password">
                Password
            </label>

            <div class="password-wrap">

                <input
                    id="password"
                    type="password"
                    name="password"
                    autocomplete="current-password"
                    required
                >

                <button
                    class="toggle"
                    type="button"
                    id="passwordToggle"
                >
                    Show
                </button>

            </div>

            @error('password')
                <div class="field-error">
                    {{ $message }}
                </div>
            @enderror

            <div class="options">
                <label class="remember">
                    <input
                        type="checkbox"
                        name="remember"
                        value="1"
                        {{ old('remember') ? 'checked' : '' }}
                    >
                    Remember me
                </label>

                <a
                    href="{{ route('admin.password.request') }}"
                    style="color: var(--gold-light); font-size: 13px; font-weight: 700; text-decoration: none;"
                >
                    Forgot password?
                </a>
            </div>

            <button
                class="login-btn"
                type="submit"
            >
                SIGN IN →
            </button>

            <div class="security">
                <span>◈ Protected TradeX Administration</span>
                <strong>LEARN • TRADE • GROW</strong>
            </div>

        </form>

    </section>

</div>

<script>
    const toggle =
        document.getElementById(
            'passwordToggle'
        );

    const password =
        document.getElementById(
            'password'
        );

    toggle.addEventListener(
        'click',
        function () {
            const hidden =
                password.type === 'password';

            password.type =
                hidden
                    ? 'text'
                    : 'password';

            toggle.textContent =
                hidden
                    ? 'Hide'
                    : 'Show';
        }
    );
</script>

<script
    src="{{ asset('admin/js/branding.js') }}">
</script>

</body>
</html>