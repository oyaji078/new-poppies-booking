<div>
    {{-- Filters --}}
    <div class="card mb-6 p-4">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
            <div class="lg:col-span-2">
                <label class="label">Cari</label>
                <input type="search" wire:model.live.debounce.300ms="search" class="input" placeholder="Kode, nama, atau email…">
            </div>
            <div>
                <label class="label">Status</label>
                <select wire:model.live="status" class="input">
                    <option value="">Semua</option>
                    @foreach ($statuses as $s)
                        <option value="{{ $s->value }}">{{ $s->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="label">Pembayaran</label>
                <select wire:model.live="paymentStatus" class="input">
                    <option value="">Semua</option>
                    @foreach ($paymentStatuses as $s)
                        <option value="{{ $s->value }}">{{ $s->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="label">Check-in dari</label>
                <input type="date" wire:model.live="from" class="input">
            </div>
            <div>
                <label class="label">Sampai</label>
                <input type="date" wire:model.live="to" class="input">
            </div>
        </div>
        <div class="mt-3 flex justify-end">
            <button wire:click="resetFilters" class="btn-ghost text-sm">Reset filter</button>
        </div>
    </div>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Kode</th>
                        <th class="px-4 py-3">Tamu</th>
                        <th class="px-4 py-3">Menginap</th>
                        <th class="px-4 py-3">Total</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Pembayaran</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($bookings as $booking)
                        <tr wire:key="bk-{{ $booking->id }}" class="hover:bg-slate-50">
                            <td class="px-4 py-3 font-mono text-xs font-medium text-slate-900">{{ $booking->code }}</td>
                            <td class="px-4 py-3">
                                <p class="font-medium text-slate-900">{{ $booking->customer_name }}</p>
                                <p class="text-xs text-slate-400">{{ $booking->customer_email }}</p>
                            </td>
                            <td class="px-4 py-3 text-slate-700">
                                {{ $booking->check_in_date->translatedFormat('d M') }} – {{ $booking->check_out_date->translatedFormat('d M Y') }}
                                <span class="block text-xs text-slate-400">{{ $booking->nights }} malam · {{ $booking->rooms }} kamar</span>
                            </td>
                            <td class="px-4 py-3 text-slate-900">{{ rupiah($booking->total_amount) }}</td>
                            <td class="px-4 py-3"><span class="badge {{ $booking->status->badgeClasses() }}">{{ $booking->status->label() }}</span></td>
                            <td class="px-4 py-3"><span class="badge {{ $booking->payment_status->badgeClasses() }}">{{ $booking->payment_status->label() }}</span></td>
                            <td class="px-4 py-3 text-right">
                                <button wire:click="showDetail({{ $booking->id }})" class="btn-ghost text-xs">Detail</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-16 text-center text-slate-500">
                                <p class="font-medium">Tidak ada reservasi</p>
                                <p class="mt-1 text-sm text-slate-400">Coba ubah atau reset filter pencarian.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($bookings->hasPages())
            <div class="border-t border-slate-100 px-4 py-3">{{ $bookings->links() }}</div>
        @endif
    </div>

    {{-- Detail drawer --}}
    @if ($detail)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/50 p-4">
            <div class="mx-auto my-8 max-w-3xl">
                <div class="card p-6">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="font-mono text-lg font-semibold text-slate-900">{{ $detail->code }}</p>
                            <p class="text-sm text-slate-500">Dibuat {{ $detail->created_at->translatedFormat('d M Y, H:i') }}</p>
                        </div>
                        <button wire:click="closeDetail" class="text-slate-400 hover:text-slate-600">&times;</button>
                    </div>

                    <div class="mt-4 flex gap-2">
                        <span class="badge {{ $detail->status->badgeClasses() }}">{{ $detail->status->label() }}</span>
                        <span class="badge {{ $detail->payment_status->badgeClasses() }}">{{ $detail->payment_status->label() }}</span>
                        @if ($detail->late_payment_recovery)
                            <span class="badge bg-orange-100 text-orange-700">Pemulihan pembayaran terlambat</span>
                        @endif
                    </div>

                    <dl class="mt-5 grid gap-3 text-sm sm:grid-cols-2">
                        <div><dt class="text-slate-500">Tamu</dt><dd class="font-medium text-slate-900">{{ $detail->customer_name }}</dd></div>
                        <div><dt class="text-slate-500">Email</dt><dd class="font-medium text-slate-900">{{ $detail->customer_email }}</dd></div>
                        <div><dt class="text-slate-500">Telepon</dt><dd class="font-medium text-slate-900">{{ $detail->customer_phone }}</dd></div>
                        <div><dt class="text-slate-500">Menginap</dt><dd class="font-medium text-slate-900">{{ $detail->check_in_date->translatedFormat('d M Y') }} – {{ $detail->check_out_date->translatedFormat('d M Y') }}</dd></div>
                        <div><dt class="text-slate-500">Total</dt><dd class="font-medium text-slate-900">{{ rupiah($detail->total_amount) }}</dd></div>
                        <div><dt class="text-slate-500">Kamar</dt><dd class="font-medium text-slate-900">{{ $detail->items->pluck('room_type_name')->join(', ') }}</dd></div>
                    </dl>

                    @if ($detail->special_request)
                        <div class="mt-4 rounded-lg bg-slate-50 px-4 py-3 text-sm text-slate-600">
                            <span class="font-medium text-slate-700">Permintaan khusus:</span> {{ $detail->special_request }}
                        </div>
                    @endif

                    <h3 class="mt-6 font-display font-semibold text-slate-900">Percobaan Pembayaran</h3>
                    @if ($detail->paymentAttempts->isEmpty())
                        <p class="mt-2 text-sm text-slate-400">Belum ada percobaan pembayaran.</p>
                    @else
                        <div class="mt-2 overflow-hidden rounded-lg border border-slate-200">
                            <table class="min-w-full divide-y divide-slate-200 text-xs">
                                <thead class="bg-slate-50 text-left uppercase tracking-wide text-slate-500">
                                    <tr><th class="px-3 py-2">Invoice</th><th class="px-3 py-2">Jumlah</th><th class="px-3 py-2">Status</th><th class="px-3 py-2">Dibayar</th></tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @foreach ($detail->paymentAttempts as $attempt)
                                        <tr>
                                            <td class="px-3 py-2 font-mono text-slate-700">{{ $attempt->invoice_number }}</td>
                                            <td class="px-3 py-2 text-slate-700">{{ rupiah($attempt->amount) }}</td>
                                            <td class="px-3 py-2 text-slate-700">{{ $attempt->status->label() }}</td>
                                            <td class="px-3 py-2 text-slate-500">{{ $attempt->paid_at?->translatedFormat('d M Y H:i') ?? '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif

                    <div class="mt-6 flex justify-end">
                        <button wire:click="closeDetail" class="btn-outline">Tutup</button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
