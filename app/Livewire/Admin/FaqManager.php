<?php

namespace App\Livewire\Admin;

use App\Models\ChatbotMessage;
use App\Models\Faq;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class FaqManager extends Component
{
    public string $tab = 'faqs'; // faqs | unanswered

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $question = '';

    public string $answer = '';

    public string $keywords = '';

    public string $category = 'general';

    public int $priority = 0;

    public bool $is_active = true;

    protected function rules(): array
    {
        return [
            'question' => ['required', 'string', 'max:255'],
            'answer' => ['required', 'string', 'max:5000'],
            'keywords' => ['nullable', 'string', 'max:500'],
            'category' => ['required', 'string', 'max:60'],
            'priority' => ['required', 'integer', 'min:0', 'max:100'],
            'is_active' => ['boolean'],
        ];
    }

    public function openCreate(): void
    {
        $this->reset(['editingId', 'question', 'answer', 'keywords']);
        $this->category = 'general';
        $this->priority = 0;
        $this->is_active = true;
        $this->resetValidation();
        $this->showForm = true;
    }

    public function openEdit(int $id): void
    {
        $faq = Faq::findOrFail($id);
        $this->editingId = $faq->id;
        $this->question = $faq->question;
        $this->answer = $faq->answer;
        $this->keywords = (string) $faq->keywords;
        $this->category = $faq->category;
        $this->priority = $faq->priority;
        $this->is_active = $faq->is_active;
        $this->resetValidation();
        $this->showForm = true;
    }

    /**
     * Turn an unanswered customer question into a new FAQ draft.
     */
    public function draftFrom(int $messageId): void
    {
        $message = ChatbotMessage::findOrFail($messageId);
        $this->openCreate();
        $this->question = mb_substr($message->content, 0, 255);
        $this->keywords = collect(explode(' ', mb_strtolower($message->content)))
            ->filter(fn ($w) => mb_strlen($w) > 3)
            ->take(5)
            ->implode(', ');
        $this->tab = 'faqs';
    }

    public function save(): void
    {
        $data = $this->validate();

        if ($this->editingId) {
            Faq::findOrFail($this->editingId)->update($data);
        } else {
            Faq::create($data);
        }

        session()->flash('success', 'FAQ disimpan.');
        $this->showForm = false;
    }

    public function toggleActive(int $id): void
    {
        $faq = Faq::findOrFail($id);
        $faq->update(['is_active' => ! $faq->is_active]);
    }

    public function delete(int $id): void
    {
        Faq::findOrFail($id)->delete();
        session()->flash('success', 'FAQ dihapus.');
    }

    public function render(): View
    {
        $faqs = Faq::orderByDesc('priority')->orderBy('category')->get();

        // Questions the bot could not answer — the backlog for improving the FAQ set.
        $unanswered = ChatbotMessage::query()
            ->where('role', 'bot')
            ->where('was_answered', false)
            ->with('session')
            ->latest('id')
            ->limit(50)
            ->get()
            ->map(function (ChatbotMessage $bot) {
                $question = ChatbotMessage::query()
                    ->where('chatbot_session_id', $bot->chatbot_session_id)
                    ->where('role', 'user')
                    ->where('id', '<', $bot->id)
                    ->latest('id')
                    ->first();

                return ['id' => $question?->id, 'content' => $question?->content, 'at' => $bot->created_at];
            })
            ->filter(fn ($row) => $row['id'] !== null)
            ->values();

        return view('livewire.admin.faq-manager', compact('faqs', 'unanswered'))
            ->layout('components.layouts.admin', [
                'title' => 'FAQ / Chatbot',
                'heading' => 'FAQ & Chatbot',
                'breadcrumb' => 'Admin / FAQ & Chatbot',
            ]);
    }
}
