<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * A venue reservation ("event").
 *
 * @property string $id
 * @property string $groom_name
 * @property \Illuminate\Support\Carbon $date
 * @property string $start_time
 * @property string|null $end_time
 * @property string|null $dj_name
 * @property string|null $dj_phone
 * @property string|null $phone
 * @property string $total_cost
 * @property string $paid_amount
 * @property string|null $notes
 * @property bool $deleted
 * @property string $sync_status
 */
class Event extends Model
{
    use HasUuids;

    public const SYNCED = 'synced';

    public const PENDING = 'pending';

    protected $fillable = [
        'id', 'groom_name', 'date', 'start_time', 'end_time', 'dj_name', 'dj_phone', 'phone',
        'total_cost', 'paid_amount', 'notes', 'created_by', 'created_by_name',
        'updated_by_name', 'deleted', 'sync_status', 'remote_updated_at',
    ];

    protected function casts(): array
    {
        return [
            'total_cost' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'deleted' => 'boolean',
        ];
    }

    /** Stored as plain "Y-m-d" so string range queries are exact. */
    protected function date(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? Carbon::createFromFormat('!Y-m-d', substr((string) $value, 0, 10)) : null,
            set: fn ($value) => $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : substr((string) $value, 0, 10),
        );
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('deleted', false);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('date')->orderBy('start_time');
    }

    public function remaining(): float
    {
        return max(0, (float) $this->total_cost - (float) $this->paid_amount);
    }

    public function isPaidInFull(): bool
    {
        return (float) $this->total_cost > 0 && $this->remaining() <= 0;
    }

    /**
     * Phone number in international form for WhatsApp links
     * (059 / 056 are Palestinian networks: +970, other 05x: +972).
     */
    public static function whatsappNumber(?string $phone): string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);

        return match (true) {
            str_starts_with($digits, '00') => substr($digits, 2),
            str_starts_with($digits, '059'), str_starts_with($digits, '056') => '970'.substr($digits, 1),
            str_starts_with($digits, '05') => '972'.substr($digits, 1),
            default => $digits,
        };
    }

    public function startsAt(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->date->format('Y-m-d').' '.$this->start_time);
    }

    /** Firestore document fields (typed values) for this event. */
    public function toFirestoreFields(): array
    {
        return [
            'groomName' => ['stringValue' => $this->groom_name],
            'date' => ['stringValue' => $this->date->format('Y-m-d')],
            'startTime' => ['stringValue' => $this->start_time],
            'endTime' => $this->end_time ? ['stringValue' => $this->end_time] : ['nullValue' => null],
            'djName' => ['stringValue' => (string) $this->dj_name],
            'djPhone' => ['stringValue' => (string) $this->dj_phone],
            'phone' => ['stringValue' => (string) $this->phone],
            'totalCost' => ['doubleValue' => (float) $this->total_cost],
            'paidAmount' => ['doubleValue' => (float) $this->paid_amount],
            'notes' => ['stringValue' => (string) $this->notes],
            'createdBy' => ['stringValue' => (string) $this->created_by],
            'createdByName' => ['stringValue' => (string) $this->created_by_name],
            'updatedByName' => ['stringValue' => (string) $this->updated_by_name],
            'deleted' => ['booleanValue' => (bool) $this->deleted],
        ];
    }

    /** Map a decoded Firestore document (plain PHP values) onto local attributes. */
    public static function attributesFromFirestore(array $data): array
    {
        return [
            'groom_name' => (string) ($data['groomName'] ?? ''),
            'date' => (string) ($data['date'] ?? now()->toDateString()),
            'start_time' => (string) ($data['startTime'] ?? '20:00'),
            'end_time' => ($data['endTime'] ?? null) ?: null,
            'dj_name' => ($data['djName'] ?? null) ?: null,
            'dj_phone' => ($data['djPhone'] ?? null) ?: null,
            'phone' => ($data['phone'] ?? null) ?: null,
            'total_cost' => (float) ($data['totalCost'] ?? 0),
            'paid_amount' => (float) ($data['paidAmount'] ?? 0),
            'notes' => ($data['notes'] ?? null) ?: null,
            'created_by' => ($data['createdBy'] ?? null) ?: null,
            'created_by_name' => ($data['createdByName'] ?? null) ?: null,
            'updated_by_name' => ($data['updatedByName'] ?? null) ?: null,
            'deleted' => (bool) ($data['deleted'] ?? false),
        ];
    }
}
