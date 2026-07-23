<?php

namespace App\Livewire\Admin;

use App\Models\RoomType;
use App\Models\RoomTypeInventory;
use App\Services\Booking\InventoryService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class InventoryCalendar extends Component
{
    public ?int $roomTypeId = null;

    public string $month;

    // Bulk panel
    public bool $showBulk = false;

    public string $bulkStart = '';

    public string $bulkEnd = '';

    public ?int $bulkTotal = null;

    public ?int $bulkBlocked = null;

    public ?int $bulkPrice = null;

    public function mount(): void
    {
        $this->roomTypeId = RoomType::query()->value('id');
        $this->month = CarbonImmutable::today()->format('Y-m');
    }

    public function previousMonth(): void
    {
        $this->month = CarbonImmutable::parse($this->month.'-01')->subMonth()->format('Y-m');
    }

    public function nextMonth(): void
    {
        $this->month = CarbonImmutable::parse($this->month.'-01')->addMonth()->format('Y-m');
    }

    public function saveBulk(InventoryService $service): void
    {
        $this->validate([
            'bulkStart' => ['required', 'date'],
            'bulkEnd' => ['required', 'date', 'after:bulkStart'],
            'bulkTotal' => ['nullable', 'integer', 'min:0'],
            'bulkBlocked' => ['nullable', 'integer', 'min:0'],
            'bulkPrice' => ['nullable', 'integer', 'min:0'],
        ]);

        $roomType = RoomType::findOrFail($this->roomTypeId);
        $changes = array_filter([
            'total' => $this->bulkTotal,
            'blocked' => $this->bulkBlocked,
            'price' => $this->bulkPrice,
        ], fn ($v) => $v !== null);

        if ($changes === []) {
            $this->addError('bulkTotal', 'Isi minimal satu nilai untuk diperbarui.');

            return;
        }

        try {
            $count = $service->bulkUpdate($roomType, $this->bulkStart, $this->bulkEnd, $changes);
            session()->flash('success', "Inventaris diperbarui untuk {$count} hari.");
            $this->reset(['showBulk', 'bulkTotal', 'bulkBlocked', 'bulkPrice']);
        } catch (\RuntimeException $e) {
            $this->addError('bulkTotal', $e->getMessage());
        }
    }

    public function block(string $date, InventoryService $service): void
    {
        $roomType = RoomType::findOrFail($this->roomTypeId);
        [$row] = array_values($service->ensureRows($roomType, [$date]));
        try {
            // Block all remaining sellable units for the day.
            $service->setBlocked($row, $row->total_inventory - $row->confirmed_inventory - $row->held_inventory);
            session()->flash('success', "Tanggal {$date} diblokir.");
        } catch (\RuntimeException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function reopen(string $date, InventoryService $service): void
    {
        $roomType = RoomType::findOrFail($this->roomTypeId);
        $row = RoomTypeInventory::where('room_type_id', $roomType->id)->whereDate('inventory_date', $date)->first();
        if ($row) {
            $service->setBlocked($row, 0);
            session()->flash('success', "Tanggal {$date} dibuka kembali.");
        }
    }

    public function render(): View
    {
        $roomType = $this->roomTypeId ? RoomType::find($this->roomTypeId) : null;
        $monthStart = CarbonImmutable::parse($this->month.'-01')->startOfMonth();
        $days = $roomType ? app(InventoryService::class)->calendar($roomType, $monthStart) : [];
        $roomTypes = RoomType::ordered()->get();

        return view('livewire.admin.inventory-calendar', [
            'roomType' => $roomType,
            'days' => $days,
            'roomTypes' => $roomTypes,
            'monthLabel' => $monthStart->translatedFormat('F Y'),
        ])->layout('components.layouts.admin', [
            'title' => 'Inventaris & Kalender',
            'heading' => 'Inventaris & Kalender',
            'breadcrumb' => 'Admin / Inventaris',
        ]);
    }
}
