<header
    x-data="{ open: false, scrolled: false }"
    x-init="scrolled = window.scrollY > 10; window.addEventListener('scroll', () => scrolled = window.scrollY > 10)"
    class="sticky top-0 z-40 transition"
    :class="scrolled ? 'bg-white/95 shadow-sm backdrop-blur' : 'bg-white/80 backdrop-blur'"
>
    <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-3.5">
        <a href="{{ route('home') }}" class="flex items-center gap-2.5">
            <span class="grid h-10 w-10 place-items-center rounded-xl bg-brand-600 font-display text-lg font-semibold text-white">NP</span>
            <span class="leading-tight">
                <span class="block font-display text-lg font-semibold text-slate-900">New Poppies</span>
                <span class="block text-xs tracking-wide text-slate-500">SENGGIGI · LOMBOK</span>
            </span>
        </a>

        <nav class="hidden items-center gap-7 md:flex">
            <a href="{{ route('rooms.index') }}" class="text-sm font-medium text-slate-600 hover:text-brand-700">Kamar</a>
            <a href="{{ route('search') }}" class="text-sm font-medium text-slate-600 hover:text-brand-700">Cari &amp; Pesan</a>
            <a href="{{ route('booking.lookup.form') }}" class="text-sm font-medium text-slate-600 hover:text-brand-700">Cek Pemesanan</a>
            <a href="{{ route('home') }}#facilities" class="text-sm font-medium text-slate-600 hover:text-brand-700">Fasilitas</a>
            <a href="{{ route('home') }}#faq" class="text-sm font-medium text-slate-600 hover:text-brand-700">FAQ</a>
        </nav>

        <div class="hidden items-center gap-3 md:flex">
            @auth
                @if (auth()->user()->isStaff())
                    <a href="{{ route('admin.dashboard') }}" class="btn-outline">Dashboard</a>
                @else
                    <a href="{{ route('account.bookings') }}" class="text-sm font-medium text-slate-600 hover:text-brand-700">Pemesanan Saya</a>
                @endif
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="btn-ghost">Keluar</button>
                </form>
            @else
                <a href="{{ route('login') }}" class="btn-ghost">Masuk</a>
                <a href="{{ route('register') }}" class="btn-primary">Daftar</a>
            @endauth
        </div>

        <button @click="open = !open" class="grid h-10 w-10 place-items-center rounded-lg text-slate-600 hover:bg-slate-100 md:hidden" aria-label="Menu">
            <svg x-show="!open" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
            <svg x-show="open" x-cloak class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 6l12 12M18 6L6 18"/></svg>
        </button>
    </div>

    <div x-show="open" x-cloak x-transition class="border-t border-slate-100 bg-white md:hidden">
        <div class="space-y-1 px-4 py-3">
            <a href="{{ route('rooms.index') }}" class="block rounded-lg px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Kamar</a>
            <a href="{{ route('search') }}" class="block rounded-lg px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Cari &amp; Pesan</a>
            <a href="{{ route('booking.lookup.form') }}" class="block rounded-lg px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Cek Pemesanan</a>
            <a href="{{ route('home') }}#facilities" class="block rounded-lg px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Fasilitas</a>
            <a href="{{ route('home') }}#faq" class="block rounded-lg px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">FAQ</a>
            @auth
                @if (auth()->user()->isStaff())
                    <a href="{{ route('admin.dashboard') }}" class="block rounded-lg px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Dashboard</a>
                @else
                    <a href="{{ route('account.bookings') }}" class="block rounded-lg px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Pemesanan Saya</a>
                @endif
            @endauth
            <div class="flex gap-2 pt-2">
                @auth
                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <button type="submit" class="btn-outline w-full">Keluar</button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="btn-outline w-full">Masuk</a>
                    <a href="{{ route('register') }}" class="btn-primary w-full">Daftar</a>
                @endauth
            </div>
        </div>
    </div>
</header>
