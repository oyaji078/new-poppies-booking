<?php

namespace App\Livewire\Admin;

use App\Models\Amenity;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Component;

class AmenityManager extends Component
{
    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $icon = '';

    public string $category = 'general';

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'icon' => ['nullable', 'string', 'max:60'],
            'category' => ['required', 'string', 'max:60'],
        ];
    }

    public function openCreate(): void
    {
        $this->reset(['editingId', 'name', 'icon']);
        $this->category = 'general';
        $this->resetValidation();
        $this->showForm = true;
    }

    public function openEdit(int $id): void
    {
        $amenity = Amenity::findOrFail($id);
        $this->editingId = $amenity->id;
        $this->name = $amenity->name;
        $this->icon = (string) $amenity->icon;
        $this->category = $amenity->category;
        $this->resetValidation();
        $this->showForm = true;
    }

    public function save(): void
    {
        $data = $this->validate();

        if ($this->editingId) {
            $amenity = Amenity::findOrFail($this->editingId);
            if ($amenity->name !== $data['name']) {
                $data['slug'] = Str::slug($data['name']).'-'.$amenity->id;
            }
            $amenity->update($data);
        } else {
            $data['slug'] = Str::slug($data['name']).'-'.Str::random(5);
            Amenity::create($data);
        }

        session()->flash('success', 'Fasilitas disimpan.');
        $this->showForm = false;
    }

    public function delete(int $id): void
    {
        Amenity::findOrFail($id)->delete();
        session()->flash('success', 'Fasilitas dihapus.');
    }

    public function render(): View
    {
        $amenities = Amenity::withCount('roomTypes')->orderBy('name')->get();

        return view('livewire.admin.amenity-manager', compact('amenities'))
            ->layout('components.layouts.admin', [
                'title' => 'Fasilitas',
                'heading' => 'Fasilitas',
                'breadcrumb' => 'Admin / Fasilitas',
            ]);
    }
}
