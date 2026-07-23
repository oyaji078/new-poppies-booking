<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Invoice {{ $booking->code }} — {{ $hotel['name'] }}</title>
    <style>
        :root {
            --brand: #0f766e;      /* teal-700 */
            --brand-dark: #134e4a; /* teal-900 */
            --ink: #0f172a;
            --muted: #64748b;
            --line: #e2e8f0;
            --soft: #f8fafc;
        }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; }
        body {
            font-family: -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            color: var(--ink);
            background: #eef2f5;
            font-size: 13px;
            line-height: 1.5;
        }
        .sheet {
            width: 210mm;
            min-height: 297mm;
            margin: 16px auto;
            background: #fff;
            padding: 18mm 16mm;
            box-shadow: 0 10px 30px rgba(15, 23, 42, .12);
        }
        .toolbar {
            width: 210mm;
            margin: 16px auto 0;
            display: flex;
            gap: 8px;
            justify-content: flex-end;
        }
        .btn {
            border: 0; cursor: pointer;
            font: inherit; font-weight: 600;
            padding: 9px 18px; border-radius: 8px;
            text-decoration: none; display: inline-block;
        }
        .btn-print { background: var(--brand); color: #fff; }
        .btn-back { background: #fff; color: var(--ink); border: 1px solid var(--line); }

        header { display: flex; justify-content: space-between; align-items: flex-start; gap: 24px; }
        .brand { display: flex; gap: 12px; align-items: center; }
        .mark {
            width: 46px; height: 46px; border-radius: 11px;
            background: var(--brand); color: #fff;
            display: grid; place-items: center;
            font-weight: 700; font-size: 18px; letter-spacing: .5px;
        }
        .brand-name { font-size: 18px; font-weight: 700; color: var(--brand-dark); }
        .brand-sub { font-size: 10px; letter-spacing: 2px; text-transform: uppercase; color: var(--muted); }
        .hotel-meta { font-size: 11px; color: var(--muted); margin-top: 8px; max-width: 240px; }

        .doc-title { text-align: right; }
        .doc-title h1 { margin: 0; font-size: 26px; letter-spacing: 3px; color: var(--brand-dark); text-transform: uppercase; }
        .doc-title .no { margin-top: 4px; font-family: ui-monospace, "SFMono-Regular", Menlo, monospace; font-weight: 600; }
        .doc-title .date { font-size: 11px; color: var(--muted); }

        .rule { height: 3px; background: var(--brand); margin: 18px 0; border-radius: 2px; }

        .cols { display: flex; gap: 28px; margin-bottom: 18px; }
        .col { flex: 1; }
        .col h3 { margin: 0 0 6px; font-size: 10px; letter-spacing: 1.5px; text-transform: uppercase; color: var(--brand); }
        .col .name { font-weight: 600; }
        .col .line { color: var(--muted); font-size: 12px; }

        .status-row { display: flex; gap: 8px; margin: 4px 0 18px; }
        .chip {
            font-size: 11px; font-weight: 600; padding: 3px 10px; border-radius: 999px;
            border: 1px solid var(--line);
        }
        .chip-paid { background: #ecfdf5; color: #047857; border-color: #a7f3d0; }
        .chip-pending { background: #fffbeb; color: #b45309; border-color: #fde68a; }
        .chip-other { background: var(--soft); color: var(--muted); }

        table { width: 100%; border-collapse: collapse; }
        thead th {
            text-align: left; font-size: 10px; letter-spacing: 1px; text-transform: uppercase;
            color: var(--muted); padding: 8px 10px; border-bottom: 2px solid var(--brand);
        }
        thead th.num, tbody td.num { text-align: right; }
        tbody td { padding: 10px; border-bottom: 1px solid var(--line); vertical-align: top; }
        tbody td .desc { font-size: 11px; color: var(--muted); }

        .totals { margin-top: 14px; display: flex; justify-content: flex-end; }
        .totals table { width: 62%; }
        .totals td { padding: 5px 10px; border: 0; }
        .totals td.num { text-align: right; }
        .totals .label { color: var(--muted); }
        .totals .grand td { border-top: 2px solid var(--brand); padding-top: 10px; font-size: 16px; font-weight: 700; color: var(--brand-dark); }
        .totals .discount td.num { color: #b91c1c; }

        .notes { margin-top: 22px; display: flex; gap: 28px; }
        .notes .box { flex: 1; background: var(--soft); border: 1px solid var(--line); border-radius: 10px; padding: 12px 14px; }
        .notes h4 { margin: 0 0 4px; font-size: 10px; letter-spacing: 1px; text-transform: uppercase; color: var(--brand); }
        .notes p { margin: 0; font-size: 11px; color: var(--muted); }

        footer { margin-top: 26px; padding-top: 14px; border-top: 1px solid var(--line); text-align: center; font-size: 10px; color: var(--muted); }
        .paid-stamp {
            display: inline-block; margin-top: 10px; transform: rotate(-6deg);
            border: 3px solid #059669; color: #059669; border-radius: 8px;
            padding: 4px 14px; font-weight: 800; letter-spacing: 2px; font-size: 15px;
        }

        @media print {
            @page { size: A4; margin: 0; }
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet { margin: 0; box-shadow: none; width: auto; min-height: auto; padding: 14mm; }
        }
    </style>
</head>
<body>
    @php
        $paid = $booking->payment_status->value === 'paid';
        $pending = in_array($booking->payment_status->value, ['pending', 'unpaid'], true);
    @endphp

    <div class="toolbar">
        <a href="{{ route('booking.show', $booking->code) }}" class="btn btn-back">Kembali</a>
        <button class="btn btn-print" onclick="window.print()">Cetak / Simpan PDF</button>
    </div>

    <div class="sheet">
        <header>
            <div>
                <div class="brand">
                    <div class="mark">NP</div>
                    <div>
                        <div class="brand-name">{{ $hotel['name'] }}</div>
                        <div class="brand-sub">Senggigi · Lombok</div>
                    </div>
                </div>
                <div class="hotel-meta">
                    @if ($hotel['address']){{ $hotel['address'] }}<br>@endif
                    @if ($hotel['phone']){{ $hotel['phone'] }} · @endif{{ $hotel['email'] }}
                </div>
            </div>
            <div class="doc-title">
                <h1>Invoice</h1>
                <div class="no">{{ $booking->code }}</div>
                <div class="date">Tanggal: {{ $booking->created_at->translatedFormat('d F Y') }}</div>
            </div>
        </header>

        <div class="rule"></div>

        <div class="cols">
            <div class="col">
                <h3>Ditagihkan kepada</h3>
                <div class="name">{{ $booking->customer_name }}</div>
                <div class="line">{{ $booking->customer_email }}</div>
                @if ($booking->customer_phone)<div class="line">{{ $booking->customer_phone }}</div>@endif
            </div>
            <div class="col">
                <h3>Menginap</h3>
                <div class="line">Check-in: <strong>{{ $booking->check_in_date->translatedFormat('d M Y') }}</strong></div>
                <div class="line">Check-out: <strong>{{ $booking->check_out_date->translatedFormat('d M Y') }}</strong></div>
                <div class="line">{{ $booking->nights }} malam · {{ $booking->rooms }} kamar · {{ $booking->adults + $booking->children }} tamu</div>
            </div>
        </div>

        <div class="status-row">
            <span class="chip {{ $paid ? 'chip-paid' : ($pending ? 'chip-pending' : 'chip-other') }}">
                Pembayaran: {{ $booking->payment_status->label() }}
            </span>
            <span class="chip chip-other">Status: {{ $booking->status->label() }}</span>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Deskripsi</th>
                    <th class="num">Kamar</th>
                    <th class="num">Malam</th>
                    <th class="num">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($booking->items as $item)
                    <tr>
                        <td>
                            <div class="name">{{ $item->room_type_name }}</div>
                            <div class="desc">{{ $booking->check_in_date->translatedFormat('d M') }} – {{ $booking->check_out_date->translatedFormat('d M Y') }}</div>
                        </td>
                        <td class="num">{{ $item->rooms }}</td>
                        <td class="num">{{ $booking->nights }}</td>
                        <td class="num">{{ rupiah($item->subtotal_amount) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="totals">
            <table>
                <tr>
                    <td class="label">Subtotal</td>
                    <td class="num">{{ rupiah($booking->subtotal_amount) }}</td>
                </tr>
                @if ($booking->discount_amount > 0)
                    <tr class="discount">
                        <td class="label">Diskon @if ($booking->promotion_code)({{ $booking->promotion_code }})@endif</td>
                        <td class="num">− {{ rupiah($booking->discount_amount) }}</td>
                    </tr>
                @endif
                <tr>
                    <td class="label">Pajak (PPN)</td>
                    <td class="num">{{ rupiah($booking->tax_amount) }}</td>
                </tr>
                <tr>
                    <td class="label">Biaya Layanan</td>
                    <td class="num">{{ rupiah($booking->service_amount) }}</td>
                </tr>
                <tr class="grand">
                    <td>Total</td>
                    <td class="num">{{ rupiah($booking->total_amount) }}</td>
                </tr>
            </table>
        </div>

        <div class="notes">
            <div class="box">
                <h4>Kebijakan</h4>
                <p>
                    @php $freeHours = (int) (data_get($booking->cancellation_policy, 'free_cancellation_hours') ?? 24); @endphp
                    Pembatalan gratis hingga {{ $freeHours }} jam sebelum check-in. Setelah itu dapat dikenakan biaya pembatalan sesuai ketentuan.
                </p>
            </div>
            <div class="box" style="text-align:center;">
                @if ($paid)
                    <span class="paid-stamp">LUNAS</span>
                @else
                    <h4>Menunggu Pembayaran</h4>
                    <p>Invoice ini belum lunas. Selesaikan pembayaran untuk mengonfirmasi pemesanan.</p>
                @endif
            </div>
        </div>

        <footer>
            Terima kasih telah memilih {{ $hotel['name'] }}. Invoice ini dibuat secara otomatis dan sah tanpa tanda tangan.
        </footer>
    </div>
</body>
</html>
