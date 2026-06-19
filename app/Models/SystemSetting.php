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
        'is_public',
    ];

    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return config('cleanops.default_settings', []);
    }

    /**
     * @return array<string, mixed>
     */
    public static function allKeyed(): array
    {
        $settings = static::query()->get()->keyBy('key');

        return collect(static::defaults())
            ->mapWithKeys(function (array $definition, string $key) use ($settings): array {
                $setting = $settings->get($key);

                return [$key => $setting ? static::decodeValue($setting->value, $setting->type) : $definition['value']];
            })
            ->all();
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, array{old: mixed, new: mixed}>
     */
    public static function putMany(array $values): array
    {
        $changes = [];

        foreach ($values as $key => $value) {
            $definition = static::defaults()[$key] ?? [
                'type' => is_array($value) ? 'array' : 'string',
                'group' => 'general',
                'is_public' => false,
            ];

            $setting = static::query()->firstOrNew(['key' => $key]);
            $settingExists = $setting->exists;
            $oldValue = $setting->exists
                ? static::decodeValue($setting->value, $setting->type)
                : ($definition['value'] ?? null);

            if ($settingExists && $oldValue === $value) {
                continue;
            }

            $setting->fill([
                'value' => static::encodeValue($value, $definition['type']),
                'type' => $definition['type'],
                'group' => $definition['group'],
                'is_public' => $definition['is_public'],
            ])->save();

            if ($oldValue !== $value) {
                $changes[$key] = [
                    'old' => $oldValue,
                    'new' => $value,
                ];
            }
        }

        return $changes;
    }

    public static function encodeValue(mixed $value, string $type): ?string
    {
        if ($value === null) {
            return null;
        }

        if (in_array($type, ['array', 'json'], true)) {
            return json_encode($value, JSON_THROW_ON_ERROR);
        }

        if ($type === 'boolean') {
            return $value ? '1' : '0';
        }

        return (string) $value;
    }

    public static function decodeValue(?string $value, string $type): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'array', 'json' => json_decode($value, true, 512, JSON_THROW_ON_ERROR),
            'integer' => (int) $value,
            'float' => (float) $value,
            'boolean' => $value === '1',
            default => $value,
        };
    }
}
