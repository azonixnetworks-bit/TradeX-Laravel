{{-- TradeX Admin — Our Channel Multi-Platform Management --}}

@extends('admin.layouts.app')

@section('title', 'Our Channel | TradeX Administration')
@section('page-title', 'Our Channel')

@push('styles')
<style>
    .channel-grid{display:grid;gap:14px}.channel-card{display:grid;grid-template-columns:210px minmax(0,1fr) 210px;gap:18px;align-items:start;padding:17px;border:1px solid rgba(255,255,255,.07);border-radius:14px;background:rgba(255,255,255,.022)}
    .channel-thumb{position:relative;overflow:hidden;border-radius:12px;background:radial-gradient(circle at 50% 35%,rgba(218,178,88,.14),rgba(8,8,8,.96));aspect-ratio:16/9;border:1px solid rgba(255,255,255,.07);display:flex;align-items:center;justify-content:center}.channel-thumb img{width:100%;height:100%;display:block;object-fit:cover}.channel-thumb .fallback{font-size:28px;opacity:.65}.channel-thumb>span:last-child{position:absolute;right:8px;bottom:8px;padding:5px 7px;border-radius:7px;background:rgba(0,0,0,.72);font-size:9px;font-weight:800}
    .channel-copy strong{display:block;margin-bottom:6px;font-size:14px}.channel-copy p{margin:0;font-size:11px;line-height:1.55;opacity:.57}.channel-tags{display:flex;gap:6px;flex-wrap:wrap;margin-top:9px}.channel-tag{padding:5px 8px;border-radius:999px;border:1px solid rgba(255,255,255,.08);font-size:9px;font-weight:800}.channel-tag.featured{color:#ddb65e;border-color:rgba(218,178,88,.24);background:rgba(218,178,88,.06)}
    .source-line{margin-top:10px;padding:9px 10px;border:1px solid rgba(255,255,255,.06);border-radius:9px;background:rgba(255,255,255,.018);font-size:9px;line-height:1.5;opacity:.72}
    .publication-list{display:grid;gap:7px;margin-top:13px}.publication-row{display:grid;grid-template-columns:130px 115px minmax(0,1fr);gap:8px;align-items:center;padding:9px 10px;border:1px solid rgba(255,255,255,.06);border-radius:10px;background:rgba(255,255,255,.015)}.publication-row strong{font-size:9px}.publication-note{font-size:9px;opacity:.56;line-height:1.4;overflow-wrap:anywhere}
    .pub-status{display:inline-flex;align-items:center;justify-content:center;padding:4px 7px;border-radius:999px;border:1px solid rgba(255,255,255,.09);font-size:8px;font-weight:900}.pub-status.PUBLISHED{color:#78df9f;border-color:rgba(120,223,159,.23)}.pub-status.FAILED{color:#ff8b8b;border-color:rgba(255,139,139,.23)}.pub-status.PROCESSING,.pub-status.PENDING{color:#87b9ff;border-color:rgba(135,185,255,.23)}.pub-status.ACTION_REQUIRED{color:#e7bf69;border-color:rgba(231,191,105,.23)}
    .pub-actions{display:flex;gap:6px;flex-wrap:wrap;margin-top:6px}.mini-link,.mini-btn{display:inline-block;border:1px solid rgba(255,255,255,.08);border-radius:7px;background:rgba(255,255,255,.025);color:inherit;padding:5px 7px;font:inherit;font-size:8px;font-weight:800;text-decoration:none;cursor:pointer}.mini-btn.retry{color:#e3bd68;border-color:rgba(227,189,104,.22)}
    .channel-actions{display:grid;gap:8px}.channel-actions form{margin:0}.channel-actions textarea{width:100%;box-sizing:border-box;min-height:48px;padding:8px;border:1px solid rgba(255,255,255,.08);border-radius:9px;background:rgba(255,255,255,.025);color:inherit;font:inherit;font-size:10px;resize:vertical;margin-bottom:6px}
    .channel-link,.channel-btn{display:block;width:100%;box-sizing:border-box;border-radius:9px;padding:9px 10px;text-align:center;font:inherit;font-size:10px;font-weight:700;text-decoration:none}.channel-link{border:1px solid rgba(255,255,255,.09);background:rgba(255,255,255,.035);color:inherit}.channel-link.youtube{color:#ff9a9a;border-color:rgba(255,91,91,.16)}.channel-btn{cursor:pointer}.publish{border:1px solid rgba(218,178,88,.25);background:rgba(218,178,88,.09);color:#e0ba65}.unpublish{border:1px solid rgba(255,179,71,.21);background:rgba(255,179,71,.07);color:#ffc173}.delete{border:1px solid rgba(255,91,91,.24);background:rgba(255,91,91,.06);color:#ff8585}
    .channel-empty{padding:46px 20px;text-align:center}.channel-empty strong{display:block;margin-bottom:7px}.channel-empty span{font-size:12px;opacity:.58}.channel-empty a{display:inline-block;margin-top:17px}
    @media(max-width:1050px){.channel-card{grid-template-columns:190px minmax(0,1fr)}.channel-actions{grid-column:1/-1;grid-template-columns:repeat(3,minmax(0,1fr))}}@media(max-width:760px){.channel-card,.publication-row{grid-template-columns:1fr}.channel-thumb{max-width:420px}.channel-actions{grid-template-columns:1fr}}
</style>
@endpush

@section('content')
<div class="page-header">
    <div>
        <div class="page-eyebrow">OUR CHANNEL · DISTRIBUTION CONTROL</div>
        <h1>Our <span>Channel</span></h1>
        <p>Upload once, track every selected destination independently, and retry only the platform that needs attention.</p>
    </div>

    @if($permissions['manage'] ?? false)
        <div style="display:flex;gap:10px;flex-wrap:wrap">
            <a class="secondary-btn" href="{{ route('admin.our_channel.connections.index') }}">Channel Connections</a>
            <a class="primary-btn" href="{{ route('admin.our_channel.create') }}">+ Upload Video</a>
        </div>
    @endif
</div>

<div class="user-stat-grid">
    <div class="user-stat-card"><span>Total Videos</span><strong>{{ number_format($stats['total']) }}</strong></div>
    <div class="user-stat-card"><span>TradeX Published</span><strong class="text-green">{{ number_format($stats['published']) }}</strong></div>
    <div class="user-stat-card"><span>External Published</span><strong class="text-gold">{{ number_format($stats['external_published']) }}</strong></div>
    <div class="user-stat-card"><span>Needs Attention</span><strong class="text-orange">{{ number_format($stats['attention']) }}</strong></div>
</div>

<section class="panel users-panel">
    <div class="panel-header">
        <div>
            <div class="panel-eyebrow">MULTI-PLATFORM VIDEO LIBRARY</div>
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
                $thumb = $video->thumbnailUrl();
                $youtubeUrl = $video->canonicalYouTubeUrl();
            @endphp

            <article class="channel-card">
                <div class="channel-thumb">
                    @if($thumb)
                        <img src="{{ $thumb }}" alt="" loading="lazy" referrerpolicy="no-referrer">
                    @else
                        <span class="fallback">▶</span>
                    @endif
                    <span>{{ $video->hasSourceMedia() ? 'MASTER UPLOAD' : 'YOUTUBE LINK' }}</span>
                </div>

                <div class="channel-copy">
                    <strong>{{ $video->title }}</strong>
                    <p>{{ $video->short_description ?: 'No summary has been added.' }}</p>

                    <div class="channel-tags">
                        <span class="channel-tag">{{ strtoupper($video->language_code) }}</span>
                        <span class="channel-tag">{{ $video->status }}</span>
                        @if($video->is_featured)
                            <span class="channel-tag featured">FEATURED</span>
                        @endif
                    </div>

                    @if($video->hasSourceMedia())
                        <div class="source-line">
                            Protected source: {{ $video->sourceDisplayName() ?: 'master video' }}
                            @if($video->source_size_bytes)
                                · {{ number_format($video->source_size_bytes / 1048576, 1) }} MB
                            @endif
                        </div>
                    @endif

                    <div class="publication-list">
                        @forelse($video->publications as $publication)
                            <div class="publication-row">
                                <strong>{{ $publication->platformLabel() }}</strong>
                                <span class="pub-status {{ $publication->status }}">{{ str_replace('_', ' ', $publication->status) }}</span>

                                <div>
                                    <div class="publication-note">
                                        {{ $publication->status_message ?: ($publication->isPublished() ? 'Published successfully.' : 'Waiting for publisher queue.') }}
                                    </div>

                                    <div class="pub-actions">
                                        @if($publication->provider_url)
                                            <a class="mini-link" href="{{ $publication->provider_url }}" target="_blank" rel="noopener noreferrer">Open Post</a>
                                        @endif

                                        @if(($permissions['manage'] ?? false) && $publication->isRetryable())
                                            <form method="POST" action="{{ route('admin.our_channel.publications.retry', ['channelVideo'=>$video,'publication'=>$publication]) }}">
                                                @csrf
                                                <button class="mini-btn retry" type="submit">Retry / Recheck Setup</button>
                                            </form>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="publication-note">No external destinations selected for this video.</div>
                        @endforelse
                    </div>
                </div>

                <div class="channel-actions">
                    @if($youtubeUrl)
                        <a class="channel-link youtube" href="{{ $youtubeUrl }}" target="_blank" rel="noopener noreferrer">Open YouTube</a>
                    @endif

                    @if($permissions['manage'] ?? false)
                        <a class="channel-link" href="{{ route('admin.our_channel.edit', ['channelVideo'=>$video]) }}">Edit Metadata</a>

                        @if($video->status === 'DRAFT')
                            <form method="POST" action="{{ route('admin.our_channel.publication', ['channelVideo'=>$video]) }}" onsubmit="return confirm('Publish in TradeX and queue enabled external destinations?');">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="action" value="PUBLISH">
                                <textarea name="reason" maxlength="1000" placeholder="Optional audit reason"></textarea>
                                <button class="channel-btn publish" type="submit">Publish / Queue</button>
                            </form>

                            <form method="POST" action="{{ route('admin.our_channel.destroy', ['channelVideo'=>$video]) }}" onsubmit="return confirm('Delete this TradeX video record? Published external posts block deletion.');">
                                @csrf
                                @method('DELETE')
                                <button class="channel-btn delete" type="submit">Delete</button>
                            </form>
                        @elseif($video->status === 'PUBLISHED')
                            <form method="POST" action="{{ route('admin.our_channel.publication', ['channelVideo'=>$video]) }}" onsubmit="return confirm('Unpublish from TradeX? Existing external platform posts will remain online.');">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="action" value="UNPUBLISH">
                                <textarea name="reason" maxlength="1000" placeholder="Optional audit reason"></textarea>
                                <button class="channel-btn unpublish" type="submit">Unpublish TradeX Only</button>
                            </form>
                        @endif
                    @endif
                </div>
            </article>

            @if($loop->last)</div>@endif
        @empty
            <div class="channel-empty">
                <strong>No Our Channel videos yet</strong>
                <span>Upload one protected master video and select the destinations TradeX should track.</span>

                @if($permissions['manage'] ?? false)
                    <a class="primary-btn" href="{{ route('admin.our_channel.create') }}">+ Upload First Video</a>
                @endif
            </div>
        @endforelse
    </div>
</section>
@endsection
