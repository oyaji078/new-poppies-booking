<div>
    <div class="mb-6 rounded-xl border border-slate-200 bg-white px-5 py-4 text-sm text-slate-600">
        Catatan permanen setiap tindakan penting: siapa, kapan, dari IP mana, dan apa yang berubah.
        Halaman ini hanya bisa dibaca — baris audit tidak dapat diubah atau dihapus dari antarmuka.
    </div>

    {{-- Filters --}}
    <div class="card p-4">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
            <div class="lg:col-span-2">
                <label class="label">Cari</label>
                <input type="text" wire:model.live.debounce.400ms="search" class="input"
                       placeholder="Nama admin, ID entitas, atau IP">
            </div>
            <div>
                <label class="label">Aksi</label>
                <select wire:model.live="action" class="input">
                    <option value="">Semua aksi</option>
                    @foreach ($usedActions as $option)
                        <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="label">Dari tanggal</label>
                <input type="date" wire:model.live="from" class="input">
            </div>
            <div>
                <label class="label">Sampai tanggal</label>
                <input type="date" wire:model.live="until" class="input">
            </div>
        </div>
        <div class="mt-3 flex items-center justify-between">
            <p class="text-xs text-slate-500">
                {{ number_format($logs->total()) }} dari {{ number_format($totalCount) }} baris audit
            </p>
            <button wire:click="resetFilters" class="btn-ghost text-xs">Atur ulang filter</button>
        </div>
    </div>

    <div class="card mt-4 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Waktu (WITA)</th>
                        <th class="px-4 py-3">Aktor</th>
                        <th class="px-4 py-3">Aksi</th>
                        <th class="px-4 py-3">Entitas</th>
                        <th class="px-4 py-3">IP</th>
                        <th class="px-4 py-3 text-right">Detail</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($logs as $log)
                        @php
                            $enum = \App\Enums\AuditAction::tryFrom($log->action);
                            $entity = $log->entity_type ? class_basename($log->entity_type) : null;
                        @endphp
                        <tr wire:key="audit-{{ $log->id }}" class="hover:bg-slate-50">
                            <td class="whitespace-nowrap px-4 py-3 text-slate-600">
                                {{ $log->created_at?->translatedFormat('d M Y H:i') ?? '—' }}
                            </td>
                            <td class="px-4 py-3">
                                @if ($log->user)
                                    <span class="font-medium text-slate-900">{{ $log->user->name }}</span>
                                @else
                                    {{-- Scheduler, webhook and guest-initiated actions have no admin behind them. --}}
                                    <span class="text-slate-400">Sistem</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <span class="badge {{ $enum?->isSensitive() ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-600' }}">
                                    {{ $enum?->label() ?? $log->action }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-slate-600">
                                {{ $entity ? $entity.' #'.$log->entity_id : '—' }}
                            </td>
                            <td class="px-4 py-3 font-mono text-xs text-slate-500">{{ $log->ip_address ?: '—' }}</td>
                            <td class="px-4 py-3 text-right">
                                @if ($log->old_values || $log->new_values)
                                    <button wire:click="toggleDetail({{ $log->id }})" class="btn-ghost text-xs">
                                        {{ $expandedId === $log->id ? 'Tutup' : 'Lihat' }}
                                    </button>
                                @else
                                    <span class="text-xs text-slate-300">—</span>
                                @endif
                            </td>
                        </tr>
                        @if ($expandedId === $log->id)
                            <tr wire:key="audit-detail-{{ $log->id }}" class="bg-slate-50">
                                <td colspan="6" class="px-4 py-4">
                                    <div class="grid gap-4 sm:grid-cols-2">
                                        <div>
                                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Sebelum</p>
                                            <pre class="mt-1 overflow-x-auto rounded-lg bg-white p-3 text-xs text-slate-700">{{ $log->old_values ? json_encode($log->old_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '—' }}</pre>
                                        </div>
                                        <div>
                                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Sesudah</p>
                                            <pre class="mt-1 overflow-x-auto rounded-lg bg-white p-3 text-xs text-slate-700">{{ $log->new_values ? json_encode($log->new_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '—' }}</pre>
                                        </div>
                                    </div>
                                    @if ($log->user_agent)
                                        <p class="mt-3 truncate text-xs text-slate-400">User agent: {{ $log->user_agent }}</p>
                                    @endif
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-16 text-center text-slate-500">
                                <p class="font-medium">Belum ada catatan audit</p>
                                <p class="mt-1 text-sm text-slate-400">
                                    Baris akan muncul otomatis saat ada check-in, pembatalan, refund,
                                    perubahan pengaturan, atau tindakan admin lainnya.
                                </p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $logs->links() }}</div>
</div>
