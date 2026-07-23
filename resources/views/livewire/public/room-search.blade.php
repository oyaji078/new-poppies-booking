<div>
    {{-- Search bar --}}
    <section class="bg-brand-900 py-10">
        <div class="mx-auto max-w-6xl px-4">
            <h1 class="font-display text-2xl font-semibold text-white sm:text-3xl">Cari Kamar</h1>
            <div class="card mt-5 p-4">
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
                    <div class="lg:col-span-1">
                        <label class="label">Check-in</label>
                        <input type="date" wire:model="checkIn" min="{{ now()->toDateString() }}" class="input">
                    </div>
                    <div class="lg:col-span-1">
                        <label class="label">Check-out</label>
                        <input type="date" wire:model="checkOut" min="{{ now()->addDay()->toDateString() }}" class="input">
                    </div>
                    <div>
                        <label class="label">Dewasa</label>
                        <input type="number" wire:model="adults" min="1" max="20" class="input">
                    </div>
                    <div>
                        <label class="label">Anak</label>
                        <input type="number" wire:model="children" min="0" max="20" class="input">
                    </div>
                    <div>
                        <label class="label">Kamar</label>
                        <input type="number" wire:model="rooms" min="1" max="10" class="input">
                    </div>
                    <div class="flex items-end">
                        <button wire:click="search" class="btn-primary w-full">
                            <span wire:loading.remove wire:target="search">Cari</span>
                            <span wire:loading wire:target="search">Mencari…</span>
                        </button>
                    </div>
                </div>
                @error('checkIn') <p class="field-error">{{ $message }}</p> @enderror
                @error('checkOut') <p class="field-error">{{ $message }}</p> @enderror
                @error('adults') <p class="field-error">{{ $message }}</p> @enderror
                @error('rooms') <p class="field-error">{{ $message }}</p> @enderror
            </div>
        </div>
    </section>

    <section class="mx-auto max-w-6xl px-4 py-10">
        {{-- Summary + sort --}}
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <div class="text-sm text-slate-600">
                @if ($nights > 0)
                    <span class="font-medium text-slate-900">{{ \Carbon\Carbon::parse($checkIn)->translatedFormat('d M Y') }}</span>
                    &rarr;
                    <span class="font-medium text-slate-900">{{ \Carbon\Carbon::parse($checkOut)->translatedFormat('d M Y') }}</span>
                    · {{ $nights }} malam · {{ $adults }} dewasa{{ $children ? ", {$children} anak" : '' }} · {{ $rooms }} kamar
                @endif
            </div>
            <select wire:model.live="sort" class="input max-w-[14rem]">
                <option value="price_asc">Harga terendah</option>
                <option value="price_desc">Harga tertinggi</option>
                <option value="capacity">Kapasitas terbesar</option>
            </select>
        </div>

        {{-- Loading skeleton --}}
        <div wire:loading.flex wire:target="search, sort, checkIn, checkOut, adults, children, rooms" class="flex-col gap-4">
            @foreach (range(1, 3) as $i)
                <div class="card animate-pulse p-5">
                    <div class="flex gap-5">
                        <div class="h-32 w-48 rounded-xl bg-slate-200"></div>
                        <div class="flex-1 space-y-3 py-2">
                            <div class="h-4 w-1/3 rounded bg-slate-200"></div>
                            <div class="h-3 w-2/3 rounded bg-slate-200"></div>
                            <div class="h-3 w-1/2 rounded bg-slate-200"></div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div wire:loading.remove wire:target="search, sort, checkIn, checkOut, adults, children, rooms">
            @if ($searchError)
                <div class="rounded-xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-800">
                    {{ $searchError }}
                </div>
            @elseif ($results->isEmpty())
                {{-- Empty state --}}
                <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-16 text-center">
                    <p class="font-display text-lg font-semibold text-slate-700">Tidak ada kamar tersedia</p>
                    <p class="mx-auto mt-2 max-w-md text-sm text-slate-500">
                        Tidak ada tipe kamar yang tersedia untuk seluruh tanggal menginap dan jumlah tamu yang dipilih.
                        Coba ubah tanggal, kurangi jumlah kamar, atau sesuaikan jumlah tamu.
                    </p>
                </div>
            @else
                <p class="mb-4 text-sm text-slate-500">{{ $results->count() }} tipe kamar tersedia</p>
                <div class="space-y-4">
                    @foreach ($results as $row)
                        @php $rt = $row['room_type']; $quote = $row['quote']; $image = $rt->primaryImage->first(); @endphp
                        <div wire:key="res-{{ $rt->id }}" class="card overflow-hidden">
                            <div class="flex flex-col gap-5 p-5 sm:flex-row">
                                <a href="{{ route('rooms.show', $rt) }}" class="block h-40 w-full shrink-0 overflow-hidden rounded-xl bg-slate-100 sm:w-56">
                                    @if ($image)
                                        <img src="{{ $image->url }}" alt="{{ $rt->name }}" class="h-full w-full object-cover">
                                    @else
                                        <div class="grid h-full w-full place-items-center bg-gradient-to-br from-brand-100 to-brand-50 text-brand-300">✦</div>
                                    @endif
                                </a>

                                <div class="flex-1">
                                    <h3 class="font-display text-xl font-semibold text-slate-900">
                                        <a href="{{ route('rooms.show', $rt) }}" class="hover:text-brand-700">{{ $rt->name }}</a>
                                    </h3>
                                    <p class="mt-1 text-sm text-slate-600">{{ $rt->short_description }}</p>
                                    <div class="mt-2 flex flex-wrap gap-1.5">
                                        <span class="badge bg-brand-50 text-brand-700">Maks. {{ $rt->max_guests }} tamu</span>
                                        @if ($rt->bed_type)<span class="badge bg-slate-100 text-slate-600">{{ $rt->bed_type }}</span>@endif
                                        @foreach ($rt->amenities->take(3) as $amenity)
                                            <span class="badge bg-slate-100 text-slate-600">{{ $amenity->name }}</span>
                                        @endforeach
                                    </div>
                                    <p class="mt-3 text-xs text-slate-500">
                                        {{ data_get($rt, 'policies') ? \Illuminate\Support\Str::limit($rt->policies, 90) : 'Kebijakan pembatalan sesuai ketentuan hotel.' }}
                                    </p>
                                    @if ($row['available'] <= 3)
                                        <p class="mt-2 text-sm font-medium text-rose-600">Tersisa {{ $row['available'] }} kamar!</p>
                                    @else
                                        <p class="mt-2 text-sm text-emerald-700">{{ $row['available'] }} kamar tersedia</p>
                                    @endif
                                </div>

                                <div class="flex w-full shrink-0 flex-col justify-between border-t border-slate-100 pt-4 sm:w-56 sm:border-l sm:border-t-0 sm:pl-5 sm:pt-0">
                                    <div class="text-right">
                                        <p class="text-xs text-slate-500">{{ $nights }} malam · {{ $rooms }} kamar</p>
                                        @if ($quote->discountTotal > 0)
                                            <p class="text-sm text-slate-400 line-through">{{ rupiah($quote->subtotalBeforeDiscount) }}</p>
                                        @endif
                                        <p class="font-display text-2xl font-semibold text-slate-900">{{ rupiah($quote->grandTotal) }}</p>
                                        <p class="text-xs text-slate-500">Termasuk pajak &amp; layanan</p>
                                        @if ($quote->hasPromotion())
                                            <span class="badge mt-1 bg-emerald-100 text-emerald-700">Promo {{ $quote->promotion->code ?? 'otomatis' }}</span>
                                        @endif
                                    </div>
                                    <div class="mt-4 flex flex-col gap-2">
                                        <a href="{{ route('checkout', ['roomType' => $rt->slug, 'checkin' => $checkIn, 'checkout' => $checkOut, 'adults' => $adults, 'children' => $children, 'rooms' => $rooms]) }}"
                                           class="btn-primary w-full">Pilih Kamar</a>
                                        <a href="{{ route('rooms.show', $rt) }}" class="btn-outline w-full text-sm">Detail</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </section>
</div>
