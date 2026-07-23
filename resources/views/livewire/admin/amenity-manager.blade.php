<div>
    <div class="mb-6 flex items-center justify-between">
        <p class="text-sm text-slate-500">{{ $amenities->count() }} fasilitas</p>
        <button wire:click="openCreate" class="btn-primary">+ Fasilitas Baru</button>
    </div>

    <div class="card overflow-hidden">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">Nama</th>
                    <th class="px-4 py-3">Kategori</th>
                    <th class="px-4 py-3">Dipakai</th>
                    <th class="px-4 py-3 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($amenities as $amenity)
                    <tr wire:key="am-{{ $amenity->id }}" class="hover:bg-slate-50">
                        <td class="px-4 py-3 font-medium text-slate-900">{{ $amenity->name }}</td>
                        <td class="px-4 py-3 text-slate-700">{{ $amenity->category }}</td>
                        <td class="px-4 py-3 text-slate-700">{{ $amenity->room_types_count }} tipe</td>
                        <td class="px-4 py-3 text-right">
                            <button wire:click="openEdit({{ $amenity->id }})" class="btn-ghost text-xs">Edit</button>
                            <button wire:click="delete({{ $amenity->id }})" wire:confirm="Hapus fasilitas ini?" class="btn-ghost text-xs text-rose-600">Hapus</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-16 text-center text-slate-500">Belum ada fasilitas.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($showForm)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
            <div class="card w-full max-w-md p-6">
                <div class="flex items-center justify-between">
                    <h2 class="font-display text-xl font-semibold text-slate-900">{{ $editingId ? 'Edit Fasilitas' : 'Fasilitas Baru' }}</h2>
                    <button wire:click="$set('showForm', false)" class="text-slate-400 hover:text-slate-600">&times;</button>
                </div>
                <form wire:submit="save" class="mt-5 space-y-4">
                    <div>
                        <label class="label">Nama</label>
                        <input type="text" wire:model="name" class="input @error('name') border-rose-400 @enderror">
                        @error('name') <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="label">Kategori</label>
                        <input type="text" wire:model="category" class="input" placeholder="general / bathroom / connectivity">
                    </div>
                    <div>
                        <label class="label">Ikon (opsional)</label>
                        <input type="text" wire:model="icon" class="input" placeholder="wifi">
                    </div>
                    <div class="flex justify-end gap-2 border-t border-slate-100 pt-4">
                        <button type="button" wire:click="$set('showForm', false)" class="btn-outline">Batal</button>
                        <button type="submit" class="btn-primary">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
