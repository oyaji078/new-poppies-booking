<div>
    <div class="mb-6 flex items-center justify-between">
        <p class="text-sm text-slate-500">{{ $promotions->count() }} promosi</p>
        <button wire:click="openCreate" class="btn-primary">+ Promosi Baru</button>
    </div>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Nama</th>
                        <th class="px-4 py-3">Kode</th>
                        <th class="px-4 py-3">Potongan</th>
                        <th class="px-4 py-3">Syarat</th>
                        <th class="px-4 py-3">Kuota</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($promotions as $promo)
                        <tr wire:key="promo-{{ $promo->id }}" class="hover:bg-slate-50">
                            <td class="px-4 py-3">
                                <p class="font-medium text-slate-900">{{ $promo->name }}</p>
                                @if ($promo->is_automatic)<span class="badge bg-sky-100 text-sky-700">Otomatis</span>@endif
                            </td>
                            <td class="px-4 py-3 font-mono text-slate-700">{{ $promo->code ?: '—' }}</td>
                            <td class="px-4 py-3 text-slate-700">
                                @if ($promo->type->value === 'percentage')
                                    {{ $promo->value }}%
                                    @if ($promo->max_discount)<span class="text-xs text-slate-400">maks {{ rupiah($promo->max_discount) }}</span>@endif
                                @else
                                    {{ rupiah($promo->value) }}
                                @endif
                            </td>
                            <td class="px-4 py-3 text-xs text-slate-500">
                                min {{ $promo->min_nights }} malam
                                @if ($promo->min_transaction > 0) · min {{ rupiah($promo->min_transaction) }} @endif
                                @if ($promo->room_types_count > 0) · {{ $promo->room_types_count }} tipe @endif
                            </td>
                            <td class="px-4 py-3 text-slate-700">
                                {{ $promo->usage_limit ? $promo->used_count.' / '.$promo->usage_limit : 'Tak terbatas' }}
                            </td>
                            <td class="px-4 py-3">
                                <button wire:click="toggleActive({{ $promo->id }})"
                                        class="badge {{ $promo->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                                    {{ $promo->is_active ? 'Aktif' : 'Nonaktif' }}
                                </button>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <button wire:click="openEdit({{ $promo->id }})" class="btn-ghost text-xs">Edit</button>
                                <button wire:click="delete({{ $promo->id }})" wire:confirm="Hapus promosi ini?" class="btn-ghost text-xs text-rose-600">Hapus</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-16 text-center text-slate-500">Belum ada promosi.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($showForm)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/50 p-4">
            <div class="mx-auto my-8 max-w-2xl">
                <div class="card p-6">
                    <div class="flex items-center justify-between">
                        <h2 class="font-display text-xl font-semibold text-slate-900">{{ $editingId ? 'Edit Promosi' : 'Promosi Baru' }}</h2>
                        <button wire:click="$set('showForm', false)" class="text-slate-400 hover:text-slate-600">&times;</button>
                    </div>
                    <form wire:submit="save" class="mt-5 space-y-4">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="sm:col-span-2">
                                <label class="label">Nama Promosi</label>
                                <input type="text" wire:model="name" class="input @error('name') border-rose-400 @enderror">
                                @error('name') <p class="field-error">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="label">Kode <span class="text-slate-400">(kosongkan bila otomatis)</span></label>
                                <input type="text" wire:model="code" class="input @error('code') border-rose-400 @enderror">
                                @error('code') <p class="field-error">{{ $message }}</p> @enderror
                            </div>
                            <div class="flex items-end">
                                <label class="flex items-center gap-2 text-sm text-slate-700">
                                    <input type="checkbox" wire:model="is_automatic" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                                    Promo otomatis
                                </label>
                            </div>
                            <div>
                                <label class="label">Tipe Potongan</label>
                                <select wire:model.live="type" class="input">
                                    <option value="percentage">Persentase (%)</option>
                                    <option value="fixed">Nominal Tetap (Rp)</option>
                                </select>
                            </div>
                            <div>
                                <label class="label">Nilai</label>
                                <input type="number" min="1" wire:model="value" class="input @error('value') border-rose-400 @enderror">
                                @error('value') <p class="field-error">{{ $message }}</p> @enderror
                            </div>
                            @if ($type === 'percentage')
                                <div>
                                    <label class="label">Maksimal Potongan (Rp)</label>
                                    <input type="number" min="0" wire:model="max_discount" class="input">
                                </div>
                            @endif
                            <div>
                                <label class="label">Minimal Malam</label>
                                <input type="number" min="1" wire:model="min_nights" class="input">
                            </div>
                            <div>
                                <label class="label">Minimal Transaksi (Rp)</label>
                                <input type="number" min="0" wire:model="min_transaction" class="input">
                            </div>
                            <div>
                                <label class="label">Batas Penggunaan</label>
                                <input type="number" min="1" wire:model="usage_limit" class="input" placeholder="Tak terbatas">
                            </div>
                            <div>
                                <label class="label">Pemesanan Mulai</label>
                                <input type="date" wire:model="booking_start" class="input">
                            </div>
                            <div>
                                <label class="label">Pemesanan Selesai</label>
                                <input type="date" wire:model="booking_end" class="input">
                                @error('booking_end') <p class="field-error">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="label">Menginap Mulai</label>
                                <input type="date" wire:model="stay_start" class="input">
                            </div>
                            <div>
                                <label class="label">Menginap Selesai</label>
                                <input type="date" wire:model="stay_end" class="input">
                                @error('stay_end') <p class="field-error">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <div>
                            <label class="label">Berlaku untuk Tipe Kamar <span class="text-slate-400">(kosongkan = semua tipe)</span></label>
                            <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                                @foreach ($roomTypes as $rt)
                                    <label class="flex items-center gap-2 text-sm text-slate-700">
                                        <input type="checkbox" value="{{ $rt->id }}" wire:model="roomTypeIds" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                                        {{ $rt->name }}
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <label class="flex items-center gap-2 text-sm text-slate-700">
                            <input type="checkbox" wire:model="is_active" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500"> Aktif
                        </label>

                        <div class="flex justify-end gap-2 border-t border-slate-100 pt-4">
                            <button type="button" wire:click="$set('showForm', false)" class="btn-outline">Batal</button>
                            <button type="submit" class="btn-primary">Simpan</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
