<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name')) — New Poppies Senggigi</title>
    <meta name="description" content="@yield('meta_description', 'Hotel butik tepi pantai di Senggigi, Lombok Barat. Pesan kamar, cek ketersediaan, dan bayar online dengan aman.')">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,500;9..144,600&family=Instrument+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    @stack('head')
</head>
<body class="min-h-screen bg-slate-50 font-sans text-slate-800 antialiased">
    @include('partials.public-nav')

    <main>
        @if (session('status'))
            <div class="mx-auto max-w-6xl px-4 pt-6">
                <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    {{ session('status') }}
                </div>
            </div>
        @endif

        @yield('content')
    </main>

    @include('partials.public-footer')

    @stack('modals')
    @livewireScripts
    @stack('scripts')
</body>
</html>
