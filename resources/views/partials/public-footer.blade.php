<footer id="location" class="mt-20 bg-brand-950 text-slate-300">
    <div class="mx-auto grid max-w-6xl gap-10 px-4 py-14 md:grid-cols-4">
        <div class="md:col-span-2">
            <div class="flex items-center gap-2.5">
                <span class="grid h-10 w-10 place-items-center rounded-xl bg-brand-600 font-display text-lg font-semibold text-white">NP</span>
                <span class="font-display text-xl font-semibold text-white">New Poppies Senggigi</span>
            </div>
            <p class="mt-4 max-w-md text-sm leading-relaxed text-slate-400">
                Hotel butik tepi pantai di kawasan Senggigi, Lombok Barat. Nikmati kenyamanan menginap
                dengan pemandangan laut, taman tropis, dan pelayanan yang ramah.
            </p>
        </div>
        <div>
            <h4 class="font-semibold text-white">Kontak</h4>
            <ul class="mt-4 space-y-2 text-sm text-slate-400">
                <li>Jl. Raya Senggigi, Batu Layar</li>
                <li>Lombok Barat, Nusa Tenggara Barat</li>
                <li>Telp: (0370) 000-000</li>
                <li>Email: reservasi@newpoppiessenggigi.test</li>
            </ul>
        </div>
        <div>
            <h4 class="font-semibold text-white">Tautan</h4>
            <ul class="mt-4 space-y-2 text-sm text-slate-400">
                <li><a href="{{ route('home') }}#rooms" class="hover:text-white">Kamar &amp; Tarif</a></li>
                <li><a href="{{ route('home') }}#faq" class="hover:text-white">FAQ</a></li>
                <li><a href="{{ route('login') }}" class="hover:text-white">Masuk Akun</a></li>
            </ul>
        </div>
    </div>
    <div class="border-t border-white/10">
        <div class="mx-auto flex max-w-6xl flex-col items-center justify-between gap-2 px-4 py-5 text-xs text-slate-500 sm:flex-row">
            <p>&copy; {{ date('Y') }} New Poppies Senggigi. Hak cipta dilindungi.</p>
            <p>Zona waktu: {{ config('app.timezone') }} (WITA)</p>
        </div>
    </div>
</footer>
