@props(['code' => '500', 'title' => 'Terjadi kesalahan', 'message' => ''])
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $code }} — {{ $title }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,600&family=Instrument+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css'])
</head>
<body class="grid min-h-screen place-items-center bg-slate-50 px-4 font-sans text-slate-800 antialiased">
    <div class="w-full max-w-md text-center">
        <a href="{{ url('/') }}" class="mb-8 inline-flex items-center gap-2.5">
            <span class="grid h-10 w-10 place-items-center rounded-xl bg-brand-600 font-display text-lg font-semibold text-white">NP</span>
            <span class="font-display text-lg font-semibold text-slate-900">New Poppies Senggigi</span>
        </a>

        <p class="font-display text-6xl font-semibold text-brand-700">{{ $code }}</p>
        <h1 class="mt-3 font-display text-2xl font-semibold text-slate-900">{{ $title }}</h1>
        <p class="mt-2 text-slate-600">{{ $message }}</p>

        <div class="mt-8 flex flex-wrap justify-center gap-3">
            <a href="{{ url('/') }}" class="btn-primary">Kembali ke Beranda</a>
            <a href="{{ url('/kamar') }}" class="btn-outline">Lihat Kamar</a>
        </div>
    </div>
</body>
</html>
