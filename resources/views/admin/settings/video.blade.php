@extends('admin.layouts.app')

@section('title', 'Video Settings')
@section('page-title', 'Video Settings')

@section('content')
<div class="page-header">
    <div>
        <div class="page-eyebrow">READ-ONLY MEDIA PIPELINE</div>
        <h1>Video <span>Settings</span></h1>
        <p>
            Safe HLS processing summary. Binary paths and private working directories are intentionally hidden.
        </p>
    </div>
</div>

@include('admin.settings.partials.nav')

<div class="user-stat-grid">
    <div class="user-stat-card">
        <span>Processing Queue</span>
        <strong>{{ $snapshot['queue'] }}</strong>
    </div>

    <div class="user-stat-card">
        <span>Max Upload</span>
        <strong>{{ number_format($snapshot['max_upload_kb']) }} KiB</strong>
    </div>

    <div class="user-stat-card">
        <span>Processing Timeout</span>
        <strong>{{ number_format($snapshot['processing_timeout_seconds']) }} sec</strong>
    </div>

    <div class="user-stat-card">
        <span>HLS Segment</span>
        <strong>{{ $snapshot['hls_segment_seconds'] }} sec</strong>
    </div>
</div>

<section class="panel users-panel">
    <div class="panel-header">
        <div>
            <div class="panel-eyebrow">RENDITIONS</div>
            <h2>Configured HLS Variants</h2>
        </div>
    </div>

    <div class="users-table-wrap">
        <table class="users-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Height</th>
                </tr>
            </thead>
            <tbody>
            @forelse($snapshot['variants'] as $variant)
                <tr>
                    <td><strong>{{ $variant['name'] }}</strong></td>
                    <td>{{ $variant['height'] }}p</td>
                </tr>
            @empty
                <tr>
                    <td colspan="2">No HLS variants are configured.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</section>

@if(auth('admin')->user()->hasPermission('education.view'))
    <div style="margin-top:18px;">
        <a
            class="primary-btn"
            href="{{ route('admin.education.courses.index') }}"
        >
            Open Education Management
        </a>
    </div>
@endif

<section class="panel" style="padding:18px;margin-top:18px;">
    FFmpeg/FFprobe binary paths, private processing directories, filesystem secrets and protected playback authorization remain outside System Settings.
</section>
@endsection
