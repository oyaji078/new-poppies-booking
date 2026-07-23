<?php

namespace Tests\Feature\Chatbot;

use App\Models\Faq;
use App\Models\RoomType;
use App\Services\Chatbot\ChatbotService;
use App\Services\Settings\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatbotServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): ChatbotService
    {
        return app(ChatbotService::class);
    }

    public function test_it_answers_from_a_matching_faq(): void
    {
        Faq::create([
            'question' => 'Apakah tersedia sarapan?',
            'answer' => 'Ya, sarapan sudah termasuk dalam harga kamar.',
            'keywords' => 'sarapan, breakfast, makan pagi',
            'category' => 'fasilitas',
            'priority' => 5,
            'is_active' => true,
        ]);

        $result = $this->service()->resolve('apakah ada sarapan pagi?');

        $this->assertTrue($result['answered']);
        $this->assertStringContainsString('sarapan sudah termasuk', $result['answer']);
    }

    public function test_inactive_faqs_are_not_used(): void
    {
        Faq::create([
            'question' => 'Pertanyaan nonaktif',
            'answer' => 'Jawaban rahasia yang tidak boleh muncul.',
            'keywords' => 'rahasia',
            'is_active' => false,
        ]);

        $result = $this->service()->resolve('ceritakan yang rahasia dong');

        $this->assertFalse($result['answered']);
        $this->assertStringNotContainsString('Jawaban rahasia', $result['answer']);
    }

    public function test_price_answers_come_from_the_database_never_invented(): void
    {
        RoomType::factory()->create(['name' => 'Deluxe Test', 'base_price' => 850_000, 'is_published' => true]);

        $result = $this->service()->resolve('berapa harga kamar?');

        $this->assertTrue($result['answered']);
        $this->assertStringContainsString('Deluxe Test', $result['answer']);
        $this->assertStringContainsString('Rp 850.000', $result['answer']);
    }

    public function test_unpublished_room_types_are_not_quoted(): void
    {
        RoomType::factory()->create(['name' => 'Public Room', 'base_price' => 500_000, 'is_published' => true]);
        RoomType::factory()->unpublished()->create(['name' => 'Hidden Room', 'base_price' => 999_000]);

        $result = $this->service()->resolve('berapa harga kamar?');

        $this->assertStringContainsString('Public Room', $result['answer']);
        $this->assertStringNotContainsString('Hidden Room', $result['answer']);
    }

    public function test_it_never_claims_availability_itself(): void
    {
        $result = $this->service()->resolve('apakah kamar tersedia besok?');

        // It must redirect to the real availability search, not assert availability.
        $this->assertStringContainsString('Cari & Pesan', $result['answer']);
        $this->assertStringNotContainsString('tersedia untuk Anda', $result['answer']);
    }

    public function test_it_refuses_to_expose_booking_data(): void
    {
        $result = $this->service()->resolve('tolong cek status pemesanan saya');

        $this->assertStringContainsString('Cek Pemesanan', $result['answer']);
        $this->assertStringContainsString('tidak dapat', $result['answer']);
    }

    public function test_check_in_times_come_from_settings(): void
    {
        app(SettingService::class)->set('check_in_time', '15:00', 'string');
        app(SettingService::class)->set('check_out_time', '11:00', 'string');

        $result = $this->service()->resolve('jam berapa check-in?');

        $this->assertStringContainsString('15:00', $result['answer']);
        $this->assertStringContainsString('11:00', $result['answer']);
    }

    public function test_unknown_question_falls_back_with_contact_details_and_is_stored(): void
    {
        $service = $this->service();
        $session = $service->sessionFor('test-session-key');

        $result = $service->ask($session, 'Apakah boleh membawa gajah peliharaan?');

        $this->assertFalse($result['answered']);
        $this->assertStringContainsString('belum memiliki jawaban', $result['answer']);

        // The unanswered exchange is stored for admin review.
        $this->assertDatabaseHas('chatbot_messages', [
            'chatbot_session_id' => $session->id,
            'role' => 'bot',
            'was_answered' => false,
        ]);
        $this->assertDatabaseHas('chatbot_messages', [
            'role' => 'user',
            'content' => 'Apakah boleh membawa gajah peliharaan?',
        ]);
    }
}
