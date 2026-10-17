<?php

use App\Enums\LedgerEntryType;
use App\Models\LedgerEntry;
use App\Models\User;
use Database\Seeders\CharacterSeeder;

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

describe('characters', function () {
    beforeEach(fn () => $this->seed(CharacterSeeder::class));

    it('lists the characters', function () {
        $this->actingAs(User::factory()->create())->getJson('/api/v1/characters')
            ->assertOk()
            ->assertJsonCount(4, 'data')
            ->assertJsonPath('data.0.slug', 'sera');
    });

    it('starts a conversation with a character that exists', function () {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/v1/conversations', ['character' => 'mira'])
            ->assertCreated()
            ->assertJsonPath('data.character.slug', 'mira');

        $this->actingAs($user)->postJson('/api/v1/conversations', ['character' => 'nobody'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'validation_failed');
    });

    it('does not show one user\'s messages to another', function () {
        $conversation = conversationFor(User::factory()->create(), 'mira');

        $this->actingAs(User::factory()->create())->getJson("/api/v1/conversations/{$conversation->id}/messages")
            ->assertStatus(404)
            ->assertJsonPath('code', 'not_found');
    });
});
