<x-layouts.public title="Cek Pemesanan">
    <section class="mx-auto max-w-lg px-4 py-16">
        <div class="card p-8">
            <h1 class="font-display text-2xl font-semibold text-slate-900">Cek Status Pemesanan</h1>
            <p class="mt-1.5 text-sm text-slate-500">Masukkan kode pemesanan dan email yang digunakan saat memesan.</p>

            @if ($errors->any())
                <div class="mt-5 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('booking.lookup') }}" class="mt-6 space-y-4">
                @csrf
                <div>
                    <label for="code" class="label">Kode Pemesanan</label>
                    <input id="code" name="code" type="text" value="{{ old('code') }}" required autofocus
                           class="input font-mono uppercase" placeholder="NPS-20260810-A7K9P2">
                </div>
                <div>
                    <label for="email" class="label">Email</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" required
                           class="input" placeholder="nama@email.com">
                </div>
                <button type="submit" class="btn-primary w-full">Lihat Pemesanan</button>
            </form>

            <p class="mt-6 text-center text-sm text-slate-500">
                <a href="{{ route('home') }}" class="hover:text-brand-700">&larr; Kembali ke beranda</a>
            </p>
        </div>
    </section>
</x-layouts.public>
