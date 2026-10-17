<?php

use App\Enums\LedgerEntryType;
use App\Models\LedgerEntry;
use App\Models\User;

describe('accounts', function () {
    it('registers a user with a wallet and the sign-up tokens, and returns a working token', function () {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Ayşe',
            'email' => 'ayse@example.com',
            'password' => 'correct horse',
        ])->assertCreated();

        $user = User::query()->where('email', 'ayse@example.com')->sole();

        expect(balance($user))->toBe(100_000)
            ->and(LedgerEntry::query()->where('user_id', $user->id)->sole()->type)->toBe(LedgerEntryType::SignupBonus);

        $this->withToken($response->json('data.token'))->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'ayse@example.com');
    });

    it('answers 401 with a code, not a redirect, when the token is missing', function () {
        $this->getJson('/api/v1/me')->assertStatus(401)->assertJsonPath('code', 'unauthenticated');
    });

    it('does not say which half of the credentials was wrong', function () {
        User::factory()->create(['email' => 'kai@example.com']);

        $this->postJson('/api/v1/auth/login', ['email' => 'kai@example.com', 'password' => 'nope'])
            ->assertStatus(401)
            ->assertJsonPath('code', 'invalid_credentials');
    });
});
