<x-layouts.public :title="$roomType->name">
    @php
        $images = $roomType->images;
        $primary = $roomType->images->firstWhere('is_primary', true) ?? $images->first();
    @endphp

    <section class="mx-auto max-w-6xl px-4 py-8">
        <nav class="mb-4 text-sm text-slate-500">
            <a href="{{ route('home') }}" class="hover:text-brand-700">Beranda</a> /
            <a href="{{ route('rooms.index') }}" class="hover:text-brand-700">Kamar</a> /
            <span class="text-slate-700">{{ $roomType->name }}</span>
        </nav>

        {{-- Gallery --}}
        <div class="grid gap-3 md:grid-cols-4 md:grid-rows-2" x-data="{ active: '{{ $primary?->url }}' }">
            <div class="md:col-span-3 md:row-span-2">
                <div class="aspect-[16/10] overflow-hidden rounded-2xl bg-slate-100">
                    @if ($primary)
                        <img :src="active" alt="{{ $roomType->name }}" class="h-full w-full object-cover">
                    @else
                        <div class="grid h-full w-full place-items-center bg-gradient-to-br from-brand-100 to-brand-50 text-brand-300">
                            <span class="font-display text-lg">Tidak ada foto</span>
                        </div>
                    @endif
                </div>
            </div>
            @foreach ($images->take(4) as $image)
                <button type="button" @click="active = '{{ $image->url }}'"
                        class="hidden aspect-[4/3] overflow-hidden rounded-xl bg-slate-100 ring-brand-500 focus:outline-none focus:ring-2 md:block">
                    <img src="{{ $image->url }}" alt="{{ $image->alt }}" class="h-full w-full object-cover">
                </button>
            @endforeach
        </div>

        <div class="mt-8 grid gap-8 lg:grid-cols-3">
            {{-- Details --}}
            <div class="lg:col-span-2">
                <h1 class="font-display text-3xl font-semibold text-slate-900">{{ $roomType->name }}</h1>
                <div class="mt-3 flex flex-wrap gap-2 text-sm text-slate-600">
                    <span class="badge bg-brand-50 text-brand-700">Maks. {{ $roomType->max_guests }} tamu</span>
                    <span class="badge bg-slate-100 text-slate-600">{{ $roomType->adult_capacity }} dewasa · {{ $roomType->child_capacity }} anak</span>
                    @if ($roomType->bed_type)<span class="badge bg-slate-100 text-slate-600">{{ $roomType->bed_type }}</span>@endif
                    @if ($roomType->room_size)<span class="badge bg-slate-100 text-slate-600">{{ $roomType->room_size }} m²</span>@endif
                </div>

                @if ($roomType->full_description)
                    <div class="prose prose-slate mt-6 max-w-none text-slate-700">
                        {!! nl2br(e($roomType->full_description)) !!}
                    </div>
                @endif

                @if ($roomType->amenities->isNotEmpty())
                    <h2 class="mt-8 font-display text-xl font-semibold text-slate-900">Fasilitas Kamar</h2>
                    <div class="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-3">
                        @foreach ($roomType->amenities as $amenity)
                            <div class="flex items-center gap-2 text-sm text-slate-700">
                                <span class="text-brand-600">✓</span> {{ $amenity->name }}
                            </div>
                        @endforeach
                    </div>
                @endif

                @if ($roomType->policies)
                    <h2 class="mt-8 font-display text-xl font-semibold text-slate-900">Kebijakan</h2>
                    <p class="mt-3 text-sm leading-relaxed text-slate-600">{{ $roomType->policies }}</p>
                @endif
            </div>

            {{-- Booking card (sticky on desktop) --}}
            <div class="lg:col-span-1">
                <div class="card sticky top-24 p-6">
                    <p class="text-sm text-slate-500">Mulai dari</p>
                    <p class="font-display text-3xl font-semibold text-slate-900">{{ rupiah($roomType->base_price) }}
                        <span class="text-sm font-normal text-slate-500">/ malam</span>
                    </p>
                    <p class="mt-1 text-xs text-slate-400">Belum termasuk pajak &amp; layanan. Total dihitung saat pemesanan.</p>

                    <a href="{{ route('search') }}" class="btn-primary mt-5 w-full">Cek Ketersediaan</a>
                    <p class="mt-3 text-center text-xs text-slate-400">Pilih tanggal menginap untuk melihat ketersediaan dan harga total.</p>

                    <ul class="mt-6 space-y-2 border-t border-slate-100 pt-4 text-sm text-slate-600">
                        <li class="flex items-center gap-2"><span class="text-brand-600">✓</span> Pembatalan sesuai kebijakan</li>
                        <li class="flex items-center gap-2"><span class="text-brand-600">✓</span> Pembayaran aman via DOKU</li>
                        <li class="flex items-center gap-2"><span class="text-brand-600">✓</span> Konfirmasi instan setelah pembayaran</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    {{-- Mobile sticky booking bar --}}
    <div class="sticky bottom-0 z-30 border-t border-slate-200 bg-white/95 p-3 backdrop-blur lg:hidden">
        <div class="mx-auto flex max-w-6xl items-center justify-between gap-3 px-1">
            <div>
                <p class="text-xs text-slate-500">Mulai dari</p>
                <p class="font-display text-lg font-semibold text-slate-900">{{ rupiah($roomType->base_price) }}</p>
            </div>
            <a href="{{ route('search') }}" class="btn-primary">Cek Ketersediaan</a>
        </div>
    </div>
</x-layouts.public>
