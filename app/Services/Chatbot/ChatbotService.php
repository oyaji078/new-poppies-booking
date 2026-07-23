<?php

namespace App\Services\Chatbot;

use App\Models\ChatbotMessage;
use App\Models\ChatbotSession;
use App\Models\Faq;
use App\Models\RoomType;
use App\Services\Settings\SettingService;

/**
 * Database-backed FAQ chatbot (§25).
 *
 * Hard rules enforced here:
 *  - never invents room prices — price answers are read from published room types;
 *  - never claims a room is available (it directs users to the real search instead,
 *    because availability may only come from AvailabilityService);
 *  - never exposes customer booking data, confirms payments or changes status;
 *  - unanswered questions are stored for admin review.
 */
class ChatbotService
{
    public function __construct(private readonly SettingService $settings) {}

    public function sessionFor(string $sessionKey, ?int $userId = null): ChatbotSession
    {
        $session = ChatbotSession::firstOrCreate(
            ['session_key' => $sessionKey],
            ['user_id' => $userId],
        );

        $session->forceFill(['last_activity_at' => now()])->save();

        return $session;
    }

    /**
     * Answer a question and persist both sides of the exchange.
     *
     * @return array{answer: string, answered: bool, faq_id: ?int, suggestions: array<int, string>}
     */
    public function ask(ChatbotSession $session, string $question): array
    {
        $question = trim($question);

        ChatbotMessage::create([
            'chatbot_session_id' => $session->id,
            'role' => 'user',
            'content' => $question,
            'was_answered' => true,
        ]);

        $result = $this->resolve($question);

        ChatbotMessage::create([
            'chatbot_session_id' => $session->id,
            'role' => 'bot',
            'content' => $result['answer'],
            'faq_id' => $result['faq_id'],
            'was_answered' => $result['answered'],
        ]);

        return $result;
    }

    /**
     * @return array{answer: string, answered: bool, faq_id: ?int, suggestions: array<int, string>}
     */
    public function resolve(string $question): array
    {
        $normalised = mb_strtolower($question);

        // 1) Dynamic answers backed by real data take precedence over static FAQs.
        if ($dynamic = $this->dynamicAnswer($normalised)) {
            return $dynamic + ['faq_id' => null, 'suggestions' => $this->suggestions()];
        }

        // 2) Keyword-scored FAQ match.
        if ($faq = $this->bestFaqMatch($normalised)) {
            return [
                'answer' => $faq->answer,
                'answered' => true,
                'faq_id' => $faq->id,
                'suggestions' => $this->suggestions(),
            ];
        }

        // 3) Safe fallback — no guessing.
        return [
            'answer' => $this->fallbackMessage(),
            'answered' => false,
            'faq_id' => null,
            'suggestions' => $this->suggestions(),
        ];
    }

    /**
     * Answers that must reflect live database values.
     *
     * @return array{answer: string, answered: bool}|null
     */
    private function dynamicAnswer(string $q): ?array
    {
        $mentionsRoom = $this->containsAny($q, ['kamar', 'tipe kamar', 'room', 'jenis kamar']);
        $mentionsPrice = $this->containsAny($q, ['harga', 'tarif', 'biaya', 'price', 'berapa']);

        // Room types + real starting prices.
        if ($mentionsRoom && $mentionsPrice) {
            $types = RoomType::query()->published()->ordered()->get(['name', 'base_price', 'max_guests']);

            if ($types->isEmpty()) {
                return ['answer' => 'Saat ini informasi kamar belum tersedia. Silakan hubungi kami langsung.', 'answered' => false];
            }

            $lines = $types->map(fn ($t) => "• {$t->name} — mulai ".rupiah($t->base_price)." / malam (maks. {$t->max_guests} tamu)")->implode("\n");

            return [
                'answer' => "Berikut tipe kamar kami beserta harga mulai:\n{$lines}\n\n"
                    .'Harga akhir dapat berbeda tergantung tanggal menginap, akhir pekan, dan promo. '
                    .'Silakan cek ketersediaan untuk melihat total harga yang pasti.',
                'answered' => true,
            ];
        }

        // Room types only.
        if ($mentionsRoom && $this->containsAny($q, ['apa saja', 'tipe', 'jenis', 'daftar', 'pilihan'])) {
            $types = RoomType::query()->published()->ordered()->pluck('name');

            if ($types->isEmpty()) {
                return null;
            }

            return [
                'answer' => 'Tipe kamar yang tersedia: '.$types->implode(', ').'. '
                    .'Silakan cek halaman pencarian untuk melihat ketersediaan pada tanggal Anda.',
                'answered' => true,
            ];
        }

        // Availability questions — the bot must NEVER assert availability itself.
        if ($this->containsAny($q, ['tersedia', 'kosong', 'available', 'ketersediaan'])) {
            return [
                'answer' => 'Ketersediaan kamar berubah setiap saat dan hanya dapat dipastikan melalui pencarian '
                    .'dengan tanggal menginap Anda. Silakan gunakan menu “Cari & Pesan” untuk melihat kamar yang '
                    .'benar-benar tersedia beserta harga totalnya.',
                'answered' => true,
            ];
        }

        // Anything touching someone's booking/payment status.
        if ($this->containsAny($q, ['status pemesanan', 'booking saya', 'pesanan saya', 'sudah bayar', 'status pembayaran'])) {
            return [
                'answer' => 'Untuk alasan keamanan, status pemesanan hanya dapat dilihat melalui halaman '
                    .'“Cek Pemesanan” dengan memasukkan kode pemesanan dan email Anda. Saya tidak dapat '
                    .'menampilkan atau mengubah data pemesanan di sini.',
                'answered' => true,
            ];
        }

        // Check-in / check-out times come from settings, not hard-coded text.
        if ($this->containsAny($q, ['check-in', 'checkin', 'check in', 'check-out', 'checkout', 'check out', 'jam'])) {
            $in = (string) $this->settings->get('check_in_time', '14:00');
            $out = (string) $this->settings->get('check_out_time', '12:00');

            return [
                'answer' => "Check-in mulai pukul {$in} WITA dan check-out paling lambat pukul {$out} WITA. "
                    .'Check-in lebih awal dapat diusahakan sesuai ketersediaan kamar.',
                'answered' => true,
            ];
        }

        return null;
    }

    private function bestFaqMatch(string $q): ?Faq
    {
        $best = null;
        $bestScore = 0;

        foreach (Faq::query()->active()->orderByDesc('priority')->get() as $faq) {
            $score = 0;

            foreach ($faq->keywordList() as $keyword) {
                if ($keyword !== '' && str_contains($q, $keyword)) {
                    // Longer keyword matches are more meaningful.
                    $score += max(1, (int) floor(mb_strlen($keyword) / 3));
                }
            }

            // A near-verbatim question is a strong signal.
            if (str_contains($q, mb_strtolower(rtrim($faq->question, '?')))) {
                $score += 10;
            }

            $score += (int) $faq->priority;

            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $faq;
            }
        }

        // Require a real signal, not just a priority bump.
        return $bestScore >= 2 ? $best : null;
    }

    private function fallbackMessage(): string
    {
        $phone = (string) $this->settings->get('hotel_phone', '(0370) 000-000');
        $email = (string) $this->settings->get('hotel_email', 'reservasi@newpoppiessenggigi.test');

        return 'Maaf, saya belum memiliki jawaban untuk pertanyaan itu. Pertanyaan Anda sudah kami catat '
            ."agar dapat dijawab lebih baik ke depannya.\n\n"
            ."Untuk bantuan langsung, silakan hubungi kami:\n"
            ."• Telepon: {$phone}\n"
            ."• Email: {$email}";
    }

    /**
     * @return array<int, string>
     */
    private function suggestions(): array
    {
        return [
            'Apa saja tipe kamar dan harganya?',
            'Jam berapa check-in dan check-out?',
            'Bagaimana kebijakan pembatalan?',
            'Metode pembayaran apa yang tersedia?',
        ];
    }

    /**
     * @param  array<int, string>  $needles
     */
    private function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }
}
