<div>
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <select wire:model.live="roomTypeId" class="input max-w-xs">
                @foreach ($roomTypes as $type)
                    <option value="{{ $type->id }}">{{ $type->name }}</option>
                @endforeach
            </select>
            <div class="flex items-center gap-2">
                <button wire:click="previousMonth" class="btn-outline px-3">‹</button>
                <span class="min-w-[10rem] text-center font-medium text-slate-800">{{ $monthLabel }}</span>
                <button wire:click="nextMonth" class="btn-outline px-3">›</button>
            </div>
        </div>
        <button wire:click="$toggle('showBulk')" class="btn-primary">Perbarui Massal</button>
    </div>

    @if ($showBulk)
        <div class="card mb-6 p-5">
            <h3 class="font-display text-lg font-semibold text-slate-900">Perbarui Rentang Tanggal</h3>
            <p class="mt-1 text-sm text-slate-500">Kosongkan kolom yang tidak ingin diubah. Tanggal akhir bersifat eksklusif (malam checkout tidak dihitung).</p>
            <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                <div>
                    <label class="label">Dari</label>
                    <input type="date" wire:model="bulkStart" class="input">
                    @error('bulkStart') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label">Sampai</label>
                    <input type="date" wire:model="bulkEnd" class="input">
                    @error('bulkEnd') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label">Total Kamar</label>
                    <input type="number" min="0" wire:model="bulkTotal" class="input">
                    @error('bulkTotal') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label">Diblokir</label>
                    <input type="number" min="0" wire:model="bulkBlocked" class="input">
                </div>
                <div>
                    <label class="label">Harga (Rp)</label>
                    <input type="number" min="0" wire:model="bulkPrice" class="input">
                </div>
            </div>
            <div class="mt-4 flex justify-end gap-2">
                <button wire:click="$set('showBulk', false)" class="btn-outline">Batal</button>
                <button wire:click="saveBulk" class="btn-primary">Terapkan</button>
            </div>
        </div>
    @endif

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Tanggal</th>
                        <th class="px-4 py-3 text-center">Total</th>
                        <th class="px-4 py-3 text-center">Ditahan</th>
                        <th class="px-4 py-3 text-center">Terkonfirmasi</th>
                        <th class="px-4 py-3 text-center">Diblokir</th>
                        <th class="px-4 py-3 text-center">Tersedia</th>
                        <th class="px-4 py-3">Harga</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($days as $day)
                        @php $isWeekend = in_array(\Carbon\Carbon::parse($day['date'])->dayOfWeek, [0, 6]); @endphp
                        <tr wire:key="day-{{ $day['date'] }}" class="{{ $isWeekend ? 'bg-sand-50/40' : '' }} hover:bg-slate-50">
                            <td class="px-4 py-2.5 font-medium text-slate-800">{{ \Carbon\Carbon::parse($day['date'])->translatedFormat('D, d M') }}</td>
                            <td class="px-4 py-2.5 text-center">{{ $day['total'] }}</td>
                            <td class="px-4 py-2.5 text-center text-amber-600">{{ $day['held'] }}</td>
                            <td class="px-4 py-2.5 text-center text-emerald-600">{{ $day['confirmed'] }}</td>
                            <td class="px-4 py-2.5 text-center text-slate-500">{{ $day['blocked'] }}</td>
                            <td class="px-4 py-2.5 text-center">
                                <span class="badge {{ $day['available'] > 0 ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' }}">{{ $day['available'] }}</span>
                            </td>
                            <td class="px-4 py-2.5 text-slate-700">
                                {{ rupiah($day['price']) }}
                                @if ($day['has_override'])<span class="ml-1 text-xs text-sand-600">•</span>@endif
                            </td>
                            <td class="px-4 py-2.5 text-right">
                                @if ($day['blocked'] > 0)
                                    <button wire:click="reopen('{{ $day['date'] }}')" class="btn-ghost text-xs">Buka</button>
                                @else
                                    <button wire:click="block('{{ $day['date'] }}')" class="btn-ghost text-xs text-amber-600">Blokir</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-4 py-16 text-center text-slate-500">Pilih tipe kamar untuk melihat kalender.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <p class="mt-3 text-xs text-slate-400">Tersedia = Total − Ditahan − Terkonfirmasi − Diblokir. Tanda • menunjukkan harga khusus (override).</p>
</div>
