<?php

namespace App\Livewire\Admin;

use App\Models\Amenity;
use App\Models\RoomImage;
use App\Models\RoomType;
use App\Services\Rooms\RoomImageService;
use App\Services\Rooms\RoomTypeService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class RoomTypeManager extends Component
{
    use WithFileUploads;
    use WithPagination;

    public string $search = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    // Form fields
    public string $name = '';

    public string $short_description = '';

    public string $full_description = '';

    public int $adult_capacity = 2;

    public int $child_capacity = 0;

    public int $max_guests = 2;

    public string $bed_type = '';

    public ?int $room_size = null;

    public ?int $base_price = null;

    public int $default_inventory = 0;

    public string $policies = '';

    public bool $is_published = false;

    /** @var array<int, int> */
    public array $amenityIds = [];

    /** @var array<int, UploadedFile> */
    #[Validate(['newImages.*' => 'image|mimes:jpg,jpeg,png,webp|max:4096'])]
    public array $newImages = [];

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'short_description' => ['nullable', 'string', 'max:500'],
            'full_description' => ['nullable', 'string'],
            'adult_capacity' => ['required', 'integer', 'min:1', 'max:20'],
            'child_capacity' => ['required', 'integer', 'min:0', 'max:20'],
            'max_guests' => ['required', 'integer', 'min:1', 'max:40'],
            'bed_type' => ['nullable', 'string', 'max:100'],
            'room_size' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'base_price' => ['required', 'integer', 'min:0'],
            'default_inventory' => ['required', 'integer', 'min:0'],
            'policies' => ['nullable', 'string'],
            'is_published' => ['boolean'],
            'amenityIds' => ['array'],
            'amenityIds.*' => [Rule::exists('amenities', 'id')],
        ];
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function openEdit(int $id): void
    {
        $roomType = RoomType::with('amenities')->findOrFail($id);

        $this->editingId = $roomType->id;
        $this->name = $roomType->name;
        $this->short_description = (string) $roomType->short_description;
        $this->full_description = (string) $roomType->full_description;
        $this->adult_capacity = $roomType->adult_capacity;
        $this->child_capacity = $roomType->child_capacity;
        $this->max_guests = $roomType->max_guests;
        $this->bed_type = (string) $roomType->bed_type;
        $this->room_size = $roomType->room_size;
        $this->base_price = $roomType->base_price;
        $this->default_inventory = $roomType->default_inventory;
        $this->policies = (string) $roomType->policies;
        $this->is_published = $roomType->is_published;
        $this->amenityIds = $roomType->amenities->pluck('id')->all();
        $this->newImages = [];
        $this->showForm = true;
    }

    public function save(RoomTypeService $service, RoomImageService $imageService): void
    {
        $validated = $this->validate();

        $data = collect($validated)->except('amenityIds')->all();

        if ($this->editingId) {
            $roomType = RoomType::findOrFail($this->editingId);
            $service->update($roomType, $data, $this->amenityIds);
        } else {
            $roomType = $service->create($data, $this->amenityIds);
            $this->editingId = $roomType->id;
        }

        foreach ($this->newImages as $image) {
            $imageService->store($roomType, $image);
        }
        $this->newImages = [];

        session()->flash('success', 'Tipe kamar berhasil disimpan.');
        $this->showForm = false;
        $this->resetForm();
    }

    public function togglePublish(int $id, RoomTypeService $service): void
    {
        $service->togglePublish(RoomType::findOrFail($id));
        session()->flash('success', 'Status publikasi diperbarui.');
    }

    public function makePrimary(int $imageId, RoomImageService $service): void
    {
        $service->makePrimary(RoomImage::findOrFail($imageId));
    }

    public function deleteImage(int $imageId, RoomImageService $service): void
    {
        $service->delete(RoomImage::findOrFail($imageId));
    }

    public function delete(int $id): void
    {
        $roomType = RoomType::findOrFail($id);
        // Keep referential safety: block deletion when bookings exist (added later phases).
        $roomType->delete();
        session()->flash('success', 'Tipe kamar dihapus.');
    }

    private function resetForm(): void
    {
        $this->reset([
            'editingId', 'name', 'short_description', 'full_description',
            'bed_type', 'room_size', 'base_price', 'policies', 'amenityIds', 'newImages',
        ]);
        $this->adult_capacity = 2;
        $this->child_capacity = 0;
        $this->max_guests = 2;
        $this->default_inventory = 0;
        $this->is_published = false;
        $this->resetValidation();
    }

    public function render(): View
    {
        $roomTypes = RoomType::query()
            ->withCount('rooms')
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->ordered()
            ->paginate(10);

        $amenities = Amenity::orderBy('name')->get();

        $editingImages = $this->editingId
            ? RoomImage::where('room_type_id', $this->editingId)->orderBy('sort_order')->get()
            : collect();

        return view('livewire.admin.room-type-manager', compact('roomTypes', 'amenities', 'editingImages'))
            ->layout('components.layouts.admin', [
                'title' => 'Tipe Kamar',
                'heading' => 'Tipe Kamar',
                'breadcrumb' => 'Admin / Tipe Kamar',
            ]);
    }
}
