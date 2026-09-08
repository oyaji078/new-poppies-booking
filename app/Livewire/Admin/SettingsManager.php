<?php

namespace App\Livewire\Admin;

use App\Enums\AuditAction;
use App\Services\Audit\AuditLogger;
use App\Services\Settings\SettingService;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Super-admin-only editor for the hotel's operating parameters.
 *
 * These values (tax, service charge, hold duration, cancellation policy, hotel
 * identity) drive pricing and money handling across the app, so the screen is
 * super-admin-only and every save is audited. Each field is typed and validated
 * before it reaches the settings store.
 */
class SettingsManager extends Component
{
    // Hotel identity (public)
    public string $hotel_name = '';

    public string $hotel_address = '';

    public string $hotel_phone = '';

    public string $hotel_email = '';

    public string $check_in_time = '14:00';

    public string $check_out_time = '12:00';

    // Pricing
    public int $tax_percent = 11;

    public int $service_percent = 10;

    // Booking policy
    public int $booking_hold_minutes = 30;

    public int $booking_max_nights = 30;

    public int $free_cancellation_hours = 24;

    public int $cancellation_fee_percent = 50;

    /**
     * Whether guests may reserve without paying online and settle cash at the
     * desk. It decides whether a room can be taken off sale on a promise, so it
     * belongs on this screen with the rest of the money handling — not in code.
     */
    public bool $cash_payment_enabled = true;

    public function mount(SettingService $settings): void
    {
        $this->authorizeSuperAdmin();

        $this->hotel_name = (string) $settings->get('hotel_name', config('app.name'));
        $this->hotel_address = (string) $settings->get('hotel_address', '');
        $this->hotel_phone = (string) $settings->get('hotel_phone', '');
        $this->hotel_email = (string) $settings->get('hotel_email', '');
        $this->check_in_time = (string) $settings->get('check_in_time', '14:00');
        $this->check_out_time = (string) $settings->get('check_out_time', '12:00');

        $this->tax_percent = $settings->integer('tax_percent', 11);
        $this->service_percent = $settings->integer('service_percent', 10);

        $this->booking_hold_minutes = $settings->integer('booking_hold_minutes', 30);
        $this->booking_max_nights = $settings->integer('booking_max_nights', 30);
        $this->free_cancellation_hours = $settings->integer('free_cancellation_hours', 24);
        $this->cancellation_fee_percent = $settings->integer('cancellation_fee_percent', 50);
        $this->cash_payment_enabled = $settings->boolean('cash_payment_enabled', true);
    }

    private function authorizeSuperAdmin(): void
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403, 'Halaman ini hanya dapat diakses oleh Super Admin.');
    }

    public function save(SettingService $settings, AuditLogger $audit): void
    {
        $this->authorizeSuperAdmin();

        $this->validate([
            'hotel_name' => ['required', 'string', 'max:120'],
            'hotel_address' => ['nullable', 'string', 'max:255'],
            'hotel_phone' => ['nullable', 'string', 'max:60'],
            'hotel_email' => ['nullable', 'email', 'max:120'],
            'check_in_time' => ['required', 'date_format:H:i'],
            'check_out_time' => ['required', 'date_format:H:i'],
            'tax_percent' => ['required', 'integer', 'min:0', 'max:100'],
            'service_percent' => ['required', 'integer', 'min:0', 'max:100'],
            'booking_hold_minutes' => ['required', 'integer', 'min:5', 'max:180'],
            'booking_max_nights' => ['required', 'integer', 'min:1', 'max:365'],
            'free_cancellation_hours' => ['required', 'integer', 'min:0', 'max:720'],
            'cancellation_fee_percent' => ['required', 'integer', 'min:0', 'max:100'],
            'cash_payment_enabled' => ['boolean'],
        ], [], [
            'hotel_name' => 'nama hotel',
            'check_in_time' => 'jam check-in',
            'check_out_time' => 'jam check-out',
        ]);

        // A payment page must never outlive the hold (see DokuCheckoutService).
        if ($this->booking_hold_minutes < (int) config('doku.payment_due_minutes', 30)) {
            $this->addError('booking_hold_minutes',
                'Durasi hold tidak boleh lebih pendek dari jendela pembayaran DOKU ('.config('doku.payment_due_minutes').' menit).');

            return;
        }

        $map = [
            ['hotel_name', $this->hotel_name, 'string', 'hotel', 'Nama Hotel', true],
            ['hotel_address', $this->hotel_address, 'string', 'hotel', 'Alamat', true],
            ['hotel_phone', $this->hotel_phone, 'string', 'hotel', 'Telepon', true],
            ['hotel_email', $this->hotel_email, 'string', 'hotel', 'Email', true],
            ['check_in_time', $this->check_in_time, 'string', 'hotel', 'Jam Check-in', true],
            ['check_out_time', $this->check_out_time, 'string', 'hotel', 'Jam Check-out', true],
            ['tax_percent', $this->tax_percent, 'integer', 'pricing', 'PPN (%)', false],
            ['service_percent', $this->service_percent, 'integer', 'pricing', 'Service Charge (%)', false],
            ['booking_hold_minutes', $this->booking_hold_minutes, 'integer', 'booking', 'Durasi Hold (menit)', false],
            ['booking_max_nights', $this->booking_max_nights, 'integer', 'booking', 'Maksimal Malam', false],
            ['free_cancellation_hours', $this->free_cancellation_hours, 'integer', 'booking', 'Batas Pembatalan Gratis (jam)', false],
            ['cancellation_fee_percent', $this->cancellation_fee_percent, 'integer', 'booking', 'Biaya Pembatalan (%)', false],
            // Public: the booking page reads it to decide whether to offer the
            // pay-at-hotel button at all.
            ['cash_payment_enabled', $this->cash_payment_enabled, 'boolean', 'booking', 'Aktifkan Bayar di Tempat (Tunai)', true],
        ];

        foreach ($map as [$key, $value, $type, $group, $label, $public]) {
            $settings->set($key, $value, $type, $group, $label, $public);
        }

        $audit->log(AuditAction::SETTINGS_CHANGE->value, null, null, [
            'by' => auth()->user()->email,
            'keys' => array_column($map, 0),
        ]);

        session()->flash('status', 'Pengaturan berhasil disimpan.');
    }

    public function render(): View
    {
        return view('livewire.admin.settings-manager')
            ->layout('components.layouts.admin', [
                'title' => 'Pengaturan',
                'heading' => 'Pengaturan',
                'breadcrumb' => 'Admin / Pengaturan',
            ]);
    }
}
