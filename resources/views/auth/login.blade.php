<x-layouts.guest title="Masuk">
    <h1 class="font-display text-2xl font-semibold text-slate-900">Masuk ke akun Anda</h1>
    <p class="mt-1.5 text-sm text-slate-500">Belum punya akun?
        <a href="{{ route('register') }}" class="font-medium text-brand-700 hover:underline">Daftar sekarang</a>
    </p>

    @if ($errors->any())
        <div class="mt-6 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}" class="mt-6 space-y-4">
        @csrf
        <div>
            <label for="email" class="label">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                   class="input @error('email') border-rose-400 @enderror" placeholder="nama@email.com">
        </div>
        <div>
            <label for="password" class="label">Kata Sandi</label>
            <input id="password" name="password" type="password" required autocomplete="current-password"
                   class="input @error('password') border-rose-400 @enderror" placeholder="••••••••">
        </div>
        <div class="flex items-center justify-between">
            <label class="flex items-center gap-2 text-sm text-slate-600">
                <input type="checkbox" name="remember" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                Ingat saya
            </label>
        </div>
        <button type="submit" class="btn-primary w-full">Masuk</button>
    </form>

    <p class="mt-6 text-center text-sm text-slate-500">
        <a href="{{ route('home') }}" class="hover:text-brand-700">&larr; Kembali ke beranda</a>
    </p>
</x-layouts.guest>
