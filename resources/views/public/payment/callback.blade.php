<x-layouts.public title="Status Pembayaran">
    <section class="mx-auto max-w-2xl px-4 py-16">
        @php $confirmed = $booking && $booking->status->value === 'confirmed'; @endphp

        <div class="card p-8 text-center">
            @if ($confirmed)
                <div class="mx-auto grid h-16 w-16 place-items-center rounded-full bg-emerald-100 text-3xl text-emerald-600">✓</div>
                <h1 class="mt-5 font-display text-2xl font-semibold text-slate-900">Pembayaran Berhasil</h1>
                <p class="mt-2 text-slate-600">
                    Pemesanan <span class="font-mono font-medium text-slate-900">{{ $booking->code }}</span> telah dikonfirmasi.
                    Detail konfirmasi juga kami kirim ke email Anda.
                </p>
            @else
                {{-- The browser callback is never proof of payment (§19). --}}
                <div class="mx-auto grid h-16 w-16 place-items-center rounded-full bg-amber-100 text-3xl text-amber-600">⏳</div>
                <h1 class="mt-5 font-display text-2xl font-semibold text-slate-900">Pembayaran sedang diperiksa</h1>
                <p class="mt-2 text-slate-600">
                    Kami sedang menunggu konfirmasi resmi dari penyedia pembayaran. Proses ini biasanya
                    berlangsung beberapa saat. Status pemesanan akan diperbarui secara otomatis setelah
                    pembayaran terverifikasi.
                </p>
                <p class="mt-3 text-sm text-slate-500">
                    Jangan menutup halaman ini sebagai bukti pembayaran — status resmi hanya ditentukan
                    setelah verifikasi di server kami.
                </p>

                {{-- Refreshes this page automatically the moment the verified
                     notification confirms the booking — no manual reload. --}}
                @if ($booking)
                    <x-payment-status-poller :booking="$booking" />
                @endif
            @endif

            <div class="mt-7 flex flex-wrap justify-center gap-3">
                @if ($booking)
                    <a href="{{ route('booking.show', $booking->code) }}" class="btn-primary">Lihat Status Pemesanan</a>
                @else
                    <a href="{{ route('booking.lookup.form') }}" class="btn-primary">Cek Pemesanan Saya</a>
                @endif
                <a href="{{ route('home') }}" class="btn-outline">Kembali ke Beranda</a>
            </div>
        </div>
    </section>
</x-layouts.public>
