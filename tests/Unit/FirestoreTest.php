<?php

namespace Tests\Unit;

use App\Services\Firebase\Firestore;
use App\Services\SyncService;
use PHPUnit\Framework\TestCase;

class FirestoreTest extends TestCase
{
    public function test_values_round_trip(): void
    {
        $data = [
            'name' => 'محمد',
            'active' => true,
            'count' => 3,
            'total' => 12.5,
            'none' => null,
            'devices' => [['id' => 'abc', 'name' => 'Pixel']],
            'empty' => [],
        ];

        $encoded = json_decode(json_encode(Firestore::encodeFields($data)), true);
        $this->assertSame(['stringValue' => 'محمد'], $encoded['name']);
        $this->assertSame(['integerValue' => '3'], $encoded['count']);
        $this->assertSame([], Firestore::decode($encoded['empty']));
        $this->assertSame($data['devices'], Firestore::decode($encoded['devices']));
        $this->assertSame(12.5, Firestore::decode($encoded['total']));
    }

    public function test_timestamps_are_normalised_for_string_comparison(): void
    {
        $a = SyncService::normalizeTimestamp('2026-10-20T10:00:00Z');
        $b = SyncService::normalizeTimestamp('2026-10-20T10:00:00.5Z');
        $c = SyncService::normalizeTimestamp('2026-10-20T10:00:00.123456789Z');

        $this->assertSame('2026-10-20T10:00:00.000000000Z', $a);
        $this->assertTrue($a < $c && $c < $b);
    }
}
