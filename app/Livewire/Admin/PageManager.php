<?php

namespace App\Livewire\Admin;

use App\Models\Page;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;

class PageManager extends Component
{
    public bool $showForm = false;

    public ?int $editingId = null;

    public string $slug = '';

    public string $title = '';

    public string $body = '';

    public bool $is_published = true;

    protected function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('pages', 'slug')->ignore($this->editingId)],
            'body' => ['nullable', 'string'],
            'is_published' => ['boolean'],
        ];
    }

    public function openCreate(): void
    {
        $this->reset(['editingId', 'slug', 'title', 'body']);
        $this->is_published = true;
        $this->resetValidation();
        $this->showForm = true;
    }

    public function openEdit(int $id): void
    {
        $page = Page::findOrFail($id);
        $this->editingId = $page->id;
        $this->slug = $page->slug;
        $this->title = $page->title;
        $this->body = (string) $page->body;
        $this->is_published = $page->is_published;
        $this->resetValidation();
        $this->showForm = true;
    }

    public function updatedTitle(string $value): void
    {
        if (! $this->editingId) {
            $this->slug = Str::slug($value);
        }
    }

    public function save(): void
    {
        $data = $this->validate();

        if ($this->editingId) {
            Page::findOrFail($this->editingId)->update($data);
        } else {
            Page::create($data);
        }

        session()->flash('success', 'Halaman disimpan.');
        $this->showForm = false;
    }

    public function delete(int $id): void
    {
        Page::findOrFail($id)->delete();
        session()->flash('success', 'Halaman dihapus.');
    }

    public function render(): View
    {
        $pages = Page::orderBy('title')->get();

        return view('livewire.admin.page-manager', compact('pages'))
            ->layout('components.layouts.admin', [
                'title' => 'Konten Website',
                'heading' => 'Konten Website',
                'breadcrumb' => 'Admin / Konten Website',
            ]);
    }
}
