<div class="max-w-3xl space-y-6">

    @if (session('status'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm text-emerald-800">
            {{ session('status') }}
        </div>
    @endif

    {{-- Hotel identity --}}
    <div class="card p-5">
        <h3 class="font-semibold text-slate-900">Identitas Hotel</h3>
        <p class="mt-1 text-sm text-slate-500">Tampil pada invoice dan halaman publik.</p>

        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-slate-700">Nama Hotel</label>
                <input type="text" wire:model="hotel_name" class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">
                @error('hotel_name') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
            </div>
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-slate-700">Alamat</label>
                <input type="text" wire:model="hotel_address" class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">
                @error('hotel_address') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">Telepon</label>
                <input type="text" wire:model="hotel_phone" class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">Email</label>
                <input type="email" wire:model="hotel_email" class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">
                @error('hotel_email') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">Jam Check-in</label>
                <input type="time" wire:model="check_in_time" class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">
                @error('check_in_time') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">Jam Check-out</label>
                <input type="time" wire:model="check_out_time" class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">
                @error('check_out_time') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
            </div>
        </div>
    </div>

    {{-- Pricing --}}
    <div class="card p-5">
        <h3 class="font-semibold text-slate-900">Harga & Pajak</h3>
        <p class="mt-1 text-sm text-slate-500">Diterapkan pada setiap perhitungan harga. Perubahan tidak mengubah pemesanan yang sudah ada (harga disnapshot).</p>

        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <div>
                <label class="block text-sm font-medium text-slate-700">PPN (%)</label>
                <input type="number" wire:model="tax_percent" min="0" max="100" class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">
                @error('tax_percent') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">Service Charge (%)</label>
                <input type="number" wire:model="service_percent" min="0" max="100" class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">
                @error('service_percent') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
            </div>
        </div>
    </div>

    {{-- Booking policy --}}
    <div class="card p-5">
        <h3 class="font-semibold text-slate-900">Kebijakan Pemesanan</h3>

        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <div>
                <label class="block text-sm font-medium text-slate-700">Durasi Hold (menit)</label>
                <input type="number" wire:model="booking_hold_minutes" min="5" max="180" class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">
                @error('booking_hold_minutes') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                <p class="mt-1 text-xs text-slate-400">Tidak boleh lebih pendek dari jendela pembayaran DOKU.</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">Maksimal Malam</label>
                <input type="number" wire:model="booking_max_nights" min="1" max="365" class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">
                @error('booking_max_nights') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">Batas Pembatalan Gratis (jam)</label>
                <input type="number" wire:model="free_cancellation_hours" min="0" max="720" class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">
                @error('free_cancellation_hours') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">Biaya Pembatalan (%)</label>
                <input type="number" wire:model="cancellation_fee_percent" min="0" max="100" class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">
                @error('cancellation_fee_percent') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                <p class="mt-1 text-xs text-slate-400">Dipotong dari refund bila dibatalkan setelah jendela gratis.</p>
            </div>
        </div>
    </div>

    <div class="flex justify-end">
        <button wire:click="save" class="btn-primary">Simpan Pengaturan</button>
    </div>
</div>
