@extends('public.layout')
@section('title', 'Contact TradeX OS')
@section('content')
<section class="page-hero"><div class="shell"><p class="eyebrow">Contact TradeX</p><h1>Start with a<br><em>clear conversation.</em></h1><p>Contact Billionaire Trading Institute for public enquiries, or use the authenticated platform support channel for account-specific assistance.</p></div></section>
<section class="page-body"><div class="shell contact-grid"><article><span>01</span><h2>Platform access</h2><p>Existing customers can access account information and available support tools after sign in.</p><a class="button primary" href="{{ route('admin.login') }}">Sign in →</a></article><article><span>02</span><h2>Public enquiries</h2><p><strong>Billionaire Trading Institute</strong><br>Registration: PV00296953</p><a class="contact-phone" href="tel:+94722542311">+94 72 254 2311</a></article></div></section>
@endsection
