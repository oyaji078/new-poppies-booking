<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Central audit trail writer. Records who did what to which entity, with a
 * before/after snapshot, IP and user agent. Sensitive keys are redacted so the
 * audit log never becomes a secondary leak of passwords or payment secrets.
 */
class AuditLogger
{
    /**
     * Keys that must never be written to the audit trail in clear text.
     *
     * @var list<string>
     */
    private array $redactKeys = [
        'password', 'password_confirmation', 'remember_token', 'secret_key',
        'doku_secret_key', 'client_secret', 'signature', 'digest',
        'id_card_number', 'request_payload', 'response_payload',
    ];

    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public function log(
        string $action,
        ?Model $entity = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?User $actor = null,
    ): AuditLog {
        $actor ??= Auth::user();

        return AuditLog::create([
            'user_id' => $actor?->getKey(),
            'action' => $action,
            'entity_type' => $entity ? $entity::class : null,
            'entity_id' => $entity?->getKey(),
            'old_values' => $oldValues ? $this->redact($oldValues) : null,
            'new_values' => $newValues ? $this->redact($newValues) : null,
            'ip_address' => Request::ip(),
            'user_agent' => substr((string) Request::userAgent(), 0, 500),
        ]);
    }

    /**
     * Convenience: record the dirty attributes of a just-saved model.
     */
    public function logModelChange(string $action, Model $model, ?array $original = null): AuditLog
    {
        $original ??= $model->getOriginal();
        $changes = $model->getChanges();

        $before = array_intersect_key($original, $changes);

        return $this->log($action, $model, $before ?: null, $changes ?: null);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function redact(array $values): array
    {
        foreach ($values as $key => $value) {
            if (in_array(strtolower((string) $key), $this->redactKeys, true)) {
                $values[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $values[$key] = $this->redact($value);
            }
        }

        return $values;
    }
}
