@props([
    'title' => 'Dashboard',
    'heading' => 'Dashboard',
    'breadcrumb' => 'Admin',
])
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} — Admin New Poppies</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,500;9..144,600&family=Instrument+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-slate-100 font-sans text-slate-800 antialiased" x-data="{ sidebar: false }">
    <div class="flex min-h-screen">
        <aside
            class="fixed inset-y-0 left-0 z-40 w-64 -translate-x-full transform bg-brand-950 text-slate-300 transition lg:translate-x-0"
            :class="sidebar && '!translate-x-0'"
        >
            <div class="flex h-16 items-center gap-2.5 border-b border-white/10 px-5">
                <span class="grid h-9 w-9 place-items-center rounded-lg bg-brand-600 font-display font-semibold text-white">NP</span>
                <span class="font-display text-lg font-semibold text-white">Admin Panel</span>
            </div>
            <nav class="flex flex-col gap-1 overflow-y-auto p-3 text-sm">
                @php
                    $isSuper = auth()->user()?->isSuperAdmin();

                    // Day-to-day operations — the Admin's domain.
                    $operational = [
                        ['Reservasi', 'admin.bookings.index'],
                        ['Pembayaran', 'admin.payments.index'],
                        ['Peninjauan Pembayaran', 'admin.payments.review'],
                        ['Check-in / Check-out', 'admin.frontdesk.index'],
                        ['Tipe Kamar', 'admin.room-types.index'],
                        ['Kamar Fisik', 'admin.rooms.index'],
                        ['Fasilitas', 'admin.amenities.index'],
                        ['Inventaris & Kalender', 'admin.inventory.index'],
                        ['Promosi', 'admin.promotions.index'],
                        ['Pembatalan & Refund', 'admin.cancellations.index'],
                        ['Tamu', 'admin.guests.index'],
                        ['Laporan', 'admin.reports.index'],
                        ['FAQ / Chatbot', 'admin.faqs.index'],
                        ['Konten Website', 'admin.pages.index'],
                        ['Audit Log', 'admin.audit.index'],
                    ];
                    // System & money configuration — the Super Admin's domain.
                    $configuration = [
                        ['Pengaturan Sistem', 'admin.settings.edit'],
                        ['Mode Pembayaran DOKU', 'admin.doku.environment'],
                        ['Kelola Pengguna', 'admin.users.index'],
                    ];
                    $navLink = function (string $label, string $routeName) {
                        if (! Route::has($routeName)) {
                            return '';
                        }
                        $active = request()->routeIs($routeName) || request()->routeIs(str_replace('.index', '.*', $routeName));
                        $classes = $active
                            ? 'bg-brand-600 text-white'
                            : 'text-slate-300 hover:bg-white/5 hover:text-white';

                        return '<a href="'.route($routeName).'" class="flex items-center gap-3 rounded-lg px-3 py-2 font-medium transition '.$classes.'">'.e($label).'</a>';
                    };
                @endphp

                {{-- Dashboard is the shared landing for every role. --}}
                {!! $navLink('Dashboard', 'admin.dashboard') !!}

                {{-- Super Admin is scoped to configuration; Admin/staff to
                     operations. The two sets are deliberately disjoint. --}}
                @if ($isSuper)
                    <div class="mt-4 mb-1 px-3">
                        <p class="text-[10px] font-semibold uppercase tracking-widest text-slate-500">Konfigurasi Sistem</p>
                    </div>
                    @foreach ($configuration as [$label, $routeName])
                        {!! $navLink($label, $routeName) !!}
                    @endforeach
                @else
                    @foreach ($operational as [$label, $routeName])
                        {!! $navLink($label, $routeName) !!}
                    @endforeach
                @endif
            </nav>
        </aside>

        <div x-show="sidebar" x-cloak @click="sidebar = false" class="fixed inset-0 z-30 bg-slate-900/50 lg:hidden"></div>

        <div class="flex min-h-screen flex-1 flex-col lg:pl-64">
            <header class="sticky top-0 z-20 flex h-16 items-center justify-between border-b border-slate-200 bg-white px-4">
                <div class="flex items-center gap-3">
                    <button @click="sidebar = !sidebar" class="grid h-10 w-10 place-items-center rounded-lg text-slate-600 hover:bg-slate-100 lg:hidden" aria-label="Menu">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
                    </button>
                    <div>
                        <nav class="text-xs text-slate-400">{{ $breadcrumb }}</nav>
                        <h1 class="font-display text-lg font-semibold text-slate-900">{{ $heading }}</h1>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <a href="{{ route('home') }}" target="_blank" class="hidden text-sm text-slate-500 hover:text-brand-700 sm:block">Lihat Situs ↗</a>
                    <div class="flex items-center gap-2 border-l border-slate-200 pl-3">
                        <div class="hidden text-right sm:block">
                            <p class="text-sm font-medium text-slate-900">{{ auth()->user()->name }}</p>
                            <p class="text-xs text-slate-500">{{ auth()->user()->role?->label() }}</p>
                        </div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="btn-ghost text-sm">Keluar</button>
                        </form>
                    </div>
                </div>
            </header>

            @if (session('success') || session('error') || session('status'))
                <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)" class="px-4 pt-4">
                    @if (session('success'))
                        <div class="flex items-center justify-between rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                            <span>{{ session('success') }}</span>
                            <button @click="show = false" class="text-emerald-600">&times;</button>
                        </div>
                    @endif
                    @if (session('error'))
                        <div class="flex items-center justify-between rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                            <span>{{ session('error') }}</span>
                            <button @click="show = false" class="text-rose-600">&times;</button>
                        </div>
                    @endif
                    @if (session('status'))
                        <div class="rounded-lg border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-800">{{ session('status') }}</div>
                    @endif
                </div>
            @endif

            <main class="flex-1 p-4 sm:p-6">
                {{ $slot }}
            </main>
        </div>
    </div>

    @livewireScripts
    {{ $scripts ?? '' }}
</body>
</html>
