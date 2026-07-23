<div class="mx-auto max-w-6xl px-4 py-10">
    {{-- Steps --}}
    <ol class="mb-8 flex flex-wrap items-center gap-x-2 gap-y-2 text-sm">
        @foreach ([1 => 'Pilih Kamar', 2 => 'Data Tamu', 3 => 'Tinjau', 4 => 'Pembayaran', 5 => 'Konfirmasi'] as $i => $label)
            @php
                $current = $i === 1 ? true : ($i === 2 ? $step >= 1 : ($i === 3 ? $step >= 2 : false));
                $isActive = ($step === 1 && $i === 2) || ($step === 2 && $i === 3);
            @endphp
            <li class="flex items-center gap-2">
                <span @class([
                    'grid h-7 w-7 place-items-center rounded-full text-xs font-semibold',
                    'bg-brand-600 text-white' => $isActive,
                    'bg-emerald-100 text-emerald-700' => $current && ! $isActive,
                    'bg-slate-200 text-slate-500' => ! $current && ! $isActive,
                ])>{{ $i }}</span>
                <span @class(['font-medium text-slate-900' => $isActive, 'text-slate-500' => ! $isActive])>{{ $label }}</span>
                @if (! $loop->last)<span class="mx-1 text-slate-300">›</span>@endif
            </li>
        @endforeach
    </ol>

    @if ($dateError)
        <div class="rounded-xl border border-rose-200 bg-rose-50 px-5 py-4 text-sm text-rose-700">{{ $dateError }}</div>
    @else
        <div class="grid gap-8 lg:grid-cols-3">
            {{-- Left: form / review --}}
            <div class="lg:col-span-2">
                @if ($bookingError)
                    <div class="mb-5 rounded-xl border border-rose-200 bg-rose-50 px-5 py-4 text-sm text-rose-700">
                        {{ $bookingError }}
                        <a href="{{ route('search') }}" class="ml-1 font-medium underline">Cari tanggal lain</a>
                    </div>
                @endif

                @if ($available < $rooms)
                    <div class="mb-5 rounded-xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-800">
                        Hanya tersisa {{ $available }} kamar untuk tanggal ini. Silakan kurangi jumlah kamar atau ubah tanggal.
                    </div>
                @endif

                @if ($step === 1)
                    <div class="card p-6">
                        <h2 class="font-display text-xl font-semibold text-slate-900">Data Tamu</h2>

                        @guest
                            <div class="mt-4 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                                Anda memesan sebagai tamu.
                                <a href="{{ route('login') }}" class="font-medium text-brand-700 hover:underline">Masuk</a> atau
                                <a href="{{ route('register') }}" class="font-medium text-brand-700 hover:underline">daftar</a>
                                untuk menyimpan riwayat pemesanan.
                            </div>
                        @endguest

                        <div class="mt-5 grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="label">Nama Lengkap *</label>
                                <input type="text" wire:model="customer_name" class="input @error('customer_name') border-rose-400 @enderror">
                                @error('customer_name') <p class="field-error">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="label">Email *</label>
                                <input type="email" wire:model="customer_email" class="input @error('customer_email') border-rose-400 @enderror">
                                @error('customer_email') <p class="field-error">{{ $message }}</p> @enderror
                                <p class="mt-1 text-xs text-slate-400">Kode pemesanan dikirim ke email ini.</p>
                            </div>
                            <div>
                                <label class="label">Nomor Telepon *</label>
                                <input type="text" wire:model="customer_phone" class="input @error('customer_phone') border-rose-400 @enderror">
                                @error('customer_phone') <p class="field-error">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="label">Negara</label>
                                <input type="text" wire:model="customer_country" class="input">
                            </div>
                            <div class="sm:col-span-2">
                                <label class="label">Nama Tamu Menginap</label>
                                @foreach ($guest_names as $i => $g)
                                    <input type="text" wire:model="guest_names.{{ $i }}" wire:key="guest-{{ $i }}" class="input mb-2" placeholder="Nama tamu {{ $i + 1 }}">
                                @endforeach
                                <p class="text-xs text-slate-400">Kosongkan bila sama dengan nama pemesan.</p>
                            </div>
                            <div>
                                <label class="label">Perkiraan Jam Tiba</label>
                                <input type="time" wire:model="arrival_time" class="input">
                            </div>
                            <div class="sm:col-span-2">
                                <label class="label">Permintaan Khusus</label>
                                <textarea wire:model="special_request" rows="3" class="input" placeholder="Contoh: kamar lantai bawah, tempat tidur terpisah…"></textarea>
                            </div>
                        </div>

                        <label class="mt-5 flex items-start gap-2 text-sm text-slate-700">
                            <input type="checkbox" wire:model="terms" class="mt-0.5 rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                            <span>Saya menyetujui syarat &amp; ketentuan serta kebijakan pembatalan hotel. *</span>
                        </label>
                        @error('terms') <p class="field-error">{{ $message }}</p> @enderror

                        <div class="mt-6 flex justify-end">
                            <button wire:click="goToReview" class="btn-primary">Lanjut ke Tinjauan</button>
                        </div>
                    </div>
                @else
                    <div class="card p-6">
                        <h2 class="font-display text-xl font-semibold text-slate-900">Tinjau Pemesanan</h2>

                        <dl class="mt-5 grid gap-3 text-sm sm:grid-cols-2">
                            <div><dt class="text-slate-500">Nama</dt><dd class="font-medium text-slate-900">{{ $customer_name }}</dd></div>
                            <div><dt class="text-slate-500">Email</dt><dd class="font-medium text-slate-900">{{ $customer_email }}</dd></div>
                            <div><dt class="text-slate-500">Telepon</dt><dd class="font-medium text-slate-900">{{ $customer_phone }}</dd></div>
                            <div><dt class="text-slate-500">Negara</dt><dd class="font-medium text-slate-900">{{ $customer_country ?: '—' }}</dd></div>
                            @if ($arrival_time)
                                <div><dt class="text-slate-500">Perkiraan tiba</dt><dd class="font-medium text-slate-900">{{ $arrival_time }}</dd></div>
                            @endif
                        </dl>

                        @if ($special_request)
                            <div class="mt-4 rounded-lg bg-slate-50 px-4 py-3 text-sm text-slate-600">
                                <span class="font-medium text-slate-700">Permintaan khusus:</span> {{ $special_request }}
                            </div>
                        @endif

                        {{-- Nightly breakdown --}}
                        <h3 class="mt-6 font-display text-lg font-semibold text-slate-900">Rincian Harga per Malam</h3>
                        <div class="mt-3 overflow-hidden rounded-xl border border-slate-200">
                            <table class="min-w-full divide-y divide-slate-200 text-sm">
                                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                                    <tr><th class="px-4 py-2">Tanggal</th><th class="px-4 py-2 text-right">Tarif</th><th class="px-4 py-2 text-right">Penyesuaian</th><th class="px-4 py-2 text-right">Diskon</th></tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @foreach ($quote->nights as $night)
                                        <tr>
                                            <td class="px-4 py-2 text-slate-700">{{ \Carbon\Carbon::parse($night->date)->translatedFormat('D, d M Y') }}</td>
                                            <td class="px-4 py-2 text-right text-slate-700">{{ rupiah($night->base) }}</td>
                                            <td class="px-4 py-2 text-right text-slate-500">{{ $night->adjustment ? '+'.rupiah($night->adjustment) : '—' }}</td>
                                            <td class="px-4 py-2 text-right text-emerald-600">{{ $night->discount ? '−'.rupiah($night->discount) : '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-6 flex flex-wrap justify-between gap-3">
                            <button wire:click="backToDetails" class="btn-outline">← Kembali</button>
                            <button wire:click="confirmBooking" class="btn-primary" wire:loading.attr="disabled" wire:target="confirmBooking">
                                <span wire:loading.remove wire:target="confirmBooking">Konfirmasi &amp; Lanjut ke Pembayaran</span>
                                <span wire:loading wire:target="confirmBooking">Memproses…</span>
                            </button>
                        </div>
                        <p class="mt-3 text-right text-xs text-slate-400">Kamar akan ditahan selama 30 menit untuk menyelesaikan pembayaran.</p>
                    </div>
                @endif
            </div>

            {{-- Right: sticky summary --}}
            <div class="lg:col-span-1">
                <div class="card sticky top-24 p-6">
                    <h3 class="font-display text-lg font-semibold text-slate-900">{{ $roomType->name }}</h3>
                    <p class="mt-1 text-sm text-slate-500">
                        {{ \Carbon\Carbon::parse($checkIn)->translatedFormat('d M Y') }} →
                        {{ \Carbon\Carbon::parse($checkOut)->translatedFormat('d M Y') }}
                    </p>
                    <p class="text-sm text-slate-500">{{ $quote->nightsCount }} malam · {{ $rooms }} kamar · {{ $adults }} dewasa{{ $children ? ", {$children} anak" : '' }}</p>

                    {{-- Promo --}}
                    <div class="mt-5 border-t border-slate-100 pt-4">
                        <label class="label">Kode Promo</label>
                        <div class="flex gap-2">
                            <input type="text" wire:model="promo_code" class="input" placeholder="Masukkan kode">
                            <button wire:click="applyPromo" class="btn-outline shrink-0 text-sm">Pakai</button>
                        </div>
                        @if ($promoMessage)
                            <p class="mt-1 text-xs {{ $promoApplied ? 'text-emerald-600' : 'text-rose-600' }}">{{ $promoMessage }}</p>
                        @endif
                    </div>

                    <dl class="mt-4 space-y-2 border-t border-slate-100 pt-4 text-sm">
                        <div class="flex justify-between"><dt class="text-slate-600">Subtotal</dt><dd class="text-slate-900">{{ rupiah($quote->subtotalBeforeDiscount) }}</dd></div>
                        @if ($quote->discountTotal > 0)
                            <div class="flex justify-between text-emerald-600">
                                <dt>Diskon{{ $quote->promotion?->code ? ' ('.$quote->promotion->code.')' : '' }}</dt>
                                <dd>−{{ rupiah($quote->discountTotal) }}</dd>
                            </div>
                        @endif
                        <div class="flex justify-between"><dt class="text-slate-600">Pajak</dt><dd class="text-slate-900">{{ rupiah($quote->taxTotal) }}</dd></div>
                        <div class="flex justify-between"><dt class="text-slate-600">Biaya layanan</dt><dd class="text-slate-900">{{ rupiah($quote->serviceTotal) }}</dd></div>
                        <div class="flex justify-between border-t border-slate-200 pt-3 text-base font-semibold">
                            <dt class="text-slate-900">Total</dt><dd class="font-display text-slate-900">{{ rupiah($quote->grandTotal) }}</dd>
                        </div>
                    </dl>

                    <p class="mt-4 text-xs text-slate-400">
                        Total dihitung di server dan tidak dapat diubah dari browser.
                    </p>
                </div>
            </div>
        </div>
    @endif
</div>
