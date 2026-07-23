@props(['booking'])

@php
    // Poll only while a verified DOKU notification can still change the state.
    $transient = in_array($booking->status->value, ['held', 'pending_payment', 'payment_review'], true);
    $current = $booking->status->value.'|'.$booking->payment_status->value;
@endphp

@if ($transient)
    <div
        data-payment-status-poller="{{ $booking->code }}"
        data-current-status="{{ $current }}"
        data-status-url="{{ route('booking.status', $booking->code) }}"
    ></div>
    <script>
        (() => {
            const node = document.querySelector(
                '[data-payment-status-poller="{{ $booking->code }}"]'
            );

            if (!node || node.dataset.pollingStarted === 'true') return;
            node.dataset.pollingStarted = 'true';

            let attempts = 0;
            let stopped = false;

            const check = async () => {
                // Stop after roughly ten minutes so abandoned tabs do not poll forever.
                if (stopped || attempts++ >= 200) return;

                try {
                    const separator = node.dataset.statusUrl.includes('?') ? '&' : '?';
                    const response = await fetch(
                        node.dataset.statusUrl + separator + '_poll=' + Date.now(),
                        {
                            headers: { 'Accept': 'application/json' },
                            credentials: 'same-origin',
                            cache: 'no-store',
                        }
                    );

                    if (response.ok) {
                        const data = await response.json();
                        const latest = data.status + '|' + data.payment_status;

                        if (latest !== node.dataset.currentStatus) {
                            stopped = true;
                            window.location.reload();
                            return;
                        }
                    }
                } catch (_) {
                    // Retry temporary network failures on the next tick.
                }

                if (!stopped) window.setTimeout(check, 3000);
            };

            // Check immediately: the webhook may finish during DOKU's redirect.
            check();
        })();
    </script>
@endif
