<x-layouts.admin title="Dashboard" heading="Dashboard" breadcrumb="Admin / Dashboard">
    <div class="mb-6">
        <p class="text-slate-600">
            Selamat datang kembali, <span class="font-medium text-slate-900">{{ auth()->user()->name }}</span>.
            <span class="text-slate-400">· {{ now()->translatedFormat('l, d F Y') }} WITA</span>
        </p>
    </div>

    {{-- Operational cards --}}
    <div class="grid gap-4 sm:grid-cols-2">
        @foreach ([
            ['Kedatangan Hari Ini', $metrics['arrivals_today'], 'admin.frontdesk.index', 'brand'],
            ['Reservasi Baru Hari Ini', $metrics['bookings_today'], 'admin.bookings.index', 'slate'],
        ] as [$label, $value, $route, $tone])
            <a href="{{ Route::has($route) ? route($route) : '#' }}" class="card p-5 transition hover:shadow-md">
                <p class="text-sm font-medium text-slate-500">{{ $label }}</p>
                <p class="mt-2 font-display text-3xl font-semibold text-slate-900">{{ number_format($value) }}</p>
            </a>
        @endforeach
    </div>

    {{-- Attention needed --}}
    <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
        <div class="card p-5">
            <p class="text-sm font-medium text-slate-500">Menunggu Pembayaran</p>
            <p class="mt-2 font-display text-3xl font-semibold text-amber-600">{{ number_format($metrics['pending_payments']) }}</p>
            <p class="mt-1 text-xs text-slate-400">Hold yang belum diselesaikan</p>
        </div>
        <a href="{{ route('admin.frontdesk.index') }}" class="card p-5 transition hover:shadow-md">
            <p class="text-sm font-medium text-slate-500">Tagihan Belum Lunas</p>
            <p class="mt-2 font-display text-3xl font-semibold {{ $metrics['awaiting_cash'] > 0 ? 'text-amber-600' : 'text-slate-900' }}">
                {{ number_format($metrics['awaiting_cash']) }}
            </p>
            <p class="mt-1 text-xs text-slate-400">Bayar di tempat, tagih di front desk</p>
        </a>
        <a href="{{ route('admin.payments.review') }}" class="card p-5 transition hover:shadow-md">
            <p class="text-sm font-medium text-slate-500">Peninjauan Pembayaran</p>
            <p class="mt-2 font-display text-3xl font-semibold {{ $metrics['payment_reviews'] > 0 ? 'text-orange-600' : 'text-slate-900' }}">
                {{ number_format($metrics['payment_reviews']) }}
            </p>
            @if ($metrics['payment_reviews'] > 0)
                <p class="mt-1 text-xs text-orange-600">Perlu tindakan admin</p>
            @endif
        </a>
        <a href="{{ route('admin.cancellations.index') }}" class="card p-5 transition hover:shadow-md">
            <p class="text-sm font-medium text-slate-500">Refund Tertunda</p>
            <p class="mt-2 font-display text-3xl font-semibold {{ $metrics['refunds_pending'] > 0 ? 'text-orange-600' : 'text-slate-900' }}">
                {{ number_format($metrics['refunds_pending']) }}
            </p>
        </a>
        <a href="{{ route('admin.rooms.index') }}" class="card p-5 transition hover:shadow-md">
            <p class="text-sm font-medium text-slate-500">Kamar Pemeliharaan</p>
            <p class="mt-2 font-display text-3xl font-semibold text-slate-900">{{ number_format($metrics['maintenance_rooms']) }}</p>
        </a>
    </div>

    {{-- Revenue + occupancy --}}
    <div class="mt-6 grid gap-4 lg:grid-cols-3">
        <div class="card p-6 lg:col-span-2">
            <div class="flex items-center justify-between">
                <h2 class="font-display text-lg font-semibold text-slate-900">Pendapatan Bulan Ini</h2>
                <a href="{{ route('admin.reports.index') }}" class="text-sm font-medium text-brand-700 hover:underline">Lihat laporan →</a>
            </div>
            <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ([
                    ['Kotor', $revenue['gross_revenue']],
                    ['Diskon', $revenue['discounts']],
                    ['Refund', $revenue['refunds']],
                    ['Bersih', $revenue['net_revenue']],
                ] as [$label, $value])
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-slate-400">{{ $label }}</p>
                        <p class="mt-1 font-display text-xl font-semibold text-slate-900">{{ rupiah($value) }}</p>
                    </div>
                @endforeach
            </div>
            <p class="mt-4 text-xs text-slate-400">
                Bersih = kotor − diskon − refund. Pembayaran gagal/kedaluwarsa tidak dihitung.
                Terkumpul: {{ rupiah($revenue['collected']) }} dari {{ $revenue['bookings'] }} pemesanan.
            </p>
        </div>

        <div class="card p-6">
            <h2 class="font-display text-lg font-semibold text-slate-900">Okupansi Hari Ini</h2>
            <p class="mt-4 font-display text-4xl font-semibold text-slate-900">{{ $occupancy['percent'] }}%</p>
            <p class="mt-1 text-sm text-slate-500">{{ $occupancy['occupied'] }} dari {{ $occupancy['total'] }} kamar terisi</p>
            <div class="mt-4 h-2.5 w-full overflow-hidden rounded-full bg-slate-100">
                <div class="h-full rounded-full bg-brand-600" style="width: {{ min(100, $occupancy['percent']) }}%"></div>
            </div>
        </div>
    </div>

    {{-- Recent reservations --}}
    <div class="card mt-6 overflow-hidden">
        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
            <h2 class="font-display text-lg font-semibold text-slate-900">Reservasi Terbaru</h2>
            <a href="{{ route('admin.bookings.index') }}" class="text-sm font-medium text-brand-700 hover:underline">Semua reservasi →</a>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-5 py-2.5">Kode</th>
                        <th class="px-5 py-2.5">Tamu</th>
                        <th class="px-5 py-2.5">Menginap</th>
                        <th class="px-5 py-2.5">Status</th>
                        <th class="px-5 py-2.5 text-right">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($recentBookings as $booking)
                        <tr class="hover:bg-slate-50">
                            <td class="px-5 py-2.5 font-mono text-xs text-slate-900">{{ $booking->code }}</td>
                            <td class="px-5 py-2.5 text-slate-700">{{ $booking->customer_name }}</td>
                            <td class="px-5 py-2.5 text-slate-600">
                                {{ $booking->check_in_date->translatedFormat('d M') }} – {{ $booking->check_out_date->translatedFormat('d M Y') }}
                            </td>
                            <td class="px-5 py-2.5"><span class="badge {{ $booking->status->badgeClasses() }}">{{ $booking->status->label() }}</span></td>
                            <td class="px-5 py-2.5 text-right text-slate-900">{{ rupiah($booking->total_amount) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-12 text-center text-slate-500">
                                <p class="font-medium">Belum ada reservasi</p>
                                <p class="mt-1 text-sm text-slate-400">Reservasi baru akan muncul di sini.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.admin>
