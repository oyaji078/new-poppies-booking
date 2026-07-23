<div>
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div class="relative w-full max-w-xs">
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="Cari nomor kamar…" class="input pl-9">
            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">⌕</span>
        </div>
        <button wire:click="openCreate" class="btn-primary">+ Kamar Baru</button>
    </div>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Nomor</th>
                        <th class="px-4 py-3">Tipe</th>
                        <th class="px-4 py-3">Lantai</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($rooms as $room)
                        <tr wire:key="room-{{ $room->id }}" class="hover:bg-slate-50">
                            <td class="px-4 py-3 font-medium text-slate-900">{{ $room->room_number }}</td>
                            <td class="px-4 py-3 text-slate-700">{{ $room->roomType->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-slate-700">{{ $room->floor ?: '—' }}</td>
                            <td class="px-4 py-3">
                                @if ($room->under_maintenance)
                                    <span class="badge bg-amber-100 text-amber-700">Pemeliharaan</span>
                                @elseif ($room->is_active)
                                    <span class="badge bg-emerald-100 text-emerald-700">Aktif</span>
                                @else
                                    <span class="badge bg-slate-100 text-slate-500">Nonaktif</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                <button wire:click="toggleMaintenance({{ $room->id }})" class="btn-ghost text-xs">
                                    {{ $room->under_maintenance ? 'Selesai Pemeliharaan' : 'Set Pemeliharaan' }}
                                </button>
                                <button wire:click="openEdit({{ $room->id }})" class="btn-ghost text-xs">Edit</button>
                                <button wire:click="delete({{ $room->id }})" wire:confirm="Hapus kamar ini?" class="btn-ghost text-xs text-rose-600">Hapus</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-16 text-center text-slate-500">Belum ada kamar fisik.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($rooms->hasPages())
            <div class="border-t border-slate-100 px-4 py-3">{{ $rooms->links() }}</div>
        @endif
    </div>

    @if ($showForm)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/50 p-4">
            <div class="mx-auto my-8 max-w-lg">
                <div class="card p-6">
                    <div class="flex items-center justify-between">
                        <h2 class="font-display text-xl font-semibold text-slate-900">{{ $editingId ? 'Edit Kamar' : 'Kamar Baru' }}</h2>
                        <button wire:click="$set('showForm', false)" class="text-slate-400 hover:text-slate-600">&times;</button>
                    </div>
                    <form wire:submit="save" class="mt-5 space-y-4">
                        <div>
                            <label class="label">Nomor Kamar</label>
                            <input type="text" wire:model="room_number" class="input @error('room_number') border-rose-400 @enderror" placeholder="STD-101">
                            @error('room_number') <p class="field-error">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="label">Tipe Kamar</label>
                            <select wire:model="room_type_id" class="input @error('room_type_id') border-rose-400 @enderror">
                                <option value="">— Pilih —</option>
                                @foreach ($roomTypes as $type)
                                    <option value="{{ $type->id }}">{{ $type->name }}</option>
                                @endforeach
                            </select>
                            @error('room_type_id') <p class="field-error">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="label">Lantai</label>
                            <input type="text" wire:model="floor" class="input">
                        </div>
                        <div class="flex gap-6">
                            <label class="flex items-center gap-2 text-sm text-slate-700">
                                <input type="checkbox" wire:model="is_active" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500"> Aktif
                            </label>
                            <label class="flex items-center gap-2 text-sm text-slate-700">
                                <input type="checkbox" wire:model="under_maintenance" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500"> Dalam pemeliharaan
                            </label>
                        </div>
                        <div>
                            <label class="label">Catatan Internal</label>
                            <textarea wire:model="internal_notes" rows="2" class="input"></textarea>
                        </div>
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
