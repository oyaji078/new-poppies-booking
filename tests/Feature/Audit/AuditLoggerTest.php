<?php

namespace Tests\Feature\Audit;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLoggerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_records_an_audit_entry(): void
    {
        $user = User::factory()->admin()->create();
        $this->actingAs($user);

        app(AuditLogger::class)->log('room.change', $user, ['name' => 'old'], ['name' => 'new']);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'room.change',
            'user_id' => $user->id,
            'entity_type' => User::class,
            'entity_id' => $user->id,
        ]);
    }

    public function test_it_redacts_sensitive_keys(): void
    {
        app(AuditLogger::class)->log('settings.change', null, null, [
            'doku_secret_key' => 'super-secret',
            'password' => 'hunter2',
            'safe' => 'visible',
        ]);

        $log = AuditLog::query()->latest('id')->first();

        $this->assertSame('[REDACTED]', $log->new_values['doku_secret_key']);
        $this->assertSame('[REDACTED]', $log->new_values['password']);
        $this->assertSame('visible', $log->new_values['safe']);
    }
}
