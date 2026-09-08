<div>
    {{-- These chips are the colour legend AND the filter: the swatch explains
         what each card colour means, clicking it narrows the board to that
         stage, clicking it again clears the filter. --}}
    <div class="mb-6 flex flex-wrap items-center gap-2">
        <button wire:click="$set('filter', '')"
                @class([
                    'flex items-center gap-2 rounded-lg border px-3 py-1.5 text-xs font-medium transition',
                    'border-brand-500 bg-brand-50 text-brand-800' => $filter === '',
                    'border-slate-200 text-slate-600 hover:bg-slate-50' => $filter !== '',
                ])>
            Semua
            <span class="rounded bg-slate-900/10 px-1.5 py-0.5 text-[10px]">{{ $totalCount }}</span>
        </button>

        @foreach (\App\Livewire\Admin\FrontDesk::filterableStatuses() as $status)
            @php
                $tone = \App\Livewire\Admin\FrontDesk::tone($status);
                $count = $counts[$status->value] ?? 0;
                $active = $filter === $status->value;
            @endphp
            <button wire:click="setFilter('{{ $status->value }}')"
                    wire:key="filter-{{ $status->value }}"
                    @class([
                        'flex items-center gap-2 rounded-lg border px-3 py-1.5 text-xs font-medium transition',
                        'border-brand-500 bg-brand-50 text-brand-800' => $active,
                        'border-slate-200 text-slate-600 hover:bg-slate-50' => ! $active,
                        'opacity-50' => $count === 0 && ! $active,
                    ])>
                <span class="h-3 w-3 rounded-sm border {{ $tone['card'] }}"></span>
                {{ $tone['label'] }}
                <span class="rounded bg-slate-900/10 px-1.5 py-0.5 text-[10px]">{{ $count }}</span>
            </button>
        @endforeach
    </div>

    @if ($bookings->isEmpty())
        <div class="card p-16 text-center">
            <p class="font-display text-lg font-semibold text-slate-700">Tidak ada data</p>
            @if ($filter !== '')
                <p class="mt-1 text-sm text-slate-500">
                    Tidak ada pemesanan berstatus
                    <strong>{{ \App\Livewire\Admin\FrontDesk::tone(\App\Enums\BookingStatus::from($filter))['label'] }}</strong>
                    hari ini.
                </p>
                <button wire:click="$set('filter', '')" class="btn-outline mt-4 text-sm">Tampilkan semua</button>
            @else
                <p class="mt-1 text-sm text-slate-500">Belum ada kedatangan, tamu menginap, atau keberangkatan hari ini.</p>
            @endif
        </div>
    @else
        <div class="space-y-3">
            @foreach ($bookings as $booking)
                @php $tone = \App\Livewire\Admin\FrontDesk::tone($booking->status); @endphp
                <div wire:key="fd-{{ $booking->id }}" class="card p-5 transition-colors {{ $tone['card'] }}">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="font-mono font-semibold text-slate-900">{{ $booking->code }}</p>
                                <span class="badge {{ $tone['badge'] }}">{{ $tone['label'] }}</span>
                            </div>
                            <p class="mt-1 text-sm text-slate-700">{{ $booking->customer_name }} · {{ $booking->customer_phone }}</p>
                            <p class="mt-1 text-sm text-slate-500">
                                {{ $booking->check_in_date->translatedFormat('d M') }} – {{ $booking->check_out_date->translatedFormat('d M Y') }}
                                · {{ $booking->rooms }} kamar · {{ $booking->adults }} dewasa
                            </p>
                            @php $assigned = $booking->items->flatMap->assignments->pluck('room.room_number')->filter(); @endphp
                            @if ($assigned->isNotEmpty())
                                <p class="mt-1 text-sm text-brand-700">Kamar: {{ $assigned->join(', ') }}</p>
                            @endif
                            @if ($booking->special_request)
                                <p class="mt-2 rounded bg-amber-50 px-2 py-1 text-xs text-amber-800">Permintaan: {{ $booking->special_request }}</p>
                            @endif
                        </div>
                        <div class="flex flex-wrap gap-2">
                            @if ($booking->status === \App\Enums\BookingStatus::CONFIRMED)
                                <button wire:click="openNoShow({{ $booking->id }})" class="btn-outline text-sm text-rose-600">Tidak Hadir</button>
                                <button wire:click="openCheckIn({{ $booking->id }})" class="btn-primary text-sm">Check-in</button>
                            @elseif ($booking->status === \App\Enums\BookingStatus::CHECKED_IN)
                                <button wire:click="openCheckOut({{ $booking->id }})" class="btn-primary text-sm">Check-out</button>
                            @else
                                <span class="text-sm text-slate-400">Selesai</span>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Check-in modal --}}
    @if ($checkInBooking)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/50 p-4">
            <div class="mx-auto my-8 max-w-2xl">
                <div class="card p-6">
                    <div class="flex items-center justify-between">
                        <h2 class="font-display text-xl font-semibold text-slate-900">Check-in {{ $checkInBooking->code }}</h2>
                        <button wire:click="closeModals" class="text-slate-400 hover:text-slate-600">&times;</button>
                    </div>

                    @error('selectedRooms') <p class="mt-3 rounded-lg bg-rose-50 px-4 py-2 text-sm text-rose-700">{{ $message }}</p> @enderror

                    @if ($checkInBooking->rooms > 1)
                        <div class="mt-3 flex flex-wrap items-center justify-between gap-2 rounded-lg bg-slate-50 px-4 py-3">
                            <p class="text-sm text-slate-600">
                                Pemesanan ini {{ $checkInBooking->rooms }} kamar — pilih {{ $checkInBooking->rooms }} kamar fisik yang berbeda.
                            </p>
                            <button wire:click="autoAssignRooms" class="btn-outline text-sm">Pilih Otomatis</button>
                        </div>
                    @else
                        <div class="mt-3 text-right">
                            <button wire:click="autoAssignRooms" class="btn-ghost text-xs">Pilih otomatis</button>
                        </div>
                    @endif

                    <div class="mt-5 space-y-5">
                        @foreach ($checkInBooking->items as $item)
                            @php
                                $picked = array_values(array_filter((array) ($selectedRooms[$item->id] ?? [])));
                                $needed = $item->rooms;
                                $complete = count($picked) >= $needed;
                            @endphp
                            <div>
                                <div class="flex flex-wrap items-baseline justify-between gap-2">
                                    <label class="label !mb-0">{{ $item->room_type_name }}</label>
                                    <span @class([
                                        'text-xs font-medium',
                                        'text-emerald-700' => $complete,
                                        'text-slate-500' => ! $complete,
                                    ])>
                                        Dipilih {{ count($picked) }} dari {{ $needed }} kamar fisik
                                    </span>
                                </div>
                                <p class="mb-2 mt-0.5 text-xs text-slate-400">
                                    Satu kamar fisik untuk satu kamar yang dipesan. Hanya kamar yang benar-benar
                                    kosong pada tanggal menginap ini yang ditampilkan.
                                </p>

                                @php $offered = $availableRooms[$item->id] ?? collect(); @endphp

                                @if ($offered->isEmpty())
                                    <p class="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800">
                                        Tidak ada kamar fisik yang tersedia untuk tipe ini pada rentang tanggal tersebut.
                                    </p>
                                @else
                                    @if ($offered->count() < $needed)
                                        {{-- Say it now, not after the admin has filled in the whole form. --}}
                                        <p class="mb-2 rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-800">
                                            Hanya {{ $offered->count() }} kamar fisik yang kosong, sedangkan pemesanan ini
                                            butuh {{ $needed }}. Selesaikan check-out tamu sebelumnya atau bebaskan kamar
                                            dari status pemeliharaan terlebih dahulu.
                                        </p>
                                    @endif
                                    <div class="grid grid-cols-3 gap-2 sm:grid-cols-4">
                                        @foreach ($availableRooms[$item->id] as $room)
                                            @php
                                                $isPicked = in_array((string) $room->id, array_map('strval', $picked), true);
                                                // Once enough rooms are chosen, the rest are locked so the
                                                // count can never exceed what was booked.
                                                $locked = ! $isPicked && $complete;
                                            @endphp
                                            <label wire:key="room-{{ $item->id }}-{{ $room->id }}"
                                                   @class([
                                                       'flex items-center gap-2 rounded-lg border px-3 py-2 text-sm transition',
                                                       'border-brand-500 bg-brand-50 font-medium text-brand-800' => $isPicked,
                                                       'border-slate-200 opacity-40' => $locked,
                                                       'border-slate-200 hover:bg-slate-50 cursor-pointer' => ! $isPicked && ! $locked,
                                                   ])>
                                                <input type="checkbox" value="{{ $room->id }}"
                                                       wire:model.live="selectedRooms.{{ $item->id }}"
                                                       @disabled($locked)
                                                       class="rounded border-slate-300 text-brand-600 focus:ring-brand-500 disabled:opacity-50">
                                                {{ $room->room_number }}
                                            </label>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @endforeach

                        @if (today()->lt($checkInBooking->check_in_date))
                            <div>
                                <label class="label">Alasan check-in lebih awal (wajib)</label>
                                <input type="text" wire:model="earlyReason" class="input" placeholder="Kamar sudah siap lebih awal">
                            </div>
                        @endif

                        <div class="grid gap-3 sm:grid-cols-3">
                            <div>
                                <label class="label">Nama tamu (identitas)</label>
                                <input type="text" wire:model="guestName" class="input">
                            </div>
                            <div>
                                <label class="label">Jenis identitas</label>
                                <input type="text" wire:model="idCardType" class="input" placeholder="KTP / Paspor">
                            </div>
                            <div>
                                <label class="label">Nomor identitas</label>
                                <input type="text" wire:model="idCardNumber" class="input">
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end gap-2 border-t border-slate-100 pt-4">
                        <button wire:click="closeModals" class="btn-outline">Batal</button>
                        <button wire:click="submitCheckIn" class="btn-primary">Konfirmasi Check-in</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Check-out modal --}}
    @if ($checkOutBooking)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
            <div class="card w-full max-w-lg p-6">
                <div class="flex items-center justify-between">
                    <h2 class="font-display text-xl font-semibold text-slate-900">Check-out {{ $checkOutBooking->code }}</h2>
                    <button wire:click="closeModals" class="text-slate-400 hover:text-slate-600">&times;</button>
                </div>
                <div class="mt-5 space-y-4">
                    <div>
                        <label class="label">Biaya tambahan (Rp)</label>
                        <input type="number" min="0" wire:model="extraCharges" class="input">
                        <p class="mt-1 text-xs text-slate-400">Kosongkan/0 bila tidak ada. Late check-out tidak dikenakan biaya otomatis.</p>
                    </div>
                    <div>
                        <label class="label">Catatan</label>
                        <textarea wire:model="checkOutNotes" rows="2" class="input"></textarea>
                        @error('checkOutNotes') <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-2 border-t border-slate-100 pt-4">
                    <button wire:click="closeModals" class="btn-outline">Batal</button>
                    <button wire:click="submitCheckOut" class="btn-primary">Konfirmasi Check-out</button>
                </div>
            </div>
        </div>
    @endif

    {{-- No-show modal --}}
    @if ($noShowBooking)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
            <div class="card w-full max-w-lg p-6">
                <h2 class="font-display text-xl font-semibold text-slate-900">Tandai Tidak Hadir</h2>
                <p class="mt-1 text-sm text-slate-500">{{ $noShowBooking->code }} — {{ $noShowBooking->customer_name }}</p>
                <div class="mt-4">
                    <label class="label">Alasan (wajib, tercatat di audit log)</label>
                    <textarea wire:model="noShowReason" rows="2" class="input"></textarea>
                    @error('noShowReason') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <div class="mt-6 flex justify-end gap-2 border-t border-slate-100 pt-4">
                    <button wire:click="closeModals" class="btn-outline">Batal</button>
                    <button wire:click="submitNoShow" class="btn-primary">Tandai Tidak Hadir</button>
                </div>
            </div>
        </div>
    @endif
</div>
