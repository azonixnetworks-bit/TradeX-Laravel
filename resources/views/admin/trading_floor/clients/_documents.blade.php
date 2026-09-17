<div class="panel" style="margin-top:18px;">
    <div class="panel-header">
        <div><div class="panel-eyebrow">CUSTOMER DOCUMENTS</div><h2>Private Document Vault</h2></div>
        <span>PDF / JPG / PNG · private storage</span>
    </div>
    <div class="panel-body">
        @if($canManageClient)
            <form method="POST" action="{{ route('admin.trading_floor.clients.documents.store', $client) }}" enctype="multipart/form-data" style="display:grid;grid-template-columns:180px minmax(220px,1fr) minmax(260px,1fr) auto;gap:12px;align-items:end;margin-bottom:20px;">
                @csrf
                <div class="form-group" style="margin:0;">
                    <label>Document Type</label>
                    <select name="document_type" required>
                        @foreach(['NIC','PASSPORT','AGREEMENT','ACCESS','OTHER'] as $type)
                            <option value="{{ $type }}">{{ $type }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group" style="margin:0;">
                    <label>Title</label>
                    <input name="title" required maxlength="255" placeholder="e.g. Signed floor agreement">
                </div>
                <div class="form-group" style="margin:0;">
                    <label>Private File</label>
                    <input name="document" type="file" required accept=".pdf,.jpg,.jpeg,.png">
                </div>
                <button class="btn btn-primary" type="submit">Upload Document</button>
            </form>
        @endif

        <div style="overflow-x:auto;">
            <table class="data-table" style="width:100%;">
                <thead><tr><th>Type / Title</th><th>File</th><th>Size</th><th>Uploaded</th><th>Status</th><th style="text-align:right;">Action</th></tr></thead>
                <tbody>
                    @forelse($client->documents as $document)
                        <tr>
                            <td><strong>{{ $document->document_type }}</strong><div style="opacity:.65;font-size:12px;">{{ $document->title }}</div></td>
                            <td>{{ $document->original_name }}</td>
                            <td>{{ number_format($document->file_size / 1024, 1) }} KB</td>
                            <td>{{ $document->created_at?->format('Y-m-d H:i') }}<div style="opacity:.65;font-size:12px;">{{ $document->uploadedByAdmin?->name ?: '—' }}</div></td>
                            <td><span class="badge">{{ $document->status }}</span></td>
                            <td style="text-align:right;white-space:nowrap;">
                                <a class="btn btn-secondary" href="{{ route('admin.trading_floor.clients.documents.download', [$client, $document]) }}">Download</a>
                                @if($canManageClient && $document->status === 'ACTIVE')
                                    <form method="POST" action="{{ route('admin.trading_floor.clients.documents.archive', [$client, $document]) }}" style="display:inline-flex;gap:6px;align-items:center;margin-left:6px;">
                                        @csrf
                                        @method('PATCH')
                                        <input name="archived_reason" required maxlength="2000" placeholder="Archive reason" style="min-width:170px;">
                                        <button class="btn btn-secondary" type="submit">Archive</button>
                                    </form>
                                @elseif($document->status === 'ARCHIVED')
                                    <div style="margin-top:5px;opacity:.6;font-size:11px;">{{ $document->archived_reason }}</div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" style="padding:22px;text-align:center;opacity:.7;">No private customer documents uploaded.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <p style="margin:14px 0 0;opacity:.64;font-size:12px;">Documents are not exposed from the public disk. Archive preserves the file and audit metadata instead of deleting it.</p>
    </div>
</div>
