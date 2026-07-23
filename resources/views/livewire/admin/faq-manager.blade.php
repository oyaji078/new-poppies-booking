<div>
    <div class="mb-6 flex items-center justify-between">
        <div class="flex gap-2 border-b border-slate-200">
            @foreach (['faqs' => 'Daftar FAQ', 'unanswered' => 'Belum Terjawab ('.$unanswered->count().')'] as $key => $label)
                <button wire:click="$set('tab', '{{ $key }}')"
                        @class([
                            'border-b-2 px-4 py-2.5 text-sm font-medium transition',
                            'border-brand-600 text-brand-700' => $tab === $key,
                            'border-transparent text-slate-500 hover:text-slate-800' => $tab !== $key,
                        ])>{{ $label }}</button>
            @endforeach
        </div>
        <button wire:click="openCreate" class="btn-primary">+ FAQ Baru</button>
    </div>

    @if ($tab === 'faqs')
        <div class="card overflow-hidden">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Pertanyaan</th>
                        <th class="px-4 py-3">Kata Kunci</th>
                        <th class="px-4 py-3">Kategori</th>
                        <th class="px-4 py-3 text-center">Prioritas</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($faqs as $faq)
                        <tr wire:key="faq-{{ $faq->id }}" class="hover:bg-slate-50">
                            <td class="px-4 py-3">
                                <p class="font-medium text-slate-900">{{ $faq->question }}</p>
                                <p class="mt-0.5 line-clamp-1 text-xs text-slate-400">{{ $faq->answer }}</p>
                            </td>
                            <td class="px-4 py-3 max-w-[14rem] text-xs text-slate-500">{{ $faq->keywords ?: '—' }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $faq->category }}</td>
                            <td class="px-4 py-3 text-center text-slate-600">{{ $faq->priority }}</td>
                            <td class="px-4 py-3">
                                <button wire:click="toggleActive({{ $faq->id }})"
                                        class="badge {{ $faq->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                                    {{ $faq->is_active ? 'Aktif' : 'Nonaktif' }}
                                </button>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <button wire:click="openEdit({{ $faq->id }})" class="btn-ghost text-xs">Edit</button>
                                <button wire:click="delete({{ $faq->id }})" wire:confirm="Hapus FAQ ini?" class="btn-ghost text-xs text-rose-600">Hapus</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-16 text-center text-slate-500">Belum ada FAQ.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @else
        <div class="mb-4 rounded-xl border border-sky-200 bg-sky-50 px-5 py-3 text-sm text-sky-800">
            Pertanyaan yang belum bisa dijawab chatbot. Jadikan FAQ agar chatbot dapat menjawabnya lain kali.
        </div>
        <div class="card overflow-hidden">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr><th class="px-4 py-3">Pertanyaan Tamu</th><th class="px-4 py-3">Waktu</th><th class="px-4 py-3 text-right">Aksi</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($unanswered as $row)
                        <tr wire:key="ua-{{ $row['id'] }}" class="hover:bg-slate-50">
                            <td class="px-4 py-3 text-slate-800">{{ $row['content'] }}</td>
                            <td class="px-4 py-3 text-slate-500">{{ $row['at']?->translatedFormat('d M Y H:i') }}</td>
                            <td class="px-4 py-3 text-right">
                                <button wire:click="draftFrom({{ $row['id'] }})" class="btn-ghost text-xs">Jadikan FAQ</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="px-4 py-16 text-center text-slate-500">Semua pertanyaan terjawab.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif

    @if ($showForm)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/50 p-4">
            <div class="mx-auto my-8 max-w-2xl">
                <div class="card p-6">
                    <div class="flex items-center justify-between">
                        <h2 class="font-display text-xl font-semibold text-slate-900">{{ $editingId ? 'Edit FAQ' : 'FAQ Baru' }}</h2>
                        <button wire:click="$set('showForm', false)" class="text-slate-400 hover:text-slate-600">&times;</button>
                    </div>
                    <form wire:submit="save" class="mt-5 space-y-4">
                        <div>
                            <label class="label">Pertanyaan</label>
                            <input type="text" wire:model="question" class="input @error('question') border-rose-400 @enderror">
                            @error('question') <p class="field-error">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="label">Jawaban</label>
                            <textarea wire:model="answer" rows="5" class="input @error('answer') border-rose-400 @enderror"></textarea>
                            @error('answer') <p class="field-error">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="label">Kata kunci (pisahkan dengan koma)</label>
                            <input type="text" wire:model="keywords" class="input" placeholder="wifi, internet, jaringan">
                            <p class="mt-1 text-xs text-slate-400">Chatbot mencocokkan pertanyaan tamu dengan kata kunci ini.</p>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-3">
                            <div>
                                <label class="label">Kategori</label>
                                <input type="text" wire:model="category" class="input">
                            </div>
                            <div>
                                <label class="label">Prioritas</label>
                                <input type="number" min="0" max="100" wire:model="priority" class="input">
                            </div>
                            <div class="flex items-end">
                                <label class="flex items-center gap-2 text-sm text-slate-700">
                                    <input type="checkbox" wire:model="is_active" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500"> Aktif
                                </label>
                            </div>
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
