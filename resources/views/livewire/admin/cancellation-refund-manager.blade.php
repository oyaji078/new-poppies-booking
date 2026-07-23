<div>
    <div class="mb-6 flex gap-2 border-b border-slate-200">
        @foreach (['cancellations' => 'Pembatalan', 'refunds' => 'Refund'] as $key => $label)
            <button wire:click="$set('tab', '{{ $key }}')"
                    @class([
                        'border-b-2 px-4 py-2.5 text-sm font-medium transition',
                        'border-brand-600 text-brand-700' => $tab === $key,
                        'border-transparent text-slate-500 hover:text-slate-800' => $tab !== $key,
                    ])>{{ $label }}</button>
        @endforeach
    </div>

    @if ($tab === 'cancellations')
        <div class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Pemesanan</th>
                            <th class="px-4 py-3">Alasan</th>
                            <th class="px-4 py-3">Oleh</th>
                            <th class="px-4 py-3">Refund</th>
                            <th class="px-4 py-3">Tanggal</th>
                            <th class="px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($cancellations as $c)
                            <tr wire:key="cx-{{ $c->id }}" class="hover:bg-slate-50">
                                <td class="px-4 py-3">
                                    <p class="font-mono text-xs font-medium text-slate-900">{{ $c->booking?->code }}</p>
                                    <p class="text-xs text-slate-400">{{ $c->booking?->customer_name }}</p>
                                </td>
                                <td class="px-4 py-3 max-w-xs text-slate-600">{{ $c->reason }}</td>
                                <td class="px-4 py-3">
                                    <span class="badge {{ $c->requested_by_type === 'admin' ? 'bg-sky-100 text-sky-700' : 'bg-slate-100 text-slate-600' }}">
                                        {{ $c->requested_by_type === 'admin' ? 'Admin' : 'Tamu' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    @if ($c->eligible_for_refund)
                                        <span class="text-emerald-700">Berhak · {{ rupiah($c->refund_estimate) }}</span>
                                    @else
                                        <span class="text-slate-400">Tidak berhak</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-slate-500">{{ $c->created_at->translatedFormat('d M Y H:i') }}</td>
                                <td class="px-4 py-3 text-right">
                                    @if ($c->booking && $c->booking->payment_status->isPaid())
                                        <button wire:click="openRefundForm({{ $c->booking_id }})" class="btn-ghost text-xs">Catat Refund</button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-16 text-center text-slate-500">Belum ada pembatalan.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @else
        <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-5 py-3 text-sm text-amber-800">
            Refund dicatat secara <strong>manual</strong>. Sebuah refund hanya dianggap selesai setelah
            statusnya diubah menjadi “Berhasil” oleh admin — pencatatan saja bukan berarti dana sudah dikirim.
        </div>

        <div class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Pemesanan</th>
                            <th class="px-4 py-3">Jumlah</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Referensi</th>
                            <th class="px-4 py-3">Diproses oleh</th>
                            <th class="px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($refunds as $r)
                            <tr wire:key="rf-{{ $r->id }}" class="hover:bg-slate-50">
                                <td class="px-4 py-3 font-mono text-xs text-slate-900">{{ $r->booking?->code }}</td>
                                <td class="px-4 py-3 font-medium text-slate-900">{{ rupiah($r->amount) }}</td>
                                <td class="px-4 py-3">
                                    <span class="badge {{ $r->status->value === 'succeeded' ? 'bg-emerald-100 text-emerald-700' : ($r->status->value === 'failed' || $r->status->value === 'rejected' ? 'bg-rose-100 text-rose-700' : 'bg-amber-100 text-amber-700') }}">
                                        {{ $r->status->label() }}
                                    </span>
                                    @if ($r->is_manual)<span class="ml-1 badge bg-slate-100 text-slate-500">Manual</span>@endif
                                </td>
                                <td class="px-4 py-3 text-slate-500">{{ $r->provider_reference ?: '—' }}</td>
                                <td class="px-4 py-3 text-slate-500">{{ $r->processedBy?->name ?? '—' }}</td>
                                <td class="px-4 py-3 text-right">
                                    @if ($r->status->value !== 'succeeded')
                                        <button wire:click="openStatusForm({{ $r->id }})" class="btn-ghost text-xs">Ubah Status</button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-16 text-center text-slate-500">Belum ada refund tercatat.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- Record refund modal --}}
    @if ($refundBooking)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
            <div class="card w-full max-w-lg p-6">
                <h2 class="font-display text-xl font-semibold text-slate-900">Catat Refund</h2>
                <p class="mt-1 text-sm text-slate-500">{{ $refundBooking->code }} · Total dibayar {{ rupiah($refundBooking->total_amount) }}</p>
                <div class="mt-5 space-y-4">
                    <div>
                        <label class="label">Jumlah refund (Rp)</label>
                        <input type="number" min="1" wire:model="refundAmount" class="input">
                        @error('refundAmount') <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="label">Alasan</label>
                        <textarea wire:model="refundReason" rows="2" class="input"></textarea>
                        @error('refundReason') <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-2 border-t border-slate-100 pt-4">
                    <button wire:click="closeForms" class="btn-outline">Batal</button>
                    <button wire:click="submitRefund" class="btn-primary">Catat Refund</button>
                </div>
            </div>
        </div>
    @endif

    {{-- Update refund status modal --}}
    @if ($updatingRefundId)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
            <div class="card w-full max-w-lg p-6">
                <h2 class="font-display text-xl font-semibold text-slate-900">Perbarui Status Refund</h2>
                <div class="mt-5 space-y-4">
                    <div>
                        <label class="label">Status baru</label>
                        <select wire:model="newStatus" class="input">
                            <option value="">— Pilih —</option>
                            @foreach ($refundStatuses as $s)
                                <option value="{{ $s->value }}">{{ $s->label() }}</option>
                            @endforeach
                        </select>
                        @error('newStatus') <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="label">Referensi transfer (opsional)</label>
                        <input type="text" wire:model="providerReference" class="input" placeholder="Nomor referensi bank / DOKU">
                    </div>
                    <div>
                        <label class="label">Catatan</label>
                        <textarea wire:model="statusNotes" rows="2" class="input"></textarea>
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-2 border-t border-slate-100 pt-4">
                    <button wire:click="closeForms" class="btn-outline">Batal</button>
                    <button wire:click="submitStatus" class="btn-primary">Simpan</button>
                </div>
            </div>
        </div>
    @endif
</div>
