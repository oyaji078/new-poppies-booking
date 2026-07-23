<div>
    <div class="mb-6 flex items-center justify-between">
        <p class="text-sm text-slate-500">{{ $pages->count() }} halaman konten</p>
        <button wire:click="openCreate" class="btn-primary">+ Halaman Baru</button>
    </div>

    <div class="card overflow-hidden">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">Judul</th>
                    <th class="px-4 py-3">Slug</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($pages as $page)
                    <tr wire:key="pg-{{ $page->id }}" class="hover:bg-slate-50">
                        <td class="px-4 py-3 font-medium text-slate-900">{{ $page->title }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ $page->slug }}</td>
                        <td class="px-4 py-3">
                            <span class="badge {{ $page->is_published ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                                {{ $page->is_published ? 'Publik' : 'Draf' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <button wire:click="openEdit({{ $page->id }})" class="btn-ghost text-xs">Edit</button>
                            <button wire:click="delete({{ $page->id }})" wire:confirm="Hapus halaman ini?" class="btn-ghost text-xs text-rose-600">Hapus</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-16 text-center text-slate-500">Belum ada halaman konten.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($showForm)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/50 p-4">
            <div class="mx-auto my-8 max-w-2xl">
                <div class="card p-6">
                    <div class="flex items-center justify-between">
                        <h2 class="font-display text-xl font-semibold text-slate-900">{{ $editingId ? 'Edit Halaman' : 'Halaman Baru' }}</h2>
                        <button wire:click="$set('showForm', false)" class="text-slate-400 hover:text-slate-600">&times;</button>
                    </div>
                    <form wire:submit="save" class="mt-5 space-y-4">
                        <div>
                            <label class="label">Judul</label>
                            <input type="text" wire:model.live="title" class="input @error('title') border-rose-400 @enderror">
                            @error('title') <p class="field-error">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="label">Slug</label>
                            <input type="text" wire:model="slug" class="input @error('slug') border-rose-400 @enderror">
                            @error('slug') <p class="field-error">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="label">Isi Konten</label>
                            <textarea wire:model="body" rows="8" class="input"></textarea>
                        </div>
                        <label class="flex items-center gap-2 text-sm text-slate-700">
                            <input type="checkbox" wire:model="is_published" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500"> Publikasikan
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
