<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Device-local key/value storage (JSON encoded values).
 *
 * Note: NativePHP Mobile keeps the PHP runtime alive between requests, so this class
 * deliberately keeps no static cache — every read goes to the local SQLite database.
 */
class AppState extends Model
{
    protected $table = 'app_state';

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    public static function get(string $key, mixed $default = null): mixed
    {
        $row = static::query()->find($key);
        $value = $row ? json_decode((string) $row->value, true) : null;

        return $value ?? $default;
    }

    public static function put(string $key, mixed $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => json_encode($value, JSON_UNESCAPED_UNICODE)]);
    }

    public static function forget(string ...$keys): void
    {
        static::query()->whereIn('key', $keys)->delete();
    }
}
