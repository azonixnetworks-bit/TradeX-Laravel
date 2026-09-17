<div class="panel" style="margin-top:18px;">
    <div class="panel-header">
        <div><div class="panel-eyebrow">LOST / REPLACED CARD</div><h2>NFC Replacement History</h2></div>
        <span>Old card is revoked before the replacement becomes active</span>
    </div>
    <div class="panel-body">
        @if($canManageAccess)
            @php($activeNfc = $client->accessCredentials->where('credential_type', 'NFC')->where('status', 'ACTIVE'))
            @forelse($activeNfc as $credential)
                <form method="POST" action="{{ route('admin.trading_floor.clients.credentials.replace', [$client, $credential]) }}" style="display:grid;grid-template-columns:180px minmax(240px,1fr) minmax(260px,1fr) auto;gap:12px;align-items:end;margin-bottom:14px;">
                    @csrf
                    <div class="form-group" style="margin:0;">
                        <label>Current Protected Card</label>
                        <input type="text" value="{{ substr($credential->credential_reference, 0, 12) }}…" disabled>
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label>New NFC Card UID</label>
                        <input name="new_credential_reference" type="password" autocomplete="off" required minlength="2" maxlength="255" placeholder="Scan replacement card">
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label>Replacement Reason</label>
                        <input name="replacement_reason" required maxlength="2000" placeholder="Lost, stolen, damaged or replaced">
                    </div>
                    <button class="btn btn-primary" type="submit">Replace Card</button>
                </form>
            @empty
                <p style="margin-top:0;opacity:.7;">No active NFC card is available for replacement.</p>
            @endforelse
        @endif

        <div style="overflow-x:auto;margin-top:12px;">
            <table class="data-table" style="width:100%;">
                <thead><tr><th>Replaced At</th><th>Old Card</th><th>New Card</th><th>Reason</th><th>Admin</th></tr></thead>
                <tbody>
                    @forelse($client->credentialReplacements as $replacement)
                        <tr>
                            <td>{{ $replacement->replaced_at?->format('Y-m-d H:i:s') }}</td>
                            <td><code>{{ substr((string) $replacement->oldCredential?->credential_reference, 0, 12) }}…</code></td>
                            <td><code>{{ substr((string) $replacement->newCredential?->credential_reference, 0, 12) }}…</code></td>
                            <td>{{ $replacement->replacement_reason }}</td>
                            <td>{{ $replacement->replacedByAdmin?->name ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" style="padding:22px;text-align:center;opacity:.7;">No NFC replacement history yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <p style="margin:14px 0 0;opacity:.64;font-size:12px;">Raw NFC card UIDs are never stored. Replacement history links only protected credential records.</p>
    </div>
</div>
