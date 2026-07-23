<div class="fixed bottom-5 right-5 z-50 print:hidden">
    {{-- Panel --}}
    @if ($open)
        <div class="mb-3 flex h-[28rem] w-[21rem] flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl sm:w-96">
            <div class="flex items-center justify-between bg-brand-700 px-4 py-3 text-white">
                <div class="flex items-center gap-2">
                    <span class="grid h-8 w-8 place-items-center rounded-lg bg-white/15 text-sm font-semibold">NP</span>
                    <div class="leading-tight">
                        <p class="text-sm font-semibold">Asisten New Poppies</p>
                        <p class="text-xs text-brand-100">Biasanya membalas seketika</p>
                    </div>
                </div>
                <button wire:click="$set('open', false)" class="text-white/80 hover:text-white" aria-label="Tutup">&times;</button>
            </div>

            <div class="flex-1 space-y-3 overflow-y-auto bg-slate-50 p-4">
                @foreach ($messages as $message)
                    <div class="flex {{ $message['role'] === 'user' ? 'justify-end' : 'justify-start' }}">
                        <div @class([
                            'max-w-[85%] whitespace-pre-line rounded-2xl px-3.5 py-2 text-sm',
                            'bg-brand-600 text-white' => $message['role'] === 'user',
                            'bg-white text-slate-700 shadow-sm' => $message['role'] !== 'user',
                        ])>{{ $message['content'] }}</div>
                    </div>
                @endforeach

                <div wire:loading wire:target="send, askSuggestion" class="flex justify-start">
                    <div class="rounded-2xl bg-white px-3.5 py-2 text-sm text-slate-400 shadow-sm">Mengetik…</div>
                </div>
            </div>

            @if ($suggestions)
                <div class="flex flex-wrap gap-1.5 border-t border-slate-100 bg-white px-3 py-2">
                    @foreach ($suggestions as $suggestion)
                        <button wire:click="askSuggestion('{{ addslashes($suggestion) }}')"
                                class="rounded-full border border-slate-200 px-2.5 py-1 text-xs text-slate-600 hover:border-brand-300 hover:bg-brand-50 hover:text-brand-700">
                            {{ $suggestion }}
                        </button>
                    @endforeach
                </div>
            @endif

            <form wire:submit="send" class="flex items-center gap-2 border-t border-slate-200 bg-white p-3">
                <input type="text" wire:model="draft" maxlength="500" class="input" placeholder="Tulis pertanyaan…" autocomplete="off">
                <button type="submit" class="btn-primary shrink-0 px-3" aria-label="Kirim">→</button>
            </form>
        </div>
    @endif

    {{-- Launcher --}}
    <button wire:click="$toggle('open')"
            class="ml-auto flex h-14 w-14 items-center justify-center rounded-full bg-brand-600 text-white shadow-lg transition hover:bg-brand-700"
            aria-label="Buka chat bantuan">
        @if ($open)
            <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 6l12 12M18 6L6 18"/></svg>
        @else
            <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 10.5h8M8 14h5M21 12a8.5 8.5 0 01-12.4 7.6L3 21l1.5-5A8.5 8.5 0 1121 12z"/></svg>
        @endif
    </button>
</div>
