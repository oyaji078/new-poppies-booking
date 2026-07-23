<?php

namespace App\Livewire\Public;

use App\Services\Chatbot\ChatbotService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Component;

class ChatbotWidget extends Component
{
    public bool $open = false;

    public string $draft = '';

    /** @var array<int, array{role: string, content: string}> */
    public array $messages = [];

    /** @var array<int, string> */
    public array $suggestions = [];

    public string $sessionKey = '';

    public function mount(ChatbotService $chatbot): void
    {
        $this->sessionKey = (string) session()->get('chatbot_key', '');

        if ($this->sessionKey === '') {
            $this->sessionKey = (string) Str::uuid();
            session()->put('chatbot_key', $this->sessionKey);
        }

        $this->messages = [[
            'role' => 'bot',
            'content' => 'Halo! Saya asisten New Poppies Senggigi. Ada yang bisa saya bantu seputar kamar, '
                .'harga, fasilitas, atau cara pemesanan?',
        ]];

        $this->suggestions = [
            'Apa saja tipe kamar dan harganya?',
            'Jam berapa check-in dan check-out?',
            'Bagaimana kebijakan pembatalan?',
        ];
    }

    public function askSuggestion(string $question, ChatbotService $chatbot): void
    {
        $this->draft = $question;
        $this->send($chatbot);
    }

    public function send(ChatbotService $chatbot): void
    {
        $question = trim($this->draft);

        if ($question === '') {
            return;
        }

        if (mb_strlen($question) > 500) {
            $question = mb_substr($question, 0, 500);
        }

        $this->messages[] = ['role' => 'user', 'content' => $question];
        $this->draft = '';

        $session = $chatbot->sessionFor($this->sessionKey, auth()->id());
        $result = $chatbot->ask($session, $question);

        $this->messages[] = ['role' => 'bot', 'content' => $result['answer']];
        $this->suggestions = $result['suggestions'];
    }

    public function render(): View
    {
        return view('livewire.public.chatbot-widget');
    }
}
