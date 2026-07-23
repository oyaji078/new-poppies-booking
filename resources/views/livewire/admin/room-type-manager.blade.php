<div>
    {{-- Header + actions --}}
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div class="relative w-full max-w-xs">
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="Cari tipe kamar…" class="input pl-9">
            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">⌕</span>
        </div>
        <button wire:click="openCreate" class="btn-primary">+ Tipe Kamar Baru</button>
    </div>

    {{-- Table --}}
    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Nama</th>
                        <th class="px-4 py-3">Harga Dasar</th>
                        <th class="px-4 py-3">Kapasitas</th>
                        <th class="px-4 py-3">Kamar Fisik</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($roomTypes as $roomType)
                        <tr wire:key="rt-{{ $roomType->id }}" class="hover:bg-slate-50">
                            <td class="px-4 py-3">
                                <p class="font-medium text-slate-900">{{ $roomType->name }}</p>
                                <p class="text-xs text-slate-400">{{ $roomType->slug }}</p>
                            </td>
                            <td class="px-4 py-3 text-slate-700">{{ rupiah($roomType->base_price) }}</td>
                            <td class="px-4 py-3 text-slate-700">{{ $roomType->max_guests }} tamu</td>
                            <td class="px-4 py-3 text-slate-700">{{ $roomType->rooms_count }}</td>
                            <td class="px-4 py-3">
                                <button wire:click="togglePublish({{ $roomType->id }})"
                                        class="badge {{ $roomType->is_published ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                                    {{ $roomType->is_published ? 'Publik' : 'Draf' }}
                                </button>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <button wire:click="openEdit({{ $roomType->id }})" class="btn-ghost text-xs">Edit</button>
                                <button wire:click="delete({{ $roomType->id }})"
                                        wire:confirm="Hapus tipe kamar ini? Tindakan tidak dapat dibatalkan."
                                        class="btn-ghost text-xs text-rose-600">Hapus</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-16 text-center text-slate-500">
                                <p class="font-medium">Belum ada tipe kamar.</p>
                                <p class="mt-1 text-sm text-slate-400">Klik “Tipe Kamar Baru” untuk menambahkan.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($roomTypes->hasPages())
            <div class="border-t border-slate-100 px-4 py-3">{{ $roomTypes->links() }}</div>
        @endif
    </div>

    {{-- Form modal --}}
    @if ($showForm)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/50 p-4" wire:key="form-modal">
            <div class="mx-auto my-8 max-w-3xl">
                <div class="card p-6">
                    <div class="flex items-center justify-between">
                        <h2 class="font-display text-xl font-semibold text-slate-900">
                            {{ $editingId ? 'Edit Tipe Kamar' : 'Tipe Kamar Baru' }}
                        </h2>
                        <button wire:click="$set('showForm', false)" class="text-slate-400 hover:text-slate-600">&times;</button>
                    </div>

                    <form wire:submit="save" class="mt-5 space-y-4">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="sm:col-span-2">
                                <label class="label">Nama Tipe Kamar</label>
                                <input type="text" wire:model="name" class="input @error('name') border-rose-400 @enderror">
                                @error('name') <p class="field-error">{{ $message }}</p> @enderror
                            </div>
                            <div class="sm:col-span-2">
                                <label class="label">Deskripsi Singkat</label>
                                <input type="text" wire:model="short_description" class="input" maxlength="500">
                                @error('short_description') <p class="field-error">{{ $message }}</p> @enderror
                            </div>
                            <div class="sm:col-span-2">
                                <label class="label">Deskripsi Lengkap</label>
                                <textarea wire:model="full_description" rows="4" class="input"></textarea>
                            </div>
                            <div>
                                <label class="label">Harga Dasar (Rp / malam)</label>
                                <input type="number" wire:model="base_price" min="0" class="input @error('base_price') border-rose-400 @enderror">
                                @error('base_price') <p class="field-error">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="label">Jumlah Kamar (inventaris default)</label>
                                <input type="number" wire:model="default_inventory" min="0" class="input @error('default_inventory') border-rose-400 @enderror">
                                @error('default_inventory') <p class="field-error">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="label">Kapasitas Dewasa</label>
                                <input type="number" wire:model="adult_capacity" min="1" class="input">
                                @error('adult_capacity') <p class="field-error">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="label">Kapasitas Anak</label>
                                <input type="number" wire:model="child_capacity" min="0" class="input">
                            </div>
                            <div>
                                <label class="label">Maksimal Tamu</label>
                                <input type="number" wire:model="max_guests" min="1" class="input">
                                @error('max_guests') <p class="field-error">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="label">Tipe Kasur</label>
                                <input type="text" wire:model="bed_type" class="input" placeholder="1 King Bed">
                            </div>
                            <div>
                                <label class="label">Luas Kamar (m²)</label>
                                <input type="number" wire:model="room_size" min="1" class="input">
                            </div>
                            <div class="flex items-end">
                                <label class="flex items-center gap-2 text-sm text-slate-700">
                                    <input type="checkbox" wire:model="is_published" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                                    Publikasikan
                                </label>
                            </div>
                            <div class="sm:col-span-2">
                                <label class="label">Kebijakan</label>
                                <textarea wire:model="policies" rows="2" class="input"></textarea>
                            </div>
                        </div>

                        {{-- Amenities --}}
                        <div>
                            <label class="label">Fasilitas</label>
                            @if ($amenities->isEmpty())
                                <p class="text-sm text-slate-400">Belum ada data fasilitas. Tambahkan di menu Fasilitas.</p>
                            @else
                                <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                                    @foreach ($amenities as $amenity)
                                        <label class="flex items-center gap-2 text-sm text-slate-700">
                                            <input type="checkbox" value="{{ $amenity->id }}" wire:model="amenityIds"
                                                   class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                                            {{ $amenity->name }}
                                        </label>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        {{-- Existing images --}}
                        @if ($editingImages->isNotEmpty())
                            <div>
                                <label class="label">Foto Kamar</label>
                                <div class="grid grid-cols-3 gap-3 sm:grid-cols-4">
                                    @foreach ($editingImages as $image)
                                        <div wire:key="img-{{ $image->id }}" class="group relative overflow-hidden rounded-lg border {{ $image->is_primary ? 'border-brand-500 ring-2 ring-brand-500/40' : 'border-slate-200' }}">
                                            <img src="{{ $image->url }}" class="aspect-square w-full object-cover">
                                            @if ($image->is_primary)
                                                <span class="absolute left-1 top-1 badge bg-brand-600 text-white">Utama</span>
                                            @endif
                                            <div class="absolute inset-x-0 bottom-0 flex justify-between gap-1 bg-slate-900/60 p-1 opacity-0 transition group-hover:opacity-100">
                                                @unless ($image->is_primary)
                                                    <button type="button" wire:click="makePrimary({{ $image->id }})" class="text-xs text-white hover:underline">Jadikan utama</button>
                                                @endunless
                                                <button type="button" wire:click="deleteImage({{ $image->id }})" class="text-xs text-rose-300 hover:underline">Hapus</button>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        {{-- Upload new images --}}
                        <div>
                            <label class="label">Tambah Foto (JPG/PNG/WebP, maks 4MB)</label>
                            <input type="file" wire:model="newImages" multiple accept="image/*"
                                   class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-4 file:py-2 file:text-sm file:font-medium file:text-brand-700 hover:file:bg-brand-100">
                            <div wire:loading wire:target="newImages" class="mt-1 text-xs text-slate-400">Mengunggah…</div>
                            @error('newImages.*') <p class="field-error">{{ $message }}</p> @enderror
                        </div>

                        <div class="flex justify-end gap-2 border-t border-slate-100 pt-4">
                            <button type="button" wire:click="$set('showForm', false)" class="btn-outline">Batal</button>
                            <button type="submit" class="btn-primary">
                                <span wire:loading.remove wire:target="save">Simpan</span>
                                <span wire:loading wire:target="save">Menyimpan…</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
