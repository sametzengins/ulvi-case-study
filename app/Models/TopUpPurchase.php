<?php

namespace App\Models;

use App\Enums\TopUpStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $user_id
 * @property string $pack
 * @property int $tokens
 * @property int $amount minor units
 * @property string $currency
 * @property string|null $checkout_session_id
 * @property TopUpStatus $status
 * @property Carbon|null $completed_at
 */
#[Fillable(['user_id', 'pack', 'tokens', 'amount', 'currency', 'checkout_session_id', 'status', 'completed_at'])]
class TopUpPurchase extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TopUpStatus::class,
            'tokens' => 'integer',
            'amount' => 'integer',
            'completed_at' => 'datetime',
        ];
    }
}
