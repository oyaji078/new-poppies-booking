<x-layouts.admin title="Laporan" heading="Laporan" breadcrumb="Admin / Laporan">
    @php
        $types = [
            'revenue' => 'Pendapatan',
            'reservations' => 'Reservasi',
            'payments' => 'Pembayaran',
            'guests' => 'Tamu',
            'room_usage' => 'Penggunaan Kamar',
            'occupancy' => 'Okupansi',
            'cancellations' => 'Pembatalan',
            'refunds' => 'Refund',
        ];
    @endphp

    {{-- Filters --}}
    <form method="GET" action="{{ route('admin.reports.index') }}" class="card mb-6 p-4 print:hidden">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
            <div>
                <label class="label">Jenis Laporan</label>
                <select name="type" class="input">
                    @foreach ($types as $key => $label)
                        <option value="{{ $key }}" @selected($type === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="label">Dari</label>
                <input type="date" name="from" value="{{ $filters['from'] }}" class="input">
            </div>
            <div>
                <label class="label">Sampai</label>
                <input type="date" name="to" value="{{ $filters['to'] }}" class="input">
            </div>
            <div>
                <label class="label">Tipe Kamar</label>
                <select name="room_type_id" class="input">
                    <option value="">Semua</option>
                    @foreach ($roomTypes as $rt)
                        <option value="{{ $rt->id }}" @selected((string) $filters['room_type_id'] === (string) $rt->id)>{{ $rt->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="btn-primary flex-1">Terapkan</button>
            </div>
        </div>
        @error('to') <p class="field-error">{{ $message }}</p> @enderror
    </form>

    {{-- Actions --}}
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3 print:hidden">
        <p class="text-sm text-slate-500">
            Periode {{ \Carbon\Carbon::parse($filters['from'])->translatedFormat('d M Y') }}
            – {{ \Carbon\Carbon::parse($filters['to'])->translatedFormat('d M Y') }}
        </p>
        <div class="flex gap-2">
            <button onclick="window.print()" class="btn-outline">Cetak</button>
            <a href="{{ route('admin.reports.export', array_merge(['type' => $type], $filters)) }}" class="btn-primary">Ekspor CSV</a>
        </div>
    </div>

    {{-- Revenue summary is always shown --}}
    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ([
            ['Pendapatan Kotor', $summary['gross_revenue']],
            ['Diskon', $summary['discounts']],
            ['Refund (berhasil)', $summary['refunds']],
            ['Pendapatan Bersih', $summary['net_revenue']],
        ] as [$label, $value])
            <div class="card p-5">
                <p class="text-sm font-medium text-slate-500">{{ $label }}</p>
                <p class="mt-2 font-display text-2xl font-semibold text-slate-900">{{ rupiah($value) }}</p>
            </div>
        @endforeach
    </div>
    <p class="mb-6 text-xs text-slate-400">
        Pendapatan bersih = kotor − diskon − refund. Pembayaran gagal dan kedaluwarsa tidak dihitung sebagai pendapatan.
        Dana terkumpul: {{ rupiah($summary['collected']) }} dari {{ $summary['bookings'] }} pemesanan.
    </p>

    {{-- Report table --}}
    <div class="card overflow-hidden">
        <div class="border-b border-slate-100 px-5 py-3">
            <h2 class="font-display font-semibold text-slate-900">{{ $types[$type] ?? 'Laporan' }}</h2>
        </div>
        <div class="overflow-x-auto">
            @if ($type === 'revenue')
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($summary as $key => $value)
                            <tr>
                                <td class="px-5 py-2.5 text-slate-600">{{ str_replace('_', ' ', ucfirst($key)) }}</td>
                                <td class="px-5 py-2.5 text-right font-medium text-slate-900">
                                    {{ in_array($key, ['bookings'], true) ? $value : rupiah($value) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

            @elseif ($data->isEmpty())
                <p class="px-5 py-16 text-center text-slate-500">Tidak ada data untuk periode dan filter ini.</p>

            @elseif ($type === 'reservations')
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr><th class="px-5 py-2">Kode</th><th class="px-5 py-2">Tamu</th><th class="px-5 py-2">Menginap</th><th class="px-5 py-2">Status</th><th class="px-5 py-2 text-right">Total</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($data as $b)
                            <tr>
                                <td class="px-5 py-2 font-mono text-xs">{{ $b->code }}</td>
                                <td class="px-5 py-2">{{ $b->customer_name }}</td>
                                <td class="px-5 py-2">{{ $b->check_in_date->translatedFormat('d M') }} – {{ $b->check_out_date->translatedFormat('d M Y') }}</td>
                                <td class="px-5 py-2"><span class="badge {{ $b->status->badgeClasses() }}">{{ $b->status->label() }}</span></td>
                                <td class="px-5 py-2 text-right">{{ rupiah($b->total_amount) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

            @elseif ($type === 'payments')
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr><th class="px-5 py-2">Invoice</th><th class="px-5 py-2">Pemesanan</th><th class="px-5 py-2">Status</th><th class="px-5 py-2 text-right">Jumlah</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($data as $p)
                            <tr>
                                <td class="px-5 py-2 font-mono text-xs">{{ $p->invoice_number }}</td>
                                <td class="px-5 py-2 font-mono text-xs">{{ $p->booking?->code }}</td>
                                <td class="px-5 py-2">{{ $p->status->label() }}</td>
                                <td class="px-5 py-2 text-right">{{ rupiah($p->amount) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

            @elseif ($type === 'guests')
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr><th class="px-5 py-2">Nama</th><th class="px-5 py-2">Email</th><th class="px-5 py-2 text-center">Pemesanan</th><th class="px-5 py-2 text-right">Nilai</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($data as $g)
                            <tr>
                                <td class="px-5 py-2">{{ $g->customer_name }}</td>
                                <td class="px-5 py-2 text-slate-500">{{ $g->customer_email }}</td>
                                <td class="px-5 py-2 text-center">{{ $g->bookings_count }}</td>
                                <td class="px-5 py-2 text-right">{{ rupiah($g->total_value) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

            @elseif ($type === 'room_usage')
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr><th class="px-5 py-2">Tipe Kamar</th><th class="px-5 py-2 text-center">Kamar Terjual</th><th class="px-5 py-2 text-center">Room-Nights</th><th class="px-5 py-2 text-right">Subtotal</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($data as $r)
                            <tr>
                                <td class="px-5 py-2">{{ $r->name }}</td>
                                <td class="px-5 py-2 text-center">{{ $r->rooms_sold }}</td>
                                <td class="px-5 py-2 text-center">{{ $r->room_nights }}</td>
                                <td class="px-5 py-2 text-right">{{ rupiah($r->subtotal) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

            @elseif ($type === 'occupancy')
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr><th class="px-5 py-2">Tanggal</th><th class="px-5 py-2 text-center">Total</th><th class="px-5 py-2 text-center">Terisi</th><th class="px-5 py-2 text-right">Okupansi</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($data as $o)
                            <tr>
                                <td class="px-5 py-2">{{ \Carbon\Carbon::parse($o['date'])->translatedFormat('D, d M Y') }}</td>
                                <td class="px-5 py-2 text-center">{{ $o['total'] }}</td>
                                <td class="px-5 py-2 text-center">{{ $o['confirmed'] }}</td>
                                <td class="px-5 py-2 text-right">{{ $o['occupancy_percent'] }}%</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

            @elseif ($type === 'cancellations')
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr><th class="px-5 py-2">Kode</th><th class="px-5 py-2">Tamu</th><th class="px-5 py-2">Dibatalkan</th><th class="px-5 py-2">Alasan</th><th class="px-5 py-2 text-right">Total</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($data as $b)
                            <tr>
                                <td class="px-5 py-2 font-mono text-xs">{{ $b->code }}</td>
                                <td class="px-5 py-2">{{ $b->customer_name }}</td>
                                <td class="px-5 py-2">{{ $b->cancelled_at?->translatedFormat('d M Y') }}</td>
                                <td class="px-5 py-2 max-w-xs text-slate-600">{{ $b->cancellation_reason }}</td>
                                <td class="px-5 py-2 text-right">{{ rupiah($b->total_amount) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

            @elseif ($type === 'refunds')
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr><th class="px-5 py-2">Pemesanan</th><th class="px-5 py-2">Status</th><th class="px-5 py-2">Referensi</th><th class="px-5 py-2 text-right">Jumlah</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($data as $r)
                            <tr>
                                <td class="px-5 py-2 font-mono text-xs">{{ $r->booking?->code }}</td>
                                <td class="px-5 py-2">{{ $r->status->label() }}</td>
                                <td class="px-5 py-2 text-slate-500">{{ $r->provider_reference ?: '—' }}</td>
                                <td class="px-5 py-2 text-right">{{ rupiah($r->amount) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
</x-layouts.admin>
