<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>TradeX Admin Two-Factor Authentication</title>

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
            background: #03070d;
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

        button {
            width: 100%;
            margin-top: 18px;
            padding: 14px 18px;
            border: 0;
            border-radius: 12px;
            background: #dcae45;
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
            color: #6f8298;
        }
    </style>
</head>

<body>
    <main class="card">
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
</body>
</html>
