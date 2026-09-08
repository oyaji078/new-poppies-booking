<?php

namespace App\Livewire\Admin;

use App\Models\GalleryImage;
use App\Services\Media\GalleryService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\UploadedFile;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;
use RuntimeException;

/**
 * Admin → Galeri. Controls the photo strip on the public homepage: what is
 * shown, in what order, and with what caption.
 */
class GalleryManager extends Component
{
    use WithFileUploads;

    /** @var array<int, UploadedFile> */
    #[Validate(['newImages.*' => 'image|mimes:jpg,jpeg,png,webp|max:4096'])]
    public array $newImages = [];

    public string $newTitle = '';

    // Inline caption editing
    public ?int $editingId = null;

    public string $title = '';

    public string $alt = '';

    public function upload(GalleryService $service): void
    {
        $this->validate(
            ['newImages' => ['required', 'array', 'min:1']],
            ['newImages.required' => 'Pilih minimal satu foto terlebih dahulu.'],
        );
        $this->validate();

        $stored = 0;

        foreach ($this->newImages as $file) {
            try {
                $service->store($file, $this->newTitle ?: null);
                $stored++;
            } catch (RuntimeException $e) {
                // A storage failure must name itself. Left uncaught it is a raw
                // 500, which on a host with display_errors on leaks paths and
                // tells the admin nothing about what to do next.
                session()->flash('error', 'Foto gagal disimpan: '.$e->getMessage());
                break;
            }
        }

        $this->reset(['newImages', 'newTitle']);

        if ($stored > 0) {
            session()->flash('success', "{$stored} foto ditambahkan ke galeri.");
        }
    }

    public function startEdit(int $id): void
    {
        $image = GalleryImage::findOrFail($id);
        $this->editingId = $image->id;
        $this->title = (string) $image->title;
        $this->alt = (string) $image->alt;
        $this->resetValidation();
    }

    public function cancelEdit(): void
    {
        $this->reset(['editingId', 'title', 'alt']);
        $this->resetValidation();
    }

    public function saveEdit(GalleryService $service): void
    {
        $this->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'alt' => ['nullable', 'string', 'max:255'],
        ]);

        $service->update(GalleryImage::findOrFail($this->editingId), $this->title, $this->alt);

        session()->flash('success', 'Keterangan foto diperbarui.');
        $this->cancelEdit();
    }

    public function togglePublish(int $id, GalleryService $service): void
    {
        $image = $service->togglePublish(GalleryImage::findOrFail($id));

        session()->flash('success', $image->is_published
            ? 'Foto ditampilkan di halaman publik.'
            : 'Foto disembunyikan dari halaman publik.');
    }

    public function moveUp(int $id, GalleryService $service): void
    {
        $service->move(GalleryImage::findOrFail($id), 'up');
    }

    public function moveDown(int $id, GalleryService $service): void
    {
        $service->move(GalleryImage::findOrFail($id), 'down');
    }

    public function delete(int $id, GalleryService $service): void
    {
        try {
            $service->delete(GalleryImage::findOrFail($id));
            session()->flash('success', 'Foto dihapus dari galeri.');
        } catch (RuntimeException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function render(): View
    {
        $images = GalleryImage::query()->ordered()->get();

        return view('livewire.admin.gallery-manager', [
            'images' => $images,
            'publishedCount' => $images->where('is_published', true)->count(),
        ])->layout('components.layouts.admin', [
            'title' => 'Galeri',
            'heading' => 'Galeri Halaman Publik',
            'breadcrumb' => 'Admin / Galeri',
        ]);
    }
}
