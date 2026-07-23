<x-mail::message>
# Pemesanan Anda Terkonfirmasi

Halo {{ $booking->customer_name }},

Terima kasih! Pembayaran Anda telah kami terima dan diverifikasi. Pemesanan Anda di
**New Poppies Senggigi** sudah terkonfirmasi.

**Kode Pemesanan:** {{ $booking->code }}

| Detail | |
|:--|:--|
| Check-in | {{ $booking->check_in_date->translatedFormat('l, d M Y') }} |
| Check-out | {{ $booking->check_out_date->translatedFormat('l, d M Y') }} |
| Lama menginap | {{ $booking->nights }} malam |
| Jumlah kamar | {{ $booking->rooms }} |
| Tamu | {{ $booking->adults }} dewasa{{ $booking->children ? ', '.$booking->children.' anak' : '' }} |
| Total dibayar | {{ rupiah($booking->total_amount) }} |

<x-mail::button :url="route('booking.show', $booking->code)">
Lihat Detail Pemesanan
</x-mail::button>

Simpan kode pemesanan ini. Anda memerlukannya (bersama email ini) untuk melihat
status pemesanan dan saat check-in.

Check-in mulai pukul 14.00 WITA, check-out paling lambat pukul 12.00 WITA.

Sampai jumpa di Senggigi!<br>
Salam hangat,<br>
{{ config('app.name') }}
</x-mail::message>
