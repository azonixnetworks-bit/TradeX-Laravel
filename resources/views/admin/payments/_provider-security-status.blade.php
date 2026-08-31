<div class="pa-provider-item">
    <span>Client ID</span>
    <strong>Client ID: {{ $accounts['paypal']['client_id_configured'] ? 'Configured' : 'Missing' }}</strong>
</div>

<div class="pa-provider-item">
    <span>Client Secret / Webhook</span>
    <strong>
        Client Secret: {{ $accounts['paypal']['client_secret_configured'] ? 'Configured' : 'Missing' }}
        ·
        Webhook: {{ $accounts['paypal']['webhook_configured'] ? 'Configured' : 'Missing' }}
    </strong>
</div>
