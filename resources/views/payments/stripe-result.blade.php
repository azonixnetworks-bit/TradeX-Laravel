<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>{{ $result === 'success' ? 'Card payment submitted' : 'Card payment cancelled' }} · TradeX</title>
    <style>
        :root { color-scheme: dark; font-family: Inter, ui-sans-serif, system-ui, sans-serif; background: #07111f; color: #f8fafc; }
        * { box-sizing: border-box; }
        body { min-height: 100vh; margin: 0; display: grid; place-items: center; padding: 24px; background: radial-gradient(circle at top, rgba(99, 102, 241, .20), transparent 38%), #07111f; }
        main { width: min(100%, 560px); padding: 36px; border: 1px solid rgba(148, 163, 184, .24); border-radius: 24px; background: rgba(15, 23, 42, .92); box-shadow: 0 24px 80px rgba(0, 0, 0, .35); text-align: center; }
        .mark { width: 64px; height: 64px; margin: 0 auto 20px; display: grid; place-items: center; border-radius: 50%; background: {{ $result === 'success' ? '#4f46e5' : '#334155' }}; font-size: 30px; font-weight: 800; }
        h1 { margin: 0 0 12px; font-size: clamp(28px, 7vw, 40px); }
        p { margin: 0; color: #cbd5e1; line-height: 1.7; }
        .note { margin-top: 18px; font-size: 14px; color: #94a3b8; }
    </style>
</head>
<body>
<main>
    @if ($result === 'success')
        <div class="mark" aria-hidden="true">✓</div>
        <h1>Card payment submitted</h1>
        <p>Return to the TradeX app while the secure payment confirmation completes.</p>
        <p class="note">This page does not confirm settlement. TradeX activates access only after a verified Stripe webhook.</p>
    @else
        <div class="mark" aria-hidden="true">×</div>
        <h1>Payment cancelled</h1>
        <p>No payment was completed. You can safely return to the TradeX app and try again.</p>
    @endif
</main>
</body>
</html>
