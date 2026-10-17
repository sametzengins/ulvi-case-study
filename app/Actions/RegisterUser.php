<?php

namespace App\Actions;

use App\Enums\LedgerEntryType;
use App\Models\User;
use App\Wallet\TokenWallet;
use Illuminate\Support\Facades\DB;

final class RegisterUser
{
    public function __construct(private readonly TokenWallet $wallet) {}

    public function handle(string $name, string $email, string $password): User
    {
        return DB::transaction(function () use ($name, $email, $password) {
            $user = User::query()->create([
                'name' => $name,
                'email' => $email,
                'password' => $password,
            ]);

            $user->wallet()->create();

            $this->wallet->credit($user, (int) config('ulvi.signup_tokens'), LedgerEntryType::SignupBonus, "signup:{$user->id}");

            return $user;
        });
    }
}
