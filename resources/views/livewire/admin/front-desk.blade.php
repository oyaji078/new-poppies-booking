<div>
    {{-- Tabs --}}
    <div class="mb-6 flex gap-2 border-b border-slate-200">
        @foreach (['arrivals' => 'Kedatangan Hari Ini', 'inhouse' => 'Sedang Menginap', 'departures' => 'Keberangkatan'] as $key => $label)
            <button wire:click="$set('tab', '{{ $key }}')"
                    @class([
                        'border-b-2 px-4 py-2.5 text-sm font-medium transition',
                        'border-brand-600 text-brand-700' => $tab === $key,
                        'border-transparent text-slate-500 hover:text-slate-800' => $tab !== $key,
                    ])>{{ $label }}</button>
        @endforeach
    </div>

    @if ($bookings->isEmpty())
        <div class="card p-16 text-center">
            <p class="font-display text-lg font-semibold text-slate-700">Tidak ada data</p>
            <p class="mt-1 text-sm text-slate-500">Belum ada pemesanan pada kategori ini hari ini.</p>
        </div>
    @else
        <div class="space-y-3">
            @foreach ($bookings as $booking)
                <div wire:key="fd-{{ $booking->id }}" class="card p-5">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <p class="font-mono font-semibold text-slate-900">{{ $booking->code }}</p>
                            <p class="mt-1 text-sm text-slate-700">{{ $booking->customer_name }} · {{ $booking->customer_phone }}</p>
                            <p class="mt-1 text-sm text-slate-500">
                                {{ $booking->check_in_date->translatedFormat('d M') }} – {{ $booking->check_out_date->translatedFormat('d M Y') }}
                                · {{ $booking->rooms }} kamar · {{ $booking->adults }} dewasa
                            </p>
                            @if ($tab !== 'arrivals')
                                @php $assigned = $booking->items->flatMap->assignments->pluck('room.room_number')->filter(); @endphp
                                @if ($assigned->isNotEmpty())
                                    <p class="mt-1 text-sm text-brand-700">Kamar: {{ $assigned->join(', ') }}</p>
                                @endif
                            @endif
                            @if ($booking->special_request)
                                <p class="mt-2 rounded bg-amber-50 px-2 py-1 text-xs text-amber-800">Permintaan: {{ $booking->special_request }}</p>
                            @endif
                        </div>
                        <div class="flex flex-wrap gap-2">
                            @if ($tab === 'arrivals')
                                <button wire:click="openNoShow({{ $booking->id }})" class="btn-outline text-sm text-rose-600">Tidak Hadir</button>
                                <button wire:click="openCheckIn({{ $booking->id }})" class="btn-primary text-sm">Check-in</button>
                            @else
                                <button wire:click="openCheckOut({{ $booking->id }})" class="btn-primary text-sm">Check-out</button>
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

                    <div class="mt-5 space-y-5">
                        @foreach ($checkInBooking->items as $item)
                            <div>
                                <label class="label">{{ $item->room_type_name }} — pilih {{ $item->rooms }} kamar</label>
                                @if (($availableRooms[$item->id] ?? collect())->isEmpty())
                                    <p class="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800">
                                        Tidak ada kamar fisik yang tersedia untuk tipe ini pada rentang tanggal tersebut.
                                    </p>
                                @else
                                    <div class="grid grid-cols-3 gap-2 sm:grid-cols-4">
                                        @foreach ($availableRooms[$item->id] as $room)
                                            <label class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm hover:bg-slate-50">
                                                <input type="checkbox" value="{{ $room->id }}"
                                                       wire:model="selectedRooms.{{ $item->id }}"
                                                       class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
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
