<x-layouts.public :title="'Pemesanan '.$booking->code">
    <section class="mx-auto max-w-4xl px-4 py-10">
        {{-- Header --}}
        <div class="card p-6 print:shadow-none">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-sm text-slate-500">Kode Pemesanan</p>
                    <p class="font-mono font-display text-2xl font-semibold text-slate-900">{{ $booking->code }}</p>
                    <p class="mt-1 text-sm text-slate-500">Dibuat {{ $booking->created_at->translatedFormat('d M Y, H:i') }} WITA</p>
                </div>
                <div class="flex flex-col items-start gap-2 sm:items-end">
                    <span class="badge {{ $booking->status->badgeClasses() }}">{{ $booking->status->label() }}</span>
                    <span class="badge {{ $booking->payment_status->badgeClasses() }}">Pembayaran: {{ $booking->payment_status->label() }}</span>
                    <a href="{{ route('booking.invoice', $booking->code) }}" target="_blank" rel="noopener"
                       class="text-sm font-medium text-brand-700 underline underline-offset-2 hover:text-brand-800">
                        Lihat / Cetak Invoice
                    </a>
                </div>
            </div>

            {{-- Payment countdown / call to action --}}
            @if ($booking->isPayable())
                <div class="mt-5 rounded-xl border border-amber-200 bg-amber-50 px-5 py-4"
                     x-data="{ remaining: {{ max(0, $booking->held_until->diffInSeconds(now(), false) * -1) }} }"
                     x-init="setInterval(() => { if (remaining > 0) remaining-- }, 1000)">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="text-sm font-medium text-amber-900">Selesaikan pembayaran sebelum kamar dilepas</p>
                            <p class="mt-0.5 text-sm text-amber-700">
                                Sisa waktu:
                                <span class="font-mono font-semibold"
                                      x-text="remaining > 0
                                        ? String(Math.floor(remaining / 60)).padStart(2,'0') + ':' + String(remaining % 60).padStart(2,'0')
                                        : 'habis'"></span>
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            @if (Route::has('payment.start'))
                                <form method="POST" action="{{ route('payment.start', $booking->code) }}">
                                    @csrf
                                    <button type="submit" class="btn-primary" data-loading-text="Menyiapkan pembayaran…">Bayar Online</button>
                                </form>
                            @endif
                            @if ($cashEnabled)
                                <form method="POST" action="{{ route('payment.cash', $booking->code) }}"
                                      onsubmit="return confirm('Konfirmasi pemesanan dan bayar tunai saat tiba di hotel?')">
                                    @csrf
                                    <button type="submit" class="btn-outline">Bayar di Tempat (Tunai)</button>
                                </form>
                            @endif
                        </div>
                    </div>
                    <p class="mt-2 text-xs text-amber-600">
                        Waktu di server adalah acuan resmi.
                        @if ($cashEnabled)
                            Memilih <strong>bayar di tempat</strong> langsung mengunci kamar Anda — pembayaran dilakukan di resepsionis saat check-in.
                        @endif
                    </p>
                </div>
            @elseif ($booking->status->value === 'expired')
                <div class="mt-5 rounded-xl border border-rose-200 bg-rose-50 px-5 py-4 text-sm text-rose-700">
                    Masa tahan pemesanan ini telah berakhir dan kamar telah dilepas. Silakan buat pemesanan baru.
                    <a href="{{ route('search') }}" class="font-medium underline">Cari kamar</a>
                </div>
            @elseif ($booking->status->value === 'payment_review')
                <div class="mt-5 rounded-xl border border-orange-200 bg-orange-50 px-5 py-4 text-sm text-orange-800">
                    Pembayaran sedang diperiksa oleh tim kami. Anda akan menerima email setelah verifikasi selesai.
                </div>
            @elseif ($booking->status->value === 'confirmed')
                @if ($cashOutstanding > 0)
                    {{-- Reserved but not yet paid: a pay-at-hotel booking. --}}
                    <div class="mt-5 rounded-xl border border-sky-200 bg-sky-50 px-5 py-4 text-sm text-sky-800">
                        <p class="font-medium">Kamar Anda sudah dikunci. Pembayaran dilakukan di tempat.</p>
                        <p class="mt-1">
                            Bawa kode pemesanan <span class="font-mono font-semibold">{{ $booking->code }}</span> dan
                            siapkan <span class="font-semibold">{{ rupiah($cashOutstanding) }}</span> untuk dibayarkan
                            di resepsionis saat check-in.
                        </p>
                    </div>
                @else
                    <div class="mt-5 rounded-xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm text-emerald-800">
                        Pemesanan Anda telah dikonfirmasi. Sampai jumpa di New Poppies Senggigi!
                    </div>
                @endif
            @endif

            @if (session('success'))
                <div class="mt-5 rounded-xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm text-emerald-800">
                    {{ session('success') }}
                </div>
            @endif

            {{-- While the status can still change (awaiting payment / under review),
                 refresh this page automatically when the notification lands. --}}
            <x-payment-status-poller :booking="$booking" />
        </div>

        {{-- Stay details --}}
        <div class="mt-6 grid gap-6 md:grid-cols-3">
            <div class="card p-6 md:col-span-2">
                <h2 class="font-display text-lg font-semibold text-slate-900">Detail Menginap</h2>
                <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-2">
                    <div><dt class="text-slate-500">Check-in</dt><dd class="font-medium text-slate-900">{{ $booking->check_in_date->translatedFormat('l, d M Y') }}</dd></div>
                    <div><dt class="text-slate-500">Check-out</dt><dd class="font-medium text-slate-900">{{ $booking->check_out_date->translatedFormat('l, d M Y') }}</dd></div>
                    <div><dt class="text-slate-500">Lama menginap</dt><dd class="font-medium text-slate-900">{{ $booking->nights }} malam</dd></div>
                    <div><dt class="text-slate-500">Tamu</dt><dd class="font-medium text-slate-900">{{ $booking->adults }} dewasa{{ $booking->children ? ", {$booking->children} anak" : '' }}</dd></div>
                </dl>

                <h3 class="mt-6 font-display font-semibold text-slate-900">Kamar</h3>
                @foreach ($booking->items as $item)
                    <div class="mt-2 rounded-lg bg-slate-50 px-4 py-3 text-sm">
                        <p class="font-medium text-slate-900">{{ $item->room_type_name }} × {{ $item->rooms }} kamar</p>
                    </div>
                @endforeach

                @if ($booking->special_request)
                    <h3 class="mt-6 font-display font-semibold text-slate-900">Permintaan Khusus</h3>
                    <p class="mt-1 text-sm text-slate-600">{{ $booking->special_request }}</p>
                @endif

                @if ($booking->cancellation_policy)
                    <h3 class="mt-6 font-display font-semibold text-slate-900">Kebijakan Pembatalan</h3>
                    <p class="mt-1 text-sm text-slate-600">{{ data_get($booking->cancellation_policy, 'described') }}</p>
                @endif
            </div>

            {{-- Price summary --}}
            <div class="card h-fit p-6">
                <h2 class="font-display text-lg font-semibold text-slate-900">Rincian Biaya</h2>
                <dl class="mt-4 space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-600">Subtotal</dt><dd class="text-slate-900">{{ rupiah($booking->subtotal_amount) }}</dd></div>
                    @if ($booking->discount_amount > 0)
                        <div class="flex justify-between text-emerald-600"><dt>Diskon</dt><dd>−{{ rupiah($booking->discount_amount) }}</dd></div>
                    @endif
                    <div class="flex justify-between"><dt class="text-slate-600">Pajak</dt><dd class="text-slate-900">{{ rupiah($booking->tax_amount) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-600">Layanan</dt><dd class="text-slate-900">{{ rupiah($booking->service_amount) }}</dd></div>
                    <div class="flex justify-between border-t border-slate-200 pt-3 text-base font-semibold">
                        <dt class="text-slate-900">Total</dt><dd class="font-display text-slate-900">{{ rupiah($booking->total_amount) }}</dd>
                    </div>
                </dl>

                <div class="mt-6 space-y-2 print:hidden" x-data="{ cancelling: false }">
                    <button onclick="window.print()" class="btn-outline w-full">Cetak Konfirmasi</button>

                    @if (in_array($booking->status->value, ['held', 'pending_payment', 'confirmed']))
                        <button x-show="!cancelling" @click="cancelling = true" class="btn-outline w-full text-rose-600">
                            Batalkan Pemesanan
                        </button>

                        <form x-show="cancelling" x-cloak method="POST" action="{{ route('booking.cancel', $booking->code) }}" class="space-y-2 text-left">
                            @csrf
                            <label class="label">Alasan pembatalan</label>
                            <textarea name="reason" rows="2" required minlength="5" class="input"
                                      placeholder="Contoh: perubahan rencana perjalanan">{{ old('reason') }}</textarea>
                            @error('reason') <p class="field-error">{{ $message }}</p> @enderror
                            <p class="text-xs text-slate-400">
                                Pengembalian dana mengikuti kebijakan pembatalan dan diproses terpisah oleh tim kami.
                            </p>
                            <div class="flex gap-2">
                                <button type="button" @click="cancelling = false" class="btn-outline flex-1">Batal</button>
                                <button type="submit" class="btn-primary flex-1 !bg-rose-600 hover:!bg-rose-700">Konfirmasi</button>
                            </div>
                        </form>
                    @endif
                </div>

                @if (session('error'))
                    <p class="mt-3 rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-700">{{ session('error') }}</p>
                @endif
            </div>
        </div>

        {{-- Nightly breakdown --}}
        <div class="card mt-6 overflow-hidden">
            <div class="border-b border-slate-100 px-6 py-4">
                <h2 class="font-display text-lg font-semibold text-slate-900">Rincian per Malam</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-6 py-2">Tanggal</th>
                            <th class="px-6 py-2 text-right">Tarif</th>
                            <th class="px-6 py-2 text-right">Penyesuaian</th>
                            <th class="px-6 py-2 text-right">Diskon</th>
                            <th class="px-6 py-2 text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($booking->items as $item)
                            @foreach ($item->nights as $night)
                                <tr>
                                    <td class="px-6 py-2 text-slate-700">{{ $night->stay_date->translatedFormat('D, d M Y') }}</td>
                                    <td class="px-6 py-2 text-right text-slate-700">{{ rupiah($night->base_amount) }}</td>
                                    <td class="px-6 py-2 text-right text-slate-500">{{ $night->adjustment_amount ? '+'.rupiah($night->adjustment_amount) : '—' }}</td>
                                    <td class="px-6 py-2 text-right text-emerald-600">{{ $night->discount_amount ? '−'.rupiah($night->discount_amount) : '—' }}</td>
                                    <td class="px-6 py-2 text-right font-medium text-slate-900">{{ rupiah($night->final_amount) }}</td>
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</x-layouts.public>
