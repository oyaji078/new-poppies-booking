<?php

namespace App\Services\Settings;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Cache;

/**
 * Thin cached accessor over the system_settings table. Falls back to config()
 * defaults so the app keeps working before any row is seeded. Business services
 * read hotel policy values (tax, service charge, hold minutes, ...) from here.
 */
class SettingService
{
    private const CACHE_KEY = 'system_settings.all';

    /**
     * @return array<string, SystemSetting>
     */
    private function all(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            return SystemSetting::query()->get()->keyBy('key')->all();
        });
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $settings = $this->all();

        if (! array_key_exists($key, $settings)) {
            return $default;
        }

        return $settings[$key]->typedValue();
    }

    public function integer(string $key, int $default = 0): int
    {
        $value = $this->get($key, $default);

        return is_numeric($value) ? (int) $value : $default;
    }

    public function boolean(string $key, bool $default = false): bool
    {
        $value = $this->get($key, $default);

        return is_bool($value) ? $value : filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public function set(string $key, mixed $value, string $type = 'string', string $group = 'general', ?string $label = null, bool $isPublic = false): SystemSetting
    {
        $stored = $type === 'json' ? json_encode($value) : (string) (is_bool($value) ? ($value ? '1' : '0') : $value);

        $setting = SystemSetting::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $stored, 'type' => $type, 'group' => $group, 'label' => $label, 'is_public' => $isPublic],
        );

        $this->flush();

        return $setting;
    }

    /**
     * @return array<string, mixed>
     */
    public function publicSettings(): array
    {
        return collect($this->all())
            ->filter(fn (SystemSetting $s) => $s->is_public)
            ->map(fn (SystemSetting $s) => $s->typedValue())
            ->all();
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
