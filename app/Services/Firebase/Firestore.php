<?php

namespace App\Services\Firebase;

use DateTimeInterface;

/**
 * Helpers to convert between plain PHP values and Firestore REST "Value" objects.
 */
class Firestore
{
    public static function encode(mixed $value): array
    {
        return match (true) {
            $value === null => ['nullValue' => null],
            is_bool($value) => ['booleanValue' => $value],
            is_int($value) => ['integerValue' => (string) $value],
            is_float($value) => ['doubleValue' => $value],
            $value instanceof DateTimeInterface => ['timestampValue' => $value->format('Y-m-d\TH:i:s.u\Z')],
            is_array($value) && array_is_list($value) => ['arrayValue' => $value === [] ? new \stdClass : ['values' => array_map([self::class, 'encode'], $value)]],
            is_array($value) => ['mapValue' => ['fields' => self::encodeFields($value)]],
            default => ['stringValue' => (string) $value],
        };
    }

    public static function encodeFields(array $data): array|\stdClass
    {
        if ($data === []) {
            return new \stdClass;
        }

        return array_map([self::class, 'encode'], $data);
    }

    public static function decode(array $value): mixed
    {
        $type = array_key_first($value);
        $v = $value[$type] ?? null;

        return match ($type) {
            'nullValue' => null,
            'booleanValue' => (bool) $v,
            'integerValue' => (int) $v,
            'doubleValue' => (float) $v,
            'timestampValue', 'stringValue', 'referenceValue', 'bytesValue' => (string) $v,
            'arrayValue' => array_map([self::class, 'decode'], $v['values'] ?? []),
            'mapValue' => self::decodeFields($v['fields'] ?? []),
            'geoPointValue' => $v,
            default => $v,
        };
    }

    public static function decodeFields(array $fields): array
    {
        return array_map([self::class, 'decode'], $fields);
    }

    /** Decode a full Firestore document into ['id' => ..., ...fields, '_updateTime' => ...]. */
    public static function decodeDocument(array $doc): array
    {
        $data = self::decodeFields($doc['fields'] ?? []);
        $data['id'] = self::idFromName($doc['name'] ?? '');
        $data['_updateTime'] = $doc['updateTime'] ?? null;

        return $data;
    }

    public static function idFromName(string $name): string
    {
        $pos = strrpos($name, '/');

        return $pos === false ? $name : substr($name, $pos + 1);
    }
}
