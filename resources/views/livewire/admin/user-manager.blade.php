<div class="space-y-6">

    @if (session('status'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm text-emerald-800">
            {{ session('status') }}
        </div>
    @endif

    @error('general')
        <div class="rounded-xl border border-rose-200 bg-rose-50 px-5 py-4 text-sm text-rose-700">{{ $message }}</div>
    @enderror

    <div class="flex items-center justify-between">
        <div>
            <h2 class="font-display text-lg font-semibold text-slate-900">Pengguna Staf</h2>
            <p class="mt-1 text-sm text-slate-500">Buat akun staf, tetapkan peran, atur ulang kata sandi, dan aktif/nonaktifkan.</p>
        </div>
        <button wire:click="openCreate" class="btn-primary">+ Pengguna Baru</button>
    </div>

    <div class="card overflow-hidden">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">Nama</th>
                    <th class="px-4 py-3">Email</th>
                    <th class="px-4 py-3">Peran</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Login Terakhir</th>
                    <th class="px-4 py-3 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach ($users as $user)
                    <tr wire:key="user-{{ $user->id }}" class="hover:bg-slate-50">
                        <td class="px-4 py-3">
                            <span class="font-medium text-slate-900">{{ $user->name }}</span>
                            @if ($user->id === auth()->id())
                                <span class="ml-1 text-xs text-slate-400">(Anda)</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-slate-600">{{ $user->email }}</td>
                        <td class="px-4 py-3">
                            <span @class([
                                'rounded-full px-2.5 py-0.5 text-xs font-semibold',
                                'bg-rose-100 text-rose-700' => $user->role->value === 'super_admin',
                                'bg-brand-100 text-brand-700' => $user->role->value === 'admin',
                                'bg-slate-100 text-slate-600' => ! in_array($user->role->value, ['super_admin', 'admin']),
                            ])>{{ $user->role->label() }}</span>
                        </td>
                        <td class="px-4 py-3">
                            @if ($user->is_active)
                                <span class="inline-flex items-center gap-1 text-emerald-700"><span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span> Aktif</span>
                            @else
                                <span class="inline-flex items-center gap-1 text-slate-400"><span class="h-1.5 w-1.5 rounded-full bg-slate-300"></span> Nonaktif</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-slate-500">{{ $user->last_login_at?->translatedFormat('d M Y, H:i') ?? '—' }}</td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <button wire:click="openEdit({{ $user->id }})" class="font-medium text-brand-700 hover:text-brand-800">Ubah</button>
                            @if ($user->id !== auth()->id())
                                <button wire:click="toggleActive({{ $user->id }})"
                                        class="ml-3 font-medium {{ $user->is_active ? 'text-rose-600 hover:text-rose-700' : 'text-emerald-600 hover:text-emerald-700' }}">
                                    {{ $user->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                </button>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if ($showForm)
        <div class="card border-2 border-brand-200 p-5">
            <h3 class="font-semibold text-slate-900">{{ $editingId ? 'Ubah Pengguna' : 'Pengguna Baru' }}</h3>

            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-slate-700">Nama</label>
                    <input type="text" wire:model="name" class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    @error('name') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Email</label>
                    <input type="email" wire:model="email" class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    @error('email') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Telepon</label>
                    <input type="text" wire:model="phone" class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    @error('phone') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Peran</label>
                    <select wire:model="role" class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">
                        @foreach ($roles as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('role') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-slate-700">
                        Kata Sandi {{ $editingId ? '(kosongkan untuk tidak mengubah)' : '' }}
                    </label>
                    <input type="password" wire:model="password" autocomplete="new-password"
                           class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    @error('password') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                    <p class="mt-1 text-xs text-slate-400">Minimal 8 karakter.</p>
                </div>
            </div>

            <div class="mt-5 flex gap-2">
                <button wire:click="save" class="btn-primary">Simpan</button>
                <button wire:click="$set('showForm', false)" class="btn-secondary">Batal</button>
            </div>
        </div>
    @endif

    <p class="text-xs text-slate-400">
        Pelanggan mendaftar sendiri dan tidak dikelola di sini. Peran Super Admin dapat mengubah mode
        pembayaran DOKU — berikan dengan bijak.
    </p>
</div>
