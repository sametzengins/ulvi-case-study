<?php

namespace App\Models;

use App\Enums\LedgerEntryType;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * @property int $id
 * @property int $user_id
 * @property LedgerEntryType $type
 * @property int $amount
 * @property int $balance_before
 * @property int $balance_after
 * @property string $idempotency_key
 * @property Carbon $created_at
 */
#[Fillable(['user_id', 'type', 'amount', 'balance_before', 'balance_after', 'idempotency_key'])]
class LedgerEntry extends Model
{
    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        // The database trigger also refuses this. This fails earlier in PHP.
        static::updating(fn () => throw new LogicException('Ledger entries are append-only.'));
        static::deleting(fn () => throw new LogicException('Ledger entries are append-only.'));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => LedgerEntryType::class,
            'amount' => 'integer',
            'balance_before' => 'integer',
            'balance_after' => 'integer',
        ];
    }
}
