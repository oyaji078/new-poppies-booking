<div>
    <div class="mb-6 rounded-xl border border-orange-200 bg-orange-50 px-5 py-4 text-sm text-orange-800">
        Pemesanan di bawah ini memerlukan peninjauan manual — misalnya jumlah pembayaran tidak cocok,
        pembayaran terlambat tanpa sisa kamar, atau status dari penyedia pembayaran tidak dikenali.
        Setiap keputusan wajib disertai alasan dan tercatat di audit log.
    </div>

    @if ($bookings->isEmpty())
        <div class="card p-16 text-center">
            <div class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-emerald-100 text-2xl text-emerald-600">✓</div>
            <p class="mt-4 font-display text-lg font-semibold text-slate-700">Tidak ada pembayaran yang perlu ditinjau</p>
            <p class="mt-1 text-sm text-slate-500">Semua pembayaran terverifikasi otomatis.</p>
        </div>
    @else
        <div class="space-y-4">
            @foreach ($bookings as $booking)
                <div wire:key="rev-{{ $booking->id }}" class="card p-5">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <p class="font-mono font-semibold text-slate-900">{{ $booking->code }}</p>
                            <p class="mt-1 text-sm text-slate-600">{{ $booking->customer_name }} · {{ $booking->customer_email }}</p>
                            <p class="mt-1 text-sm text-slate-500">
                                {{ $booking->check_in_date->translatedFormat('d M Y') }} – {{ $booking->check_out_date->translatedFormat('d M Y') }}
                                · {{ $booking->rooms }} kamar · {{ $booking->items->pluck('room_type_name')->join(', ') }}
                            </p>
                        </div>
                        <div class="text-right">
                            <p class="text-xs text-slate-500">Total tagihan</p>
                            <p class="font-display text-xl font-semibold text-slate-900">{{ rupiah($booking->total_amount) }}</p>
                            @foreach ($booking->paymentAttempts->where('status.value', 'paid') as $paid)
                                <p class="text-xs text-emerald-600">Dibayar: {{ rupiah($paid->amount) }}</p>
                            @endforeach
                        </div>
                    </div>

                    @if ($actingId === $booking->id)
                        <div class="mt-4 rounded-lg border border-slate-200 bg-slate-50 p-4">
                            <label class="label">
                                Alasan {{ $action === 'approve' ? 'persetujuan' : 'penolakan' }} (wajib, tercatat di audit log)
                            </label>
                            <textarea wire:model="reason" rows="2" class="input" placeholder="Contoh: bukti transfer diverifikasi manual oleh keuangan."></textarea>
                            @error('reason') <p class="field-error">{{ $message }}</p> @enderror
                            <div class="mt-3 flex justify-end gap-2">
                                <button wire:click="cancelAction" class="btn-outline">Batal</button>
                                <button wire:click="submit" class="{{ $action === 'approve' ? 'btn-primary' : 'btn-outline text-rose-600' }}">
                                    {{ $action === 'approve' ? 'Konfirmasi Pemesanan' : 'Tolak & Batalkan' }}
                                </button>
                            </div>
                        </div>
                    @else
                        <div class="mt-4 flex justify-end gap-2 border-t border-slate-100 pt-4">
                            <button wire:click="startAction({{ $booking->id }}, 'reject')" class="btn-outline text-rose-600">Tolak</button>
                            <button wire:click="startAction({{ $booking->id }}, 'approve')" class="btn-primary">Setujui</button>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>
