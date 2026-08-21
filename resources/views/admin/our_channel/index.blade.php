{{-- TradeX Admin — ADMIN-04 Our Channel Management --}}

@extends('admin.layouts.app')

@section('title', 'Our Channel | TradeX Administration')
@section('page-title', 'Our Channel')

@push('styles')
<style>
    .channel-grid{display:grid;gap:14px}.channel-card{display:grid;grid-template-columns:220px minmax(0,1fr) 175px 190px;gap:18px;align-items:center;padding:17px;border:1px solid rgba(255,255,255,.07);border-radius:14px;background:rgba(255,255,255,.022)}
    .channel-thumb{position:relative;overflow:hidden;border-radius:12px;background:#080808;aspect-ratio:16/9;border:1px solid rgba(255,255,255,.07)}
    .channel-thumb img{width:100%;height:100%;display:block;object-fit:cover}.channel-thumb span{position:absolute;right:8px;bottom:8px;padding:5px 7px;border-radius:7px;background:rgba(0,0,0,.72);font-size:9px;font-weight:800}
    .channel-copy strong{display:block;margin-bottom:6px;font-size:14px}.channel-copy p{margin:0;font-size:11px;line-height:1.55;opacity:.57}.channel-tags{display:flex;gap:6px;flex-wrap:wrap;margin-top:9px}.channel-tag{padding:5px 8px;border-radius:999px;border:1px solid rgba(255,255,255,.08);font-size:9px;font-weight:800}.channel-tag.featured{color:#ddb65e;border-color:rgba(218,178,88,.24);background:rgba(218,178,88,.06)}
    .channel-metrics{display:grid;gap:8px}.channel-metric{padding:9px 10px;border:1px solid rgba(255,255,255,.06);border-radius:10px;background:rgba(255,255,255,.018)}.channel-metric span{display:block;font-size:8px;opacity:.45;text-transform:uppercase;margin-bottom:3px}.channel-metric strong{font-size:11px}
    .channel-actions{display:grid;gap:8px}.channel-actions form{margin:0}.channel-actions textarea{width:100%;box-sizing:border-box;min-height:50px;padding:8px;border:1px solid rgba(255,255,255,.08);border-radius:9px;background:rgba(255,255,255,.025);color:inherit;font:inherit;font-size:10px;resize:vertical;margin-bottom:6px}
    .channel-link,.channel-btn{display:block;width:100%;box-sizing:border-box;border-radius:9px;padding:9px 10px;text-align:center;font:inherit;font-size:10px;font-weight:700;text-decoration:none}.channel-link{border:1px solid rgba(255,255,255,.09);background:rgba(255,255,255,.035);color:inherit}.channel-link.youtube{color:#ff9a9a;border-color:rgba(255,91,91,.16)}.channel-btn{cursor:pointer}.publish{border:1px solid rgba(218,178,88,.25);background:rgba(218,178,88,.09);color:#e0ba65}.unpublish{border:1px solid rgba(255,179,71,.21);background:rgba(255,179,71,.07);color:#ffc173}.delete{border:1px solid rgba(255,91,91,.24);background:rgba(255,91,91,.06);color:#ff8585}
    .channel-empty{padding:46px 20px;text-align:center}.channel-empty strong{display:block;margin-bottom:7px}.channel-empty span{font-size:12px;opacity:.58}.channel-empty a{display:inline-block;margin-top:17px}
    @media(max-width:1150px){.channel-card{grid-template-columns:190px minmax(0,1fr) 165px}.channel-actions{grid-column:2/-1;grid-template-columns:repeat(3,minmax(0,1fr))}}
    @media(max-width:760px){.channel-card{grid-template-columns:1fr}.channel-thumb{max-width:420px}.channel-actions{grid-column:auto;grid-template-columns:1fr}}
</style>
@endpush

@section('content')
<div class="page-header">
    <div>
        <div class="page-eyebrow">ADMIN-04 · FREE LEARNING CONTENT</div>
        <h1>Our <span>Channel</span></h1>
        <p>Curate TradeX YouTube videos for free in-app learning without duplicating video storage.</p>
    </div>

    @if($permissions['manage'] ?? false)
        <div>
            <a
                class="primary-btn"
                href="{{ route('admin.our_channel.create') }}"
            >
                + Add YouTube Video
            </a>
        </div>
    @endif
</div>

<div class="user-stat-grid">
    <div class="user-stat-card"><span>Total Videos</span><strong>{{ number_format($stats['total']) }}</strong></div>
    <div class="user-stat-card"><span>Published</span><strong class="text-green">{{ number_format($stats['published']) }}</strong></div>
    <div class="user-stat-card"><span>Draft</span><strong class="text-orange">{{ number_format($stats['draft']) }}</strong></div>
    <div class="user-stat-card"><span>Featured</span><strong class="text-gold">{{ number_format($stats['featured']) }}</strong></div>
</div>

<section class="panel users-panel">
    <div class="panel-header">
        <div>
            <div class="panel-eyebrow">YOUTUBE CATALOGUE</div>
            <h2>Managed Videos</h2>
        </div>
    </div>

    <div style="padding:24px;border-top:1px solid rgba(255,255,255,.06)">
        @forelse($videos as $video)
            @if($loop->first)<div class="channel-grid">@endif

            @php
                $statusClass = $video->status === 'PUBLISHED'
                    ? 'status-active'
                    : ($video->status === 'ARCHIVED' ? 'status-blocked' : 'status-pending');
            @endphp

            <article class="channel-card">
                <div class="channel-thumb">
                    <img
                        src="{{ $video->thumbnailUrl() }}"
                        alt=""
                        loading="lazy"
                        referrerpolicy="no-referrer"
                    >
                    <span>YOUTUBE</span>
                </div>

                <div class="channel-copy">
                    <strong>{{ $video->title }}</strong>
                    <p>{{ $video->short_description ?: 'No summary has been added.' }}</p>

                    <div class="channel-tags">
                        <span class="channel-tag">{{ strtoupper($video->language_code) }}</span>
                        @if($video->is_featured)
                            <span class="channel-tag featured">FEATURED</span>
                        @endif
                    </div>
                </div>

                <div class="channel-metrics">
                    <div class="channel-metric">
                        <span>Status</span>
                        <div class="user-status {{ $statusClass }}">
                            <span class="user-status-dot"></span>
                            {{ $video->status }}
                        </div>
                    </div>

                    <div class="channel-metric">
                        <span>YouTube ID</span>
                        <strong>{{ $video->youtube_video_id }}</strong>
                    </div>

                    <div class="channel-metric">
                        <span>Order</span>
                        <strong>{{ number_format($video->sort_order) }}</strong>
                    </div>
                </div>

                <div class="channel-actions">
                    <a
                        class="channel-link youtube"
                        href="{{ $video->canonicalYouTubeUrl() }}"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        Open YouTube
                    </a>

                    @if($permissions['manage'] ?? false)
                        <a
                            class="channel-link"
                            href="{{ route('admin.our_channel.edit', ['channelVideo'=>$video]) }}"
                        >
                            Edit Video
                        </a>

                        @if($video->status === 'DRAFT')
                            <form
                                method="POST"
                                action="{{ route('admin.our_channel.publication', ['channelVideo'=>$video]) }}"
                                onsubmit="return confirm('Publish this Our Channel video?');"
                            >
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="action" value="PUBLISH">
                                <textarea name="reason" maxlength="1000" placeholder="Optional audit reason"></textarea>
                                <button class="channel-btn publish" type="submit">Publish</button>
                            </form>

                            <form
                                method="POST"
                                action="{{ route('admin.our_channel.destroy', ['channelVideo'=>$video]) }}"
                                onsubmit="return confirm('Delete this Our Channel video?');"
                            >
                                @csrf
                                @method('DELETE')
                                <button class="channel-btn delete" type="submit">Delete</button>
                            </form>
                        @elseif($video->status === 'PUBLISHED')
                            <form
                                method="POST"
                                action="{{ route('admin.our_channel.publication', ['channelVideo'=>$video]) }}"
                                onsubmit="return confirm('Unpublish this Our Channel video?');"
                            >
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="action" value="UNPUBLISH">
                                <textarea name="reason" maxlength="1000" placeholder="Optional audit reason"></textarea>
                                <button class="channel-btn unpublish" type="submit">Unpublish</button>
                            </form>
                        @endif
                    @endif
                </div>
            </article>

            @if($loop->last)</div>@endif
        @empty
            <div class="channel-empty">
                <strong>No Our Channel videos yet</strong>
                <span>Add a trusted TradeX YouTube video and publish it when it is ready.</span>

                @if($permissions['manage'] ?? false)
                    <a
                        class="primary-btn"
                        href="{{ route('admin.our_channel.create') }}"
                    >
                        + Add First Video
                    </a>
                @endif
            </div>
        @endforelse
    </div>
</section>
@endsection
