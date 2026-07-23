<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'type',
        'group',
        'label',
        'is_public',
    ];

    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
        ];
    }

    /**
     * Cast the stored string value to its declared type.
     */
    public function typedValue(): mixed
    {
        return match ($this->type) {
            'integer' => (int) $this->value,
            'boolean' => filter_var($this->value, FILTER_VALIDATE_BOOLEAN),
            'json' => json_decode((string) $this->value, true),
            default => $this->value,
        };
    }
}
