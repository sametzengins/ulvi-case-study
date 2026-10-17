<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Only App\Wallet\TokenWallet changes the balance.
 *
 * @property int $id
 * @property int $user_id
 * @property int $balance
 */
#[Fillable(['user_id', 'balance'])]
class Wallet extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'balance' => 'integer',
        ];
    }
}
