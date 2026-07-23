<?php

namespace App\Livewire\Admin;

use App\Models\Booking;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class GuestList extends Component
{
    use WithPagination;

    public string $search = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        // One row per guest (by email), aggregated from their bookings.
        $guests = Booking::query()
            ->select([
                'customer_email',
                DB::raw('MAX(customer_name) as customer_name'),
                DB::raw('MAX(customer_phone) as customer_phone'),
                DB::raw('MAX(customer_country) as customer_country'),
                DB::raw('COUNT(*) as bookings_count'),
                DB::raw("SUM(CASE WHEN status IN ('confirmed','checked_in','checked_out') THEN total_amount ELSE 0 END) as lifetime_value"),
                DB::raw('MAX(check_in_date) as last_stay'),
            ])
            ->when($this->search, function ($q) {
                $term = "%{$this->search}%";
                $q->where(fn ($s) => $s->where('customer_name', 'like', $term)
                    ->orWhere('customer_email', 'like', $term)
                    ->orWhere('customer_phone', 'like', $term));
            })
            ->groupBy('customer_email')
            ->orderByDesc('bookings_count')
            ->paginate(20);

        return view('livewire.admin.guest-list', compact('guests'))
            ->layout('components.layouts.admin', [
                'title' => 'Tamu',
                'heading' => 'Data Tamu',
                'breadcrumb' => 'Admin / Tamu',
            ]);
    }
}
