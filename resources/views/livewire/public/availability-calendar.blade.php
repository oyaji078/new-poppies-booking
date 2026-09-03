<div class="border-t border-slate-100 pt-5">
    <div class="flex items-center justify-between">
        <h3 class="font-display font-semibold text-slate-900">Pilih Tanggal Menginap</h3>
        <div class="flex items-center gap-1">
            <button type="button" wire:click="previousMonth" @disabled(! $canPageBack)
                    class="grid h-8 w-8 place-items-center rounded-lg text-slate-500 transition hover:bg-slate-100 disabled:opacity-30 disabled:hover:bg-transparent"
                    aria-label="Bulan sebelumnya">‹</button>
            <button type="button" wire:click="nextMonth"
                    class="grid h-8 w-8 place-items-center rounded-lg text-slate-500 transition hover:bg-slate-100"
                    aria-label="Bulan berikutnya">›</button>
        </div>
    </div>

    <p class="mt-1 text-center text-sm font-medium text-slate-700">{{ $monthLabel }}</p>

    {{-- Weekday header, Monday first (Indonesian convention). --}}
    <div class="mt-3 grid grid-cols-7 gap-1 text-center text-[11px] font-semibold uppercase text-slate-400">
        @foreach (['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'] as $label)
            <div>{{ $label }}</div>
        @endforeach
    </div>

    <div class="mt-1 grid grid-cols-7 gap-1" wire:loading.class="opacity-50" wire:target="previousMonth,nextMonth,selectDate">
        @foreach ($days as $day)
            @php
                $classes = match (true) {
                    $day['is_check_in'] || $day['is_check_out'] => 'bg-brand-600 text-white font-semibold',
                    $day['in_range'] => 'bg-brand-50 text-brand-800',
                    $day['is_past'] => 'text-slate-300',
                    $day['is_full'] => 'text-rose-300 line-through',
                    ! $day['selectable'] => 'text-slate-300',
                    default => 'text-slate-700 hover:bg-brand-50 hover:text-brand-800',
                };
            @endphp
            <button type="button"
                    @if ($day['selectable']) wire:click="selectDate('{{ $day['date'] }}')" @else disabled @endif
                    wire:key="cal-{{ $day['date'] }}"
                    @class([
                        'relative flex h-10 flex-col items-center justify-center rounded-lg text-sm transition',
                        'opacity-40' => ! $day['in_month'],
                        'ring-1 ring-inset ring-brand-300' => $day['is_today'] && ! $day['is_check_in'] && ! $day['is_check_out'],
                        'cursor-not-allowed' => ! $day['selectable'],
                        $classes,
                    ])
                    @if ($day['is_full'] && ! $day['is_past']) title="Penuh" @elseif ($day['free'] > 0 && ! $day['is_past']) title="Sisa {{ $day['free'] }} kamar" @endif>
                {{ $day['day'] }}
                @if (! $day['is_past'] && ! $day['is_full'] && $day['free'] <= 2 && ! $day['is_check_in'] && ! $day['is_check_out'] && ! $day['in_range'])
                    <span class="text-[9px] leading-none text-amber-600">sisa {{ $day['free'] }}</span>
                @endif
            </button>
        @endforeach
    </div>

    {{-- Legend --}}
    <div class="mt-3 flex flex-wrap gap-3 text-[11px] text-slate-500">
        <span class="flex items-center gap-1"><span class="h-2.5 w-2.5 rounded-sm bg-brand-600"></span>Pilihan Anda</span>
        <span class="flex items-center gap-1"><span class="h-2.5 w-2.5 rounded-sm border border-slate-300 bg-white"></span>Tersedia</span>
        <span class="flex items-center gap-1"><span class="text-rose-300 line-through">00</span>Penuh</span>
    </div>

    {{-- Selection state --}}
    <div class="mt-4 rounded-xl bg-slate-50 px-4 py-3 text-sm">
        @if ($checkIn === '')
            <p class="text-slate-600">Pilih tanggal <strong>check-in</strong> pada kalender.</p>
        @elseif ($checkOut === '')
            <p class="text-slate-600">
                Check-in <strong>{{ \Carbon\CarbonImmutable::parse($checkIn)->translatedFormat('d M Y') }}</strong>.
                Sekarang pilih tanggal <strong>check-out</strong>.
            </p>
            <p class="mt-1 text-xs text-slate-400">
                Tanggal check-out tidak dihitung sebagai malam menginap, jadi boleh jatuh pada tanggal yang penuh.
            </p>
        @else
            <div class="flex items-center justify-between gap-3">
                <div>
                    <p class="text-slate-900">
                        {{ \Carbon\CarbonImmutable::parse($checkIn)->translatedFormat('d M') }} –
                        {{ \Carbon\CarbonImmutable::parse($checkOut)->translatedFormat('d M Y') }}
                    </p>
                    <p class="text-xs text-slate-500">{{ $nights }} malam · {{ $rooms }} kamar</p>
                </div>
                <button type="button" wire:click="clearSelection" class="text-xs font-medium text-slate-500 underline">Ubah</button>
            </div>
        @endif
    </div>

    {{-- Party size --}}
    <div class="mt-3 grid grid-cols-3 gap-2">
        <div>
            <label class="label !mb-1 !text-xs">Kamar</label>
            <input type="number" min="1" max="10" wire:model.live="rooms" class="input !py-1.5 !text-sm">
        </div>
        <div>
            <label class="label !mb-1 !text-xs">Dewasa</label>
            <input type="number" min="1" max="20" wire:model.live="adults" class="input !py-1.5 !text-sm">
        </div>
        <div>
            <label class="label !mb-1 !text-xs">Anak</label>
            <input type="number" min="0" max="20" wire:model.live="children" class="input !py-1.5 !text-sm">
        </div>
    </div>

    @if ($checkIn !== '' && $checkOut !== '')
        <a href="{{ route('checkout', [
                'roomType' => $roomType->slug,
                'checkin' => $checkIn,
                'checkout' => $checkOut,
                'adults' => $adults,
                'children' => $children,
                'rooms' => $rooms,
            ]) }}" class="btn-primary mt-4 w-full">
            Pesan {{ $nights }} Malam
        </a>
    @else
        <button type="button" disabled class="btn-primary mt-4 w-full opacity-50">Pilih tanggal dahulu</button>
    @endif
</div>
