<x-layouts.public title="Beranda">
    {{-- Hero --}}
    <section class="relative overflow-hidden bg-brand-950">
        <div class="absolute inset-0 bg-gradient-to-br from-brand-800 via-brand-900 to-brand-950"></div>
        <div class="absolute -right-24 -top-24 h-96 w-96 rounded-full bg-brand-500/20 blur-3xl"></div>
        <div class="absolute -bottom-24 -left-24 h-96 w-96 rounded-full bg-sand-500/10 blur-3xl"></div>

        <div class="relative mx-auto max-w-6xl px-4 py-24 text-center sm:py-32">
            <span class="badge bg-white/10 text-sand-200">★ Hotel Butik Tepi Pantai</span>
            <h1 class="mx-auto mt-5 max-w-3xl font-display text-4xl font-semibold leading-tight text-white sm:text-6xl">
                Ketenangan Senggigi, kenyamanan yang menginap di hati.
            </h1>
            <p class="mx-auto mt-5 max-w-xl text-lg text-brand-100">
                Temukan kamar terbaik dengan pemandangan laut Lombok. Cek ketersediaan, pesan, dan bayar online dengan aman.
            </p>
            <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
                <a href="{{ route('search') }}" class="btn-accent px-7 py-3 text-base">Cari &amp; Pesan Kamar</a>
                <a href="#faq" class="btn px-7 py-3 text-base text-white ring-1 ring-inset ring-white/30 hover:bg-white/10">Pertanyaan Umum</a>
            </div>
        </div>
    </section>

    {{-- Advantages — lifted over the hero edge; z-10 keeps the cards (and their
         icons) above the hero background instead of being clipped behind it. --}}
    <section class="relative z-10 mx-auto -mt-14 max-w-6xl px-4">
        <div class="grid gap-4 sm:grid-cols-3">
            @foreach ([
                ['Lokasi Strategis', 'Langkah singkat ke Pantai Senggigi, restoran, dan pusat oleh-oleh.'],
                ['Pembayaran Aman', 'Transaksi online terverifikasi melalui gateway pembayaran DOKU.'],
                ['Ketersediaan Real-time', 'Kalender kamar akurat — bebas dari risiko pemesanan ganda.'],
            ] as [$title, $desc])
                <div class="card p-6 shadow-lg shadow-brand-950/5">
                    <div class="grid h-11 w-11 place-items-center rounded-xl bg-brand-50 text-lg text-brand-700">✦</div>
                    <h3 class="mt-4 font-display text-lg font-semibold text-slate-900">{{ $title }}</h3>
                    <p class="mt-1.5 text-sm leading-relaxed text-slate-600">{{ $desc }}</p>
                </div>
            @endforeach
        </div>
    </section>

    {{-- Featured rooms --}}
    <section id="rooms" class="mx-auto max-w-6xl px-4 py-20">
        <div class="flex items-end justify-between">
            <div>
                <h2 class="font-display text-3xl font-semibold text-slate-900">Pilihan Kamar</h2>
                <p class="mt-2 max-w-lg text-slate-600">Setiap kamar dirancang untuk kenyamanan menginap Anda.</p>
            </div>
            <a href="{{ route('rooms.index') }}" class="hidden text-sm font-medium text-brand-700 hover:underline sm:block">Lihat semua →</a>
        </div>

        @if ($featuredRoomTypes->isNotEmpty())
            <div class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($featuredRoomTypes as $roomType)
                    <x-room-card :room-type="$roomType" />
                @endforeach
            </div>
        @else
            <div class="mt-10 rounded-2xl border border-dashed border-slate-300 bg-white p-12 text-center text-slate-500">
                Tipe kamar akan tampil di sini setelah data kamar dipublikasikan oleh admin.
            </div>
        @endif
    </section>

    {{-- Facilities — now synced from admin settings --}}
    <section id="facilities" class="bg-white py-20">
        <div class="mx-auto max-w-6xl px-4">
            <div class="text-center">
                <h2 class="font-display text-3xl font-semibold text-slate-900">Fasilitas</h2>
                <p class="mx-auto mt-2 max-w-lg text-slate-600">Nikmati kelengkapan fasilitas selama menginap.</p>
            </div>
            @if ($facilities->isNotEmpty())
                <div class="mt-10 grid grid-cols-2 gap-4 sm:grid-cols-4">
                    @foreach ($facilities as $facility)
                        <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-5 text-center text-sm font-medium text-slate-700">
                            {{ $facility->name }}
                        </div>
                    @endforeach
                </div>
            @else
                <div class="mt-10 rounded-2xl border border-dashed border-slate-300 bg-white p-12 text-center text-slate-500">
                    Admin belum menambahkan fasilitas hotel. Silakan kunjungi kembali nanti.
                </div>
            @endif
        </div>
    </section>

    {{-- Gallery — curated at Admin → Galeri. Clicking a photo opens it larger;
         `lightbox` holds the index of the open photo, or null when closed. --}}
    <section id="gallery" class="mx-auto max-w-6xl px-4 py-20" x-data="{ lightbox: null }">
        <div class="text-center">
            <h2 class="font-display text-3xl font-semibold text-slate-900">Galeri</h2>
            <p class="mx-auto mt-2 max-w-lg text-slate-600">Suasana New Poppies Senggigi.</p>
        </div>

        @if ($galleryImages->isNotEmpty())
            <div class="mt-10 grid grid-cols-2 gap-3 sm:grid-cols-4">
                @foreach ($galleryImages as $i => $photo)
                    <button type="button" @click="lightbox = {{ $i }}"
                            class="group relative aspect-square overflow-hidden rounded-xl bg-slate-100
                                   focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2">
                        <img src="{{ $photo->url }}" alt="{{ $photo->alt_text }}" loading="lazy"
                             class="h-full w-full object-cover transition duration-300 group-hover:scale-105">
                        @if ($photo->title)
                            <span class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-slate-900/70 to-transparent px-3 py-2 text-left text-xs font-medium text-white">
                                {{ $photo->title }}
                            </span>
                        @endif
                    </button>
                @endforeach
            </div>

            {{-- Lightbox --}}
            <div x-show="lightbox !== null" x-cloak @keydown.escape.window="lightbox = null"
                 @click="lightbox = null"
                 class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 p-4">
                @foreach ($galleryImages as $i => $photo)
                    <div x-show="lightbox === {{ $i }}" @click.stop class="max-h-full max-w-4xl">
                        <img src="{{ $photo->url }}" alt="{{ $photo->alt_text }}"
                             class="max-h-[80vh] w-auto rounded-xl object-contain">
                        @if ($photo->title)
                            <p class="mt-3 text-center text-sm text-white">{{ $photo->title }}</p>
                        @endif
                    </div>
                @endforeach
                <button type="button" @click="lightbox = null"
                        class="absolute right-5 top-5 text-3xl leading-none text-white/80 hover:text-white"
                        aria-label="Tutup">&times;</button>
            </div>
        @else
            <div class="mt-10 grid grid-cols-2 gap-3 sm:grid-cols-4">
                @foreach (range(1, 8) as $i)
                    <div class="aspect-square rounded-xl bg-gradient-to-br from-brand-100 to-sand-100"></div>
                @endforeach
            </div>
        @endif
    </section>

    {{-- FAQ --}}
    <section id="faq" class="mx-auto max-w-3xl px-4 py-20">
        <div class="text-center">
            <h2 class="font-display text-3xl font-semibold text-slate-900">Pertanyaan Umum</h2>
        </div>
        <div class="mt-8 space-y-3" x-data="{ open: 0 }">
            @foreach ([
                ['Jam berapa check-in dan check-out?', 'Check-in mulai pukul 14.00 WITA dan check-out paling lambat pukul 12.00 WITA.'],
                ['Metode pembayaran apa yang tersedia?', 'Pembayaran dilakukan secara online melalui DOKU (kartu, virtual account, dan e-wallet).'],
                ['Bagaimana kebijakan pembatalan?', 'Pembatalan minimal 24 jam sebelum check-in dapat memperoleh pengembalian penuh sesuai kebijakan.'],
            ] as $i => [$q, $a])
                <div class="card overflow-hidden">
                    <button @click="open === {{ $i }} ? open = null : open = {{ $i }}" class="flex w-full items-center justify-between px-5 py-4 text-left">
                        <span class="font-medium text-slate-900">{{ $q }}</span>
                        <span class="text-slate-400" x-text="open === {{ $i }} ? '−' : '+'"></span>
                    </button>
                    <div x-show="open === {{ $i }}" x-transition x-cloak class="px-5 pb-4 text-sm text-slate-600">{{ $a }}</div>
                </div>
            @endforeach
        </div>
    </section>

    {{-- CTA --}}
    <section class="mx-auto max-w-6xl px-4 pb-4">
        <div class="overflow-hidden rounded-3xl bg-brand-900 px-8 py-14 text-center">
            <h2 class="font-display text-3xl font-semibold text-white">Siap merencanakan menginap Anda?</h2>
            <p class="mx-auto mt-2 max-w-lg text-brand-100">Buat akun untuk mempercepat proses pemesanan dan melihat riwayat reservasi.</p>
            <a href="{{ route('search') }}" class="btn-accent mt-6 px-7 py-3 text-base">Cari Kamar</a>
        </div>
    </section>
</x-layouts.public>
