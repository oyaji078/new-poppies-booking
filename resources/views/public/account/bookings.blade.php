<x-layouts.public title="Pemesanan Saya">
    <section class="mx-auto max-w-4xl px-4 py-10">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="font-display text-2xl font-semibold text-slate-900">Pemesanan Saya</h1>
                <p class="mt-1 text-sm text-slate-500">Halo {{ $user->name }}, berikut riwayat pemesanan Anda.</p>
            </div>
            <a href="{{ route('search') }}" class="btn-primary">Pesan Kamar Baru</a>
        </div>

        @if ($bookings->isEmpty())
            <div class="mt-8 card p-10 text-center">
                <div class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-slate-100 text-2xl">🏝️</div>
                <p class="mt-4 font-medium text-slate-900">Belum ada pemesanan</p>
                <p class="mt-1 text-sm text-slate-500">Pemesanan yang Anda buat saat masuk akan muncul di sini.</p>
                <a href="{{ route('search') }}" class="mt-5 inline-block btn-primary">Cari Kamar</a>
            </div>
        @else
            <div class="mt-6 space-y-4">
                @foreach ($bookings as $booking)
                    <div wire:key="bk-{{ $booking->id }}" class="card p-5">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="font-mono font-display text-lg font-semibold text-slate-900">{{ $booking->code }}</span>
                                    <span class="badge {{ $booking->status->badgeClasses() }}">{{ $booking->status->label() }}</span>
                                </div>
                                <p class="mt-0.5 text-xs text-slate-400">Dibuat {{ $booking->created_at->translatedFormat('d M Y, H:i') }} WITA</p>
                            </div>
                            <div class="text-right">
                                <p class="text-xs text-slate-500">Total</p>
                                <p class="font-display text-lg font-semibold text-slate-900">{{ rupiah($booking->total_amount) }}</p>
                            </div>
                        </div>

                        <div class="mt-4 grid gap-3 border-t border-slate-100 pt-4 text-sm sm:grid-cols-3">
                            <div>
                                <dt class="text-xs text-slate-400">Kamar</dt>
                                <dd class="font-medium text-slate-800">{{ $booking->items->first()?->room_type_name ?? '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-slate-400">Menginap</dt>
                                <dd class="font-medium text-slate-800">
                                    {{ $booking->check_in_date->translatedFormat('d M') }} – {{ $booking->check_out_date->translatedFormat('d M Y') }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs text-slate-400">Durasi</dt>
                                <dd class="font-medium text-slate-800">{{ $booking->nights }} malam · {{ $booking->rooms }} kamar</dd>
                            </div>
                        </div>

                        <div class="mt-4 flex flex-wrap gap-2">
                            <a href="{{ route('booking.show', $booking->code) }}" class="btn-outline">Detail</a>

                            @if (in_array($booking->status->value, ['confirmed', 'checked_in', 'checked_out'], true))
                                <a href="{{ route('booking.invoice', $booking->code) }}" target="_blank" rel="noopener" class="btn-ghost">Invoice</a>
                            @endif

                            @if ($booking->is_payable && Route::has('payment.start'))
                                <form method="POST" action="{{ route('payment.start', $booking->code) }}">
                                    @csrf
                                    <button type="submit" class="btn-primary" data-loading-text="Menyiapkan…">Bayar Sekarang</button>
                                </form>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-6">{{ $bookings->links() }}</div>
        @endif
    </section>
</x-layouts.public>
