<div>
    <div class="mb-6">
        <input type="search" wire:model.live.debounce.300ms="search" class="input max-w-sm" placeholder="Cari nama, email, atau telepon…">
    </div>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Nama</th>
                        <th class="px-4 py-3">Kontak</th>
                        <th class="px-4 py-3">Negara</th>
                        <th class="px-4 py-3 text-center">Pemesanan</th>
                        <th class="px-4 py-3">Nilai (terkonfirmasi)</th>
                        <th class="px-4 py-3">Menginap terakhir</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($guests as $guest)
                        <tr wire:key="g-{{ $guest->customer_email }}" class="hover:bg-slate-50">
                            <td class="px-4 py-3 font-medium text-slate-900">{{ $guest->customer_name }}</td>
                            <td class="px-4 py-3 text-slate-600">
                                {{ $guest->customer_email }}
                                <span class="block text-xs text-slate-400">{{ $guest->customer_phone }}</span>
                            </td>
                            <td class="px-4 py-3 text-slate-600">{{ $guest->customer_country ?: '—' }}</td>
                            <td class="px-4 py-3 text-center text-slate-700">{{ $guest->bookings_count }}</td>
                            <td class="px-4 py-3 text-slate-900">{{ rupiah($guest->lifetime_value) }}</td>
                            <td class="px-4 py-3 text-slate-500">{{ \Carbon\Carbon::parse($guest->last_stay)->translatedFormat('d M Y') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-16 text-center text-slate-500">Belum ada data tamu.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($guests->hasPages())
            <div class="border-t border-slate-100 px-4 py-3">{{ $guests->links() }}</div>
        @endif
    </div>
</div>
