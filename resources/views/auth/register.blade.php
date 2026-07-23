<x-layouts.guest title="Daftar">
    <h1 class="font-display text-2xl font-semibold text-slate-900">Buat akun baru</h1>
    <p class="mt-1.5 text-sm text-slate-500">Sudah punya akun?
        <a href="{{ route('login') }}" class="font-medium text-brand-700 hover:underline">Masuk</a>
    </p>

    <form method="POST" action="{{ route('register') }}" class="mt-6 space-y-4">
        @csrf
        <div>
            <label for="name" class="label">Nama Lengkap</label>
            <input id="name" name="name" type="text" value="{{ old('name') }}" required autofocus autocomplete="name"
                   class="input @error('name') border-rose-400 @enderror" placeholder="Nama Anda">
            @error('name') <p class="field-error">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="email" class="label">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="username"
                   class="input @error('email') border-rose-400 @enderror" placeholder="nama@email.com">
            @error('email') <p class="field-error">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="phone" class="label">Nomor Telepon <span class="text-slate-400">(opsional)</span></label>
            <input id="phone" name="phone" type="text" value="{{ old('phone') }}" autocomplete="tel"
                   class="input @error('phone') border-rose-400 @enderror" placeholder="08xxxxxxxxxx">
            @error('phone') <p class="field-error">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="password" class="label">Kata Sandi</label>
            <input id="password" name="password" type="password" required autocomplete="new-password"
                   class="input @error('password') border-rose-400 @enderror" placeholder="Minimal 8 karakter">
            @error('password') <p class="field-error">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="password_confirmation" class="label">Konfirmasi Kata Sandi</label>
            <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"
                   class="input" placeholder="Ulangi kata sandi">
        </div>
        <button type="submit" class="btn-primary w-full">Daftar</button>
    </form>

    <p class="mt-6 text-center text-sm text-slate-500">
        <a href="{{ route('home') }}" class="hover:text-brand-700">&larr; Kembali ke beranda</a>
    </p>
</x-layouts.guest>
