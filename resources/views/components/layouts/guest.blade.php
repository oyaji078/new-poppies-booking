@props(['title' => 'Akun'])
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} — New Poppies Senggigi</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,500;9..144,600&family=Instrument+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-100 font-sans text-slate-800 antialiased">
    <div class="grid min-h-screen lg:grid-cols-2">
        <div class="relative hidden bg-brand-900 lg:block">
            <div class="absolute inset-0 bg-gradient-to-br from-brand-800 via-brand-900 to-brand-950"></div>
            <div class="relative flex h-full flex-col justify-between p-12 text-white">
                <a href="{{ route('home') }}" class="flex items-center gap-2.5">
                    <span class="grid h-10 w-10 place-items-center rounded-xl bg-white/15 font-display text-lg font-semibold">NP</span>
                    <span class="font-display text-xl font-semibold">New Poppies Senggigi</span>
                </a>
                <div>
                    <h2 class="font-display text-3xl font-semibold leading-tight">Menginap di tepi pantai Senggigi.</h2>
                    <p class="mt-3 max-w-sm text-brand-100">Pesan kamar dengan mudah, cek ketersediaan secara real-time, dan bayar dengan aman.</p>
                </div>
                <p class="text-sm text-brand-200/80">Lombok Barat · Nusa Tenggara Barat</p>
            </div>
        </div>

        <div class="flex items-center justify-center px-4 py-12">
            <div class="w-full max-w-md">
                <a href="{{ route('home') }}" class="mb-8 flex items-center gap-2.5 lg:hidden">
                    <span class="grid h-10 w-10 place-items-center rounded-xl bg-brand-600 font-display text-lg font-semibold text-white">NP</span>
                    <span class="font-display text-lg font-semibold text-slate-900">New Poppies Senggigi</span>
                </a>
                {{ $slot }}
            </div>
        </div>
    </div>
</body>
</html>
