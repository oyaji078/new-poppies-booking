<div>
    {{-- Upload panel --}}
    <div class="card p-6">
        <h2 class="font-display text-lg font-semibold text-slate-900">Tambah Foto</h2>
        <p class="mt-1 text-sm text-slate-500">
            Format JPG, PNG, atau WEBP. Maksimal 4 MB per foto. Gunakan foto mendatar
            (landscape) agar tampil rapi di halaman publik.
        </p>

        <form wire:submit="upload" class="mt-5 grid gap-4 sm:grid-cols-3">
            <div class="sm:col-span-2">
                <label class="label">Pilih foto (bisa lebih dari satu)</label>
                <input type="file" wire:model="newImages" multiple accept="image/jpeg,image/png,image/webp"
                       class="input file:mr-3 file:rounded file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:text-sm file:text-slate-700">
                @error('newImages') <p class="field-error">{{ $message }}</p> @enderror
                @error('newImages.*') <p class="field-error">{{ $message }}</p> @enderror
                <div wire:loading wire:target="newImages" class="mt-1 text-xs text-slate-500">Mengunggah…</div>
            </div>
            <div>
                <label class="label">Keterangan (opsional)</label>
                <input type="text" wire:model="newTitle" class="input" placeholder="Kolam renang">
            </div>
            <div class="sm:col-span-3">
                <button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="upload,newImages">
                    Unggah ke Galeri
                </button>
            </div>
        </form>
    </div>

    {{-- Current gallery --}}
    <div class="mt-6 flex flex-wrap items-center justify-between gap-2">
        <p class="text-sm text-slate-500">
            {{ $images->count() }} foto · <span class="font-medium text-slate-700">{{ $publishedCount }}</span> tampil di halaman publik
        </p>
        <a href="{{ route('home') }}#gallery" target="_blank" class="text-sm font-medium text-brand-700 hover:underline">
            Lihat galeri di situs ↗
        </a>
    </div>

    @if ($images->isEmpty())
        <div class="card mt-3 p-16 text-center">
            <p class="font-display text-lg font-semibold text-slate-700">Galeri masih kosong</p>
            <p class="mt-1 text-sm text-slate-500">
                Selama belum ada foto, halaman publik menampilkan kotak placeholder.
            </p>
        </div>
    @else
        <div class="mt-3 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($images as $index => $image)
                <div wire:key="gal-{{ $image->id }}"
                     class="card overflow-hidden {{ $image->is_published ? '' : 'opacity-60' }}">
                    <div class="relative aspect-[4/3] bg-slate-100">
                        <img src="{{ $image->url }}" alt="{{ $image->alt_text }}"
                             class="h-full w-full object-cover" loading="lazy">
                        <span class="absolute left-2 top-2 badge {{ $image->is_published ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600' }}">
                            {{ $image->is_published ? 'Tampil' : 'Disembunyikan' }}
                        </span>
                        <span class="absolute right-2 top-2 badge bg-slate-900/70 text-white">#{{ $index + 1 }}</span>
                    </div>

                    @if ($editingId === $image->id)
                        <div class="space-y-3 p-4">
                            <div>
                                <label class="label">Keterangan</label>
                                <input type="text" wire:model="title" class="input" placeholder="Kolam renang">
                                @error('title') <p class="field-error">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="label">Teks alternatif (aksesibilitas)</label>
                                <input type="text" wire:model="alt" class="input" placeholder="Kolam renang menghadap taman">
                                @error('alt') <p class="field-error">{{ $message }}</p> @enderror
                            </div>
                            <div class="flex justify-end gap-2">
                                <button wire:click="cancelEdit" class="btn-outline text-sm">Batal</button>
                                <button wire:click="saveEdit" class="btn-primary text-sm">Simpan</button>
                            </div>
                        </div>
                    @else
                        <div class="p-4">
                            <p class="truncate font-medium text-slate-900">{{ $image->title ?: '—' }}</p>
                            <p class="mt-0.5 truncate text-xs text-slate-500">{{ $image->original_name }}</p>

                            <div class="mt-3 flex flex-wrap items-center gap-1">
                                <button wire:click="moveUp({{ $image->id }})" class="btn-ghost px-2 py-1 text-xs"
                                        @disabled($loop->first) title="Naikkan urutan">↑</button>
                                <button wire:click="moveDown({{ $image->id }})" class="btn-ghost px-2 py-1 text-xs"
                                        @disabled($loop->last) title="Turunkan urutan">↓</button>
                                <button wire:click="startEdit({{ $image->id }})" class="btn-ghost px-2 py-1 text-xs">Keterangan</button>
                                <button wire:click="togglePublish({{ $image->id }})" class="btn-ghost px-2 py-1 text-xs">
                                    {{ $image->is_published ? 'Sembunyikan' : 'Tampilkan' }}
                                </button>
                                <button wire:click="delete({{ $image->id }})" wire:confirm="Hapus foto ini dari galeri?"
                                        class="btn-ghost px-2 py-1 text-xs text-rose-600">Hapus</button>
                            </div>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>
