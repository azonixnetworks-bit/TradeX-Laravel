@php
    $adminNotices = [];

    $boundaryNotice = session('admin_notice');

    if (is_array($boundaryNotice)) {
        $adminNotices[] = [
            'type' => (string) ($boundaryNotice['type'] ?? 'info'),
            'title' => (string) ($boundaryNotice['title'] ?? 'TradeX Administration'),
            'message' => (string) ($boundaryNotice['message'] ?? ''),
            'status' => isset($boundaryNotice['status'])
                ? (int) $boundaryNotice['status']
                : null,
            'reference' => filled($boundaryNotice['reference'] ?? null)
                ? (string) $boundaryNotice['reference']
                : null,
        ];
    }

    foreach ([
        'success' => ['success', 'Completed'],
        'warning' => ['warning', 'Attention'],
        'error' => ['error', 'Could not complete'],
        'info' => ['info', 'Information'],
    ] as $sessionKey => [$type, $title]) {
        $message = session($sessionKey);

        if (filled($message)) {
            $adminNotices[] = [
                'type' => $type,
                'title' => $title,
                'message' => (string) $message,
                'status' => null,
                'reference' => null,
            ];
        }
    }

    if ($errors->any()) {
        $firstError = (string) $errors->first();
        $errorCount = $errors->count();

        $adminNotices[] = [
            'type' => 'error',
            'title' => 'Please check the form',
            'message' => $errorCount > 1
                ? $firstError.' (+'.($errorCount - 1).' more field '.(($errorCount - 1) === 1 ? 'issue' : 'issues').')'
                : $firstError,
            'status' => 422,
            'reference' => null,
        ];
    }
@endphp

@if($adminNotices !== [])
    <div
        class="admin-toast-stack"
        aria-live="polite"
        aria-relevant="additions"
    >
        @foreach($adminNotices as $notice)
            @php
                $toastType = in_array(
                    $notice['type'],
                    ['success', 'warning', 'error', 'info'],
                    true
                )
                    ? $notice['type']
                    : 'info';

                $timeout = match ($toastType) {
                    'success' => 5500,
                    'info' => 7000,
                    'warning' => 9500,
                    'error' => 12000,
                    default => 7000,
                };
            @endphp

            <section
                class="admin-toast admin-toast-{{ $toastType }}"
                role="{{ $toastType === 'error' ? 'alert' : 'status' }}"
                data-admin-toast
                data-timeout="{{ $timeout }}"
            >
                <div class="admin-toast-indicator" aria-hidden="true"></div>

                <div class="admin-toast-copy">
                    <div class="admin-toast-heading-row">
                        <strong>{{ $notice['title'] }}</strong>

                        @if($notice['status'])
                            <span>HTTP {{ $notice['status'] }}</span>
                        @endif
                    </div>

                    <p>{{ $notice['message'] }}</p>

                    @if($notice['reference'])
                        <code>Reference: {{ $notice['reference'] }}</code>
                    @endif
                </div>

                <button
                    class="admin-toast-close"
                    type="button"
                    aria-label="Dismiss notification"
                    data-admin-toast-close
                >
                    ×
                </button>

                <div
                    class="admin-toast-progress"
                    aria-hidden="true"
                ></div>
            </section>
        @endforeach
    </div>
@endif
