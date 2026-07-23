<?php

namespace App\Livewire\Admin;

use App\Enums\PromotionType;
use App\Models\Promotion;
use App\Models\RoomType;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;

class PromotionManager extends Component
{
    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public ?string $code = null;

    public string $type = 'percentage';

    public ?int $value = null;

    public ?int $max_discount = null;

    public bool $is_automatic = false;

    public int $min_nights = 1;

    public int $min_transaction = 0;

    public ?int $usage_limit = null;

    public ?string $booking_start = null;

    public ?string $booking_end = null;

    public ?string $stay_start = null;

    public ?string $stay_end = null;

    public bool $is_active = true;

    /** @var array<int, int> */
    public array $roomTypeIds = [];

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:60', Rule::unique('promotions', 'code')->ignore($this->editingId)],
            'type' => ['required', Rule::in(['percentage', 'fixed'])],
            'value' => ['required', 'integer', 'min:1'],
            'max_discount' => ['nullable', 'integer', 'min:0'],
            'is_automatic' => ['boolean'],
            'min_nights' => ['required', 'integer', 'min:1'],
            'min_transaction' => ['required', 'integer', 'min:0'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'booking_start' => ['nullable', 'date'],
            'booking_end' => ['nullable', 'date', 'after_or_equal:booking_start'],
            'stay_start' => ['nullable', 'date'],
            'stay_end' => ['nullable', 'date', 'after_or_equal:stay_start'],
            'is_active' => ['boolean'],
            'roomTypeIds' => ['array'],
            'roomTypeIds.*' => [Rule::exists('room_types', 'id')],
        ];
    }

    public function openCreate(): void
    {
        $this->reset();
        $this->type = 'percentage';
        $this->min_nights = 1;
        $this->is_active = true;
        $this->resetValidation();
        $this->showForm = true;
    }

    public function openEdit(int $id): void
    {
        $p = Promotion::with('roomTypes')->findOrFail($id);
        $this->editingId = $p->id;
        $this->name = $p->name;
        $this->code = $p->code;
        $this->type = $p->type->value;
        $this->value = $p->value;
        $this->max_discount = $p->max_discount;
        $this->is_automatic = $p->is_automatic;
        $this->min_nights = $p->min_nights;
        $this->min_transaction = $p->min_transaction;
        $this->usage_limit = $p->usage_limit;
        $this->booking_start = $p->booking_start?->toDateString();
        $this->booking_end = $p->booking_end?->toDateString();
        $this->stay_start = $p->stay_start?->toDateString();
        $this->stay_end = $p->stay_end?->toDateString();
        $this->is_active = $p->is_active;
        $this->roomTypeIds = $p->roomTypes->pluck('id')->all();
        $this->resetValidation();
        $this->showForm = true;
    }

    public function save(): void
    {
        $data = $this->validate();
        $roomTypeIds = $data['roomTypeIds'] ?? [];
        unset($data['roomTypeIds']);
        $data['type'] = PromotionType::from($data['type']);

        if ($this->editingId) {
            $promo = Promotion::findOrFail($this->editingId);
            $promo->update($data);
        } else {
            $promo = Promotion::create($data);
        }
        $promo->roomTypes()->sync($roomTypeIds);

        session()->flash('success', 'Promosi disimpan.');
        $this->showForm = false;
    }

    public function toggleActive(int $id): void
    {
        $promo = Promotion::findOrFail($id);
        $promo->update(['is_active' => ! $promo->is_active]);
        session()->flash('success', 'Status promosi diperbarui.');
    }

    public function delete(int $id): void
    {
        Promotion::findOrFail($id)->delete();
        session()->flash('success', 'Promosi dihapus.');
    }

    public function render(): View
    {
        $promotions = Promotion::withCount('roomTypes')->latest()->get();
        $roomTypes = RoomType::ordered()->get();

        return view('livewire.admin.promotion-manager', compact('promotions', 'roomTypes'))
            ->layout('components.layouts.admin', [
                'title' => 'Promosi',
                'heading' => 'Promosi',
                'breadcrumb' => 'Admin / Promosi',
            ]);
    }
}
