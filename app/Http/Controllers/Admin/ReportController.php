<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Reports\CsvExporter;
use App\Services\Reports\ReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request, ReportService $reports): View
    {
        $filters = $this->filters($request);
        $type = $request->query('type', 'revenue');

        $data = $this->dataFor($type, $filters, $reports);

        return view('admin.reports.index', [
            'type' => $type,
            'filters' => $filters,
            'summary' => $reports->revenueSummary($filters),
            'data' => $data,
            'roomTypes' => $reports->roomTypeOptions(),
        ]);
    }

    public function export(Request $request, ReportService $reports, CsvExporter $csv): StreamedResponse
    {
        $filters = $this->filters($request);
        $type = $request->query('type', 'revenue');
        $data = $this->dataFor($type, $filters, $reports);
        $stamp = now()->format('Ymd-His');

        return match ($type) {
            'reservations' => $csv->stream("laporan-reservasi-{$stamp}.csv",
                ['Kode', 'Nama', 'Email', 'Check-in', 'Check-out', 'Malam', 'Kamar', 'Status', 'Pembayaran', 'Total'],
                $data->map(fn ($b) => [
                    $b->code, $b->customer_name, $b->customer_email,
                    $b->check_in_date->toDateString(), $b->check_out_date->toDateString(),
                    $b->nights, $b->rooms, $b->status->label(), $b->payment_status->label(), $b->total_amount,
                ])),

            'payments' => $csv->stream("laporan-pembayaran-{$stamp}.csv",
                ['Invoice', 'Kode Pemesanan', 'Jumlah', 'Status', 'Dibayar pada'],
                $data->map(fn ($p) => [
                    $p->invoice_number, $p->booking?->code, $p->amount, $p->status->label(),
                    $p->paid_at?->toDateTimeString() ?? '',
                ])),

            'guests' => $csv->stream("laporan-tamu-{$stamp}.csv",
                ['Nama', 'Email', 'Telepon', 'Negara', 'Jumlah Pemesanan', 'Nilai Total'],
                $data->map(fn ($g) => [
                    $g->customer_name, $g->customer_email, $g->customer_phone,
                    $g->customer_country, $g->bookings_count, $g->total_value,
                ])),

            'room_usage' => $csv->stream("laporan-penggunaan-kamar-{$stamp}.csv",
                ['Tipe Kamar', 'Kamar Terjual', 'Room-Nights', 'Subtotal'],
                $data->map(fn ($r) => [$r->name, $r->rooms_sold, $r->room_nights, $r->subtotal])),

            'occupancy' => $csv->stream("laporan-okupansi-{$stamp}.csv",
                ['Tanggal', 'Total Kamar', 'Terkonfirmasi', 'Diblokir', 'Okupansi (%)'],
                $data->map(fn ($o) => [$o['date'], $o['total'], $o['confirmed'], $o['blocked'], $o['occupancy_percent']])),

            'cancellations' => $csv->stream("laporan-pembatalan-{$stamp}.csv",
                ['Kode', 'Nama', 'Dibatalkan pada', 'Alasan', 'Total'],
                $data->map(fn ($b) => [
                    $b->code, $b->customer_name, $b->cancelled_at?->toDateTimeString(), $b->cancellation_reason, $b->total_amount,
                ])),

            'refunds' => $csv->stream("laporan-refund-{$stamp}.csv",
                ['Kode Pemesanan', 'Jumlah', 'Status', 'Referensi', 'Diproses pada'],
                $data->map(fn ($r) => [
                    $r->booking?->code, $r->amount, $r->status->label(), $r->provider_reference,
                    $r->processed_at?->toDateTimeString(),
                ])),

            default => $csv->stream("laporan-pendapatan-{$stamp}.csv",
                ['Metrik', 'Nilai (Rp)'],
                collect($reports->revenueSummary($filters))->map(fn ($v, $k) => [$k, $v])->values()),
        };
    }

    /**
     * @return Collection<int, mixed>
     */
    private function dataFor(string $type, array $filters, ReportService $reports)
    {
        return match ($type) {
            'reservations' => $reports->reservations($filters),
            'payments' => $reports->payments($filters),
            'guests' => $reports->guests($filters),
            'room_usage' => $reports->roomUsage($filters),
            'occupancy' => $reports->occupancy($filters),
            'cancellations' => $reports->cancellations($filters),
            'refunds' => $reports->refunds($filters),
            default => collect(),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function filters(Request $request): array
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'status' => ['nullable', 'string', 'max:30'],
            'payment_status' => ['nullable', 'string', 'max:30'],
            'room_type_id' => ['nullable', 'integer', 'exists:room_types,id'],
        ]);

        return [
            'from' => $validated['from'] ?? now()->startOfMonth()->toDateString(),
            'to' => $validated['to'] ?? now()->endOfMonth()->toDateString(),
            'status' => $validated['status'] ?? null,
            'payment_status' => $validated['payment_status'] ?? null,
            'room_type_id' => $validated['room_type_id'] ?? null,
        ];
    }
}
