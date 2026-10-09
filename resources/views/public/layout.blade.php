<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="description" content="@yield('description', 'TradeX OS is a focused workspace for market education, intelligence and risk-aware planning.')">
    <meta name="theme-color" content="#06191a"><title>@yield('title') | TradeX OS</title>
    <link rel="icon" type="image/png" href="{{ asset('admin/brand/tradex-favicon.png') }}">
    <link rel="stylesheet" href="{{ asset('site/css/tradex-public.css') }}?v={{ filemtime(public_path('site/css/tradex-public.css')) }}">
    <link rel="stylesheet" href="{{ asset('site/css/tradex-public-pages.css') }}?v={{ filemtime(public_path('site/css/tradex-public-pages.css')) }}">
</head>
<body class="public-page">
<header><div class="shell nav"><a class="brand" href="{{ route('public.home') }}"><img src="{{ asset('admin/brand/tradex-logo.png') }}" alt="TradeX"><span>TRADE<b>X</b><small>OPERATING SYSTEM</small></span></a><nav><a href="{{ route('public.platform') }}">Platform</a><a href="{{ route('public.about') }}">Our method</a><a href="{{ route('public.faq') }}">FAQ</a><a href="{{ route('public.contact') }}">Contact</a></nav><a class="login" href="{{ route('admin.login') }}">Access platform <b>→</b></a></div></header>
<main>@yield('content')</main>
<footer><div class="shell footer"><div><a class="brand" href="{{ route('public.home') }}"><img src="{{ asset('admin/brand/tradex-logo.png') }}" alt=""><span>TRADE<b>X</b><small>OPERATING SYSTEM</small></span></a><p>Education, market intelligence and planning tools for a more structured trading process.</p><p class="company-id">Billionaire Trading Institute<br>Registration: PV00296953</p></div><div><b>PLATFORM</b><a href="{{ route('public.education') }}">Education</a><a href="{{ route('public.market-intelligence') }}">Market intelligence</a><a href="{{ route('public.tools') }}">Planning tools</a></div><div><b>COMPANY</b><a href="{{ route('public.about') }}">About TradeX</a><a href="{{ route('public.faq') }}">FAQ</a><a href="{{ route('public.contact') }}">Contact</a></div><div><b>POLICIES</b><a href="{{ route('public.terms') }}">Terms of use</a><a href="{{ route('public.privacy') }}">Privacy notice</a><a href="{{ route('public.risk-disclosure') }}">Risk disclosure</a></div></div><div class="shell bottom">© {{ now()->year }} Billionaire Trading Institute. All rights reserved.<span>Learn. Plan. Trade with clarity.</span></div></footer>
</body></html>
