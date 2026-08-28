<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Payment submitted | TradeX</title>
    <style>
        :root { color-scheme: dark; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: #090b10;
            color: #f6f7fb;
        }
        main {
            width: min(100%, 560px);
            padding: clamp(28px, 6vw, 48px);
            border: 1px solid rgba(255,255,255,.10);
            border-radius: 24px;
            background: rgba(255,255,255,.04);
            box-shadow: 0 24px 80px rgba(0,0,0,.35);
        }
        .eyebrow { margin: 0 0 12px; font-size: 13px; letter-spacing: .12em; text-transform: uppercase; opacity: .7; }
        h1 { margin: 0 0 16px; font-size: clamp(30px, 7vw, 46px); line-height: 1.05; }
        p { margin: 0; line-height: 1.7; color: rgba(246,247,251,.78); }
        .note { margin-top: 24px; padding-top: 20px; border-top: 1px solid rgba(255,255,255,.09); font-size: 14px; }
    </style>
</head>
<body>
<main>
    <p class="eyebrow">TradeX secure checkout</p>
    <h1>Payment submitted</h1>
    <p>Your Stripe checkout has returned to TradeX. You may return to the TradeX app while payment verification finishes.</p>
    <p class="note">This page does not confirm settlement or premium access. TradeX activates access only after a valid Stripe-signed webhook is verified by the server.</p>
</main>
</body>
</html>
