<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Forgot Password — TradeX Admin</title><style>
:root{--gold:#dcae45;--gold2:#f1ce72;--muted:#8da0b6}*{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;padding:24px;font-family:Inter,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;color:#fff;background:radial-gradient(circle at 20% 10%,rgba(20,119,255,.15),transparent 30%),#03070d}.card{width:min(520px,100%);padding:42px;border:1px solid rgba(255,255,255,.08);border-radius:24px;background:#07111e;box-shadow:0 35px 90px rgba(0,0,0,.5)}img{width:150px;max-height:110px;object-fit:contain;object-position:left center}.eyebrow{margin-top:24px;color:var(--gold);font-size:11px;font-weight:800;letter-spacing:2px}h1{margin:10px 0 10px;font-size:32px}.copy{color:var(--muted);line-height:1.65;font-size:14px}label{display:block;margin:26px 0 8px;color:#b9c6d5;font-size:13px;font-weight:600}input{width:100%;height:56px;padding:0 16px;border:1px solid rgba(255,255,255,.1);border-radius:13px;outline:none;background:#091727;color:#fff;font-size:15px}input:focus{border-color:#1477ff;box-shadow:0 0 0 4px rgba(20,119,255,.09)}button{width:100%;height:56px;margin-top:22px;border:0;border-radius:13px;font-weight:800;cursor:pointer;background:linear-gradient(135deg,#c9972d,#f0cb6a);color:#080a0d}.status,.error{margin-top:18px;padding:13px 15px;border-radius:11px;font-size:13px}.status{color:#86edbd;background:rgba(57,217,138,.08)}.error{color:#ffabb2;background:rgba(255,95,109,.08)}.back{display:inline-block;margin-top:24px;color:var(--gold2);text-decoration:none;font-size:13px}
</style>

    <link
        rel="stylesheet"
        href="{{ asset('admin/css/auth-theme.css') }}"
    ></head>
<body><div class="card"><img src="{{ asset('admin/brand/tradex-logo.webp') }}" alt="TradeX"><div class="eyebrow">SECURE PASSWORD RECOVERY</div><h1>Forgot password?</h1><div class="copy">Enter your admin email. If an active TradeX admin account matches it, we will send a short-lived reset link. Two-factor authentication stays enabled.</div>
@if(session('status'))<div class="status">{{ session('status') }}</div>@endif
@error('email')<div class="error">{{ $message }}</div>@enderror
<form method="POST" action="{{ route('admin.password.email') }}">@csrf<label for="email">Admin Email</label><input id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="email" required autofocus><button type="submit">SEND RESET LINK</button></form><a class="back" href="{{ route('admin.login') }}">← Back to secure sign in</a></div></body></html>
