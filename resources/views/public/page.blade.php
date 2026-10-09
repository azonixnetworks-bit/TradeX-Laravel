@extends('public.layout')
@section('title', $title)
@section('description', $lead)
@section('content')
<section class="page-hero"><div class="shell"><p class="eyebrow">{{ $eyebrow }}</p><h1>{{ $title }}</h1><p>{{ $lead }}</p></div></section>
<section class="page-body"><div class="shell"><div class="page-grid">@foreach($blocks as $index => $block)<article><span>0{{ $index + 1 }}</span><h2>{{ $block['title'] }}</h2><p>{{ $block['text'] }}</p></article>@endforeach</div><div class="page-cta"><p>TRADEX OS</p><h2>Build a stronger<br><em>trading process.</em></h2><a class="button primary" href="{{ route('admin.login') }}">Access the platform →</a></div></div></section>
@endsection
