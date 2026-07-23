<x-layouts.public title="Kamar & Tarif">
    <section class="bg-brand-900 py-14">
        <div class="mx-auto max-w-6xl px-4">
            <h1 class="font-display text-3xl font-semibold text-white sm:text-4xl">Kamar &amp; Tarif</h1>
            <p class="mt-2 max-w-lg text-brand-100">Pilih tipe kamar yang sesuai untuk menginap Anda di Senggigi.</p>
        </div>
    </section>

    <section class="mx-auto max-w-6xl px-4 py-12">
        @if ($roomTypes->isNotEmpty())
            <p class="mb-6 text-sm text-slate-500">{{ $roomTypes->total() }} tipe kamar tersedia</p>
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($roomTypes as $roomType)
                    <x-room-card :room-type="$roomType" />
                @endforeach
            </div>
            <div class="mt-8">{{ $roomTypes->links() }}</div>
        @else
            <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-16 text-center">
                <p class="font-display text-lg font-semibold text-slate-700">Belum ada kamar yang dipublikasikan</p>
                <p class="mt-1 text-sm text-slate-500">Silakan kembali lagi nanti atau hubungi kami untuk informasi ketersediaan.</p>
                <a href="{{ route('home') }}" class="btn-outline mt-5">Kembali ke Beranda</a>
            </div>
        @endif
    </section>
</x-layouts.public>
