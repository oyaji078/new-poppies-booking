@props(['booking'])

@php
    // Only poll while the status can still change on its own — i.e. while a DOKU
    // notification could arrive. Settled states (confirmed, expired, cancelled,
    // checked in/out, no-show) never need it.
    $transient = in_array($booking->status->value, ['held', 'pending_payment', 'payment_review'], true);
    $current = $booking->status->value.'|'.$booking->payment_status->value;
@endphp

@if ($transient)
    <div
        data-payment-status-poller="{{ $booking->code }}"
        x-data="{
            current: @js($current),
            url: @js(route('booking.status', $booking->code)),
            tries: 0,
            check() {
                // Give up after ~10 minutes so an abandoned tab stops polling.
                if (this.tries++ > 150) return;
                fetch(this.url, { headers: { 'Accept': 'application/json' } })
                    .then(r => (r.ok ? r.json() : null))
                    .then(d => {
                        if (d && (d.status + '|' + d.payment_status) !== this.current) {
                            // The server has the new state — re-render it authoritatively.
                            window.location.reload();
                        }
                    })
                    .catch(() => {});
            },
        }"
        x-init="setInterval(() => check(), 4000)"
        wire:ignore
    ></div>
@endif
