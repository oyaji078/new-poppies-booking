<?php

namespace App\Livewire\Admin;

use App\Enums\AuditAction;
use App\Models\Room;
use App\Models\RoomType;
use App\Services\Audit\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class RoomManager extends Component
{
    use WithPagination;

    public string $search = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public ?int $room_type_id = null;

    public string $room_number = '';

    public string $floor = '';

    public bool $is_active = true;

    public bool $under_maintenance = false;

    public string $internal_notes = '';

    protected function rules(): array
    {
        return [
            'room_type_id' => ['required', Rule::exists('room_types', 'id')],
            'room_number' => ['required', 'string', 'max:20', Rule::unique('rooms', 'room_number')->ignore($this->editingId)],
            'floor' => ['nullable', 'string', 'max:20'],
            'is_active' => ['boolean'],
            'under_maintenance' => ['boolean'],
            'internal_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function openCreate(): void
    {
        $this->reset(['editingId', 'room_number', 'floor', 'internal_notes']);
        $this->is_active = true;
        $this->under_maintenance = false;
        $this->room_type_id = RoomType::query()->value('id');
        $this->resetValidation();
        $this->showForm = true;
    }

    public function openEdit(int $id): void
    {
        $room = Room::findOrFail($id);
        $this->editingId = $room->id;
        $this->room_type_id = $room->room_type_id;
        $this->room_number = $room->room_number;
        $this->floor = (string) $room->floor;
        $this->is_active = $room->is_active;
        $this->under_maintenance = $room->under_maintenance;
        $this->internal_notes = (string) $room->internal_notes;
        $this->resetValidation();
        $this->showForm = true;
    }

    public function save(AuditLogger $audit): void
    {
        $data = $this->validate();

        if ($this->editingId) {
            $room = Room::findOrFail($this->editingId);
            $room->update($data);
        } else {
            $room = Room::create($data);
        }

        $audit->log(AuditAction::ROOM_CHANGE->value, $room, null, $room->only(['room_number', 'is_active', 'under_maintenance']));

        session()->flash('success', 'Kamar berhasil disimpan.');
        $this->showForm = false;
    }

    public function toggleMaintenance(int $id, AuditLogger $audit): void
    {
        $room = Room::findOrFail($id);
        $room->update(['under_maintenance' => ! $room->under_maintenance]);
        $audit->log(AuditAction::ROOM_CHANGE->value, $room, null, ['under_maintenance' => $room->under_maintenance]);
        session()->flash('success', 'Status pemeliharaan diperbarui.');
    }

    public function delete(int $id): void
    {
        $room = Room::findOrFail($id);

        // room_assignments.room_id restricts deletes, so a room that has ever
        // been handed to a guest cannot go. Without this check the database
        // raises the objection instead and the admin sees a 500 page. Taking
        // the room out of service is the operation they actually want.
        if ($room->assignments()->exists()) {
            session()->flash('error', "Kamar {$room->room_number} pernah dipakai pemesanan, jadi tidak dapat dihapus. Nonaktifkan atau tandai pemeliharaan agar tidak dijual lagi.");

            return;
        }

        $room->delete();
        session()->flash('success', 'Kamar dihapus.');
    }

    public function render(): View
    {
        $rooms = Room::query()
            ->with('roomType')
            ->when($this->search, fn ($q) => $q->whereLike('room_number', "%{$this->search}%", caseSensitive: false))
            ->orderBy('room_number')
            ->paginate(15);

        $roomTypes = RoomType::ordered()->get();

        return view('livewire.admin.room-manager', compact('rooms', 'roomTypes'))
            ->layout('components.layouts.admin', [
                'title' => 'Kamar Fisik',
                'heading' => 'Kamar Fisik',
                'breadcrumb' => 'Admin / Kamar Fisik',
            ]);
    }
}
