<div class="space-y-6">

    @if (session('status'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm text-emerald-800">
            {{ session('status') }}
        </div>
    @endif

    <div class="card p-5">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h2 class="font-display text-lg font-semibold text-slate-900">Mode Pembayaran DOKU</h2>
                <p class="mt-1 max-w-2xl text-sm text-slate-500">
                    Menentukan ke lingkungan DOKU mana seluruh pembayaran dikirim. Kredensial kedua mode
                    tersimpan di berkas environment server dan tidak pernah masuk ke basis data —
                    yang berpindah hanyalah pilihan modenya.
                </p>
            </div>
            <span @class([
                'rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-wide',
                'bg-rose-100 text-rose-700' => $active === 'production',
                'bg-amber-100 text-amber-700' => $active !== 'production',
            ])>
                Aktif: {{ $active }}
            </span>
        </div>

        @if ($active === 'production')
            <div class="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                <strong>Mode produksi aktif.</strong> Setiap pembayaran tamu adalah transaksi nyata dengan
                uang sungguhan, dan refund harus diproses manual lewat dashboard DOKU.
            </div>
        @else
            <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                <strong>Mode sandbox aktif.</strong> Pembayaran hanya simulasi — tidak ada uang yang berpindah.
                Jangan gunakan mode ini untuk tamu sungguhan: pemesanan akan terkonfirmasi tanpa pembayaran nyata.
            </div>
        @endif
    </div>

    <div class="grid gap-4 md:grid-cols-2">
        @foreach ($modes as $mode)
            <div wire:key="mode-{{ $mode['name'] }}" @class([
                'card p-5',
                'ring-2 ring-brand-500' => $mode['is_active'],
            ])>
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="font-semibold text-slate-900">{{ ucfirst($mode['name']) }}</h3>
                        <p class="mt-0.5 text-xs text-slate-500">{{ $mode['label'] }}</p>
                    </div>
                    @if ($mode['is_active'])
                        <span class="rounded-full bg-brand-100 px-2.5 py-0.5 text-xs font-semibold text-brand-700">Aktif</span>
                    @endif
                </div>

                <dl class="mt-4 space-y-2 text-sm">
                    <div class="flex justify-between gap-3">
                        <dt class="text-slate-500">Base URL</dt>
                        <dd class="text-right font-mono text-xs text-slate-700">{{ $mode['base_url'] }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-slate-500">Client ID</dt>
                        <dd class="text-right font-mono text-xs text-slate-700">{{ $mode['client_id'] }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-slate-500">Secret Key</dt>
                        <dd class="text-right font-mono text-xs text-slate-700">{{ $mode['secret_key'] }}</dd>
                    </div>
                </dl>

                <div class="mt-4">
                    @if (! $mode['configured'])
                        <p class="rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-500">
                            Kredensial belum lengkap. Isi
                            <code class="font-mono">DOKU_{{ strtoupper($mode['name']) }}_CLIENT_ID</code> dan
                            <code class="font-mono">DOKU_{{ strtoupper($mode['name']) }}_SECRET_KEY</code>
                            pada berkas <code class="font-mono">.env</code> server, ambil dari
                            <a href="{{ $mode['dashboard_url'] }}" target="_blank" rel="noopener"
                               class="font-medium text-brand-700 underline">dashboard DOKU</a>.
                        </p>
                    @elseif ($mode['is_active'])
                        <p class="text-xs text-slate-400">Mode ini sedang digunakan.</p>
                    @else
                        <button wire:click="startSwitch('{{ $mode['name'] }}')" class="btn-primary w-full">
                            Alihkan ke {{ ucfirst($mode['name']) }}
                        </button>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    @error('target')
        <div class="rounded-xl border border-rose-200 bg-rose-50 px-5 py-4 text-sm text-rose-700">{{ $message }}</div>
    @enderror

    @if ($confirming)
        <div class="card border-2 border-brand-300 p-5">
            <h3 class="font-semibold text-slate-900">Konfirmasi perpindahan ke {{ ucfirst($target) }}</h3>

            @if ($target === 'production')
                <p class="mt-2 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                    Setelah ini setiap pembayaran menjadi transaksi uang sungguhan. Pastikan
                    <code class="font-mono">APP_DEBUG=false</code>, domain https sudah benar, dan Notification URL
                    produksi sudah terdaftar di dashboard DOKU.
                </p>
            @else
                <p class="mt-2 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                    Setelah ini pembayaran tidak lagi memungut uang sungguhan. Pemesanan tamu nyata akan
                    terkonfirmasi tanpa pembayaran yang benar-benar diterima.
                </p>
            @endif

            <label class="mt-4 block text-sm font-medium text-slate-700" for="doku-reason">
                Alasan perubahan <span class="text-rose-600">*</span>
            </label>
            <textarea id="doku-reason" wire:model="reason" rows="3"
                      class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500"
                      placeholder="Mis. mulai uji coba integrasi sebelum go-live"></textarea>
            @error('reason')
                <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
            @enderror
            <p class="mt-1 text-xs text-slate-400">Alasan ini tercatat permanen di audit log beserta nama Anda.</p>

            <div class="mt-4 flex gap-2">
                <button wire:click="confirmSwitch" class="btn-primary">Ya, alihkan sekarang</button>
                <button wire:click="cancelSwitch" class="btn-secondary">Batal</button>
            </div>
        </div>
    @endif

    <div class="card p-5">
        <h3 class="font-semibold text-slate-900">Metode Pembayaran</h3>
        <p class="mt-1 text-sm text-slate-500">
            Metode yang ditampilkan pada halaman DOKU. Kosongkan semua untuk menampilkan setiap metode
            yang aktif di akun. Satu metode terpilih akan mengarahkan tamu langsung ke metode itu.
            Metode harus sudah diaktifkan di dashboard DOKU (mis. QRIS: Settings → Account → Service).
        </p>

        @if ($methodStatus)
            <p class="mt-3 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-800">{{ $methodStatus }}</p>
        @endif

        <div class="mt-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($availableMethods as $method)
                <label wire:key="pm-{{ $method }}"
                       class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm hover:bg-slate-50">
                    <input type="checkbox" value="{{ $method }}" wire:model="methods"
                           class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                    <span class="font-mono text-xs text-slate-700">{{ $method }}</span>
                </label>
            @endforeach
        </div>

        <div class="mt-4">
            <button wire:click="saveMethods" class="btn-primary">Simpan Metode</button>
        </div>
    </div>

    <div class="card p-5">
        <h3 class="font-semibold text-slate-900">Pemeriksaan konfigurasi mode aktif</h3>

        @forelse ($check['problems'] as $problem)
            <p class="mt-2 rounded-lg border border-rose-200 bg-rose-50 px-4 py-2 text-sm text-rose-800">{{ $problem }}</p>
        @empty
            <p class="mt-2 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-800">
                Tidak ada masalah yang menghalangi pembayaran.
            </p>
        @endforelse

        @foreach ($check['warnings'] as $warning)
            <p class="mt-2 rounded-lg border border-amber-200 bg-amber-50 px-4 py-2 text-sm text-amber-800">{{ $warning }}</p>
        @endforeach

        <div class="mt-4 overflow-x-auto">
            <table class="min-w-full text-sm">
                <tbody class="divide-y divide-slate-100">
                    @foreach ($check['summary'] as $key => $value)
                        <tr>
                            <th scope="row" class="whitespace-nowrap py-1.5 pr-4 text-left font-normal text-slate-500">{{ $key }}</th>
                            <td class="py-1.5 font-mono text-xs text-slate-700">{{ $value }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="card p-5">
        <h3 class="font-semibold text-slate-900">Riwayat perpindahan mode</h3>
        @if ($history->isEmpty())
            <p class="mt-2 text-sm text-slate-500">Belum pernah ada perpindahan mode.</p>
        @else
            <table class="mt-3 min-w-full divide-y divide-slate-200 text-sm">
                <thead class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="py-2">Waktu</th>
                        <th class="py-2">Oleh</th>
                        <th class="py-2">Perubahan</th>
                        <th class="py-2">Alasan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($history as $entry)
                        <tr wire:key="hist-{{ $entry->id }}">
                            <td class="py-2 whitespace-nowrap text-slate-600">{{ $entry->created_at?->format('d M Y H:i') }}</td>
                            <td class="py-2 text-slate-700">{{ $entry->user?->name ?? '—' }}</td>
                            <td class="py-2 text-slate-700">
                                {{ data_get($entry->old_values, 'environment', '?') }}
                                <span class="text-slate-400">→</span>
                                <strong>{{ data_get($entry->new_values, 'environment', '?') }}</strong>
                            </td>
                            <td class="py-2 text-slate-500">{{ data_get($entry->new_values, 'reason', '—') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
