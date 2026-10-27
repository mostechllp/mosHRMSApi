<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActivitySetting extends Model
{
    use HasFactory;

    protected $table = 'activity_settings';

    public const DEFAULTS = [
        'default_duration_minutes' => '60',
    ];

    protected $fillable = [
        'key',
        'value',
    ];

    public static function get(string $key, ?string $default = null): ?string
    {
        $setting = static::where('key', $key)->first();
        if ($setting) {
            return $setting->value;
        }

        return static::DEFAULTS[$key] ?? $default;
    }

    public static function set(string $key, ?string $value): static
    {
        return static::updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
