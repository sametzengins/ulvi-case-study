<?php

use App\Enums\LedgerEntryType;
use App\Enums\MessageRole;
use App\Enums\MessageStatus;
use App\Models\Character;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Wallet\TokenWallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

/**
 * A user whose wallet holds exactly this many tokens.
 */
function userWithTokens(int $tokens = 100_000): User
{
    $user = User::factory()->create();

    app(TokenWallet::class)->credit($user, $tokens, LedgerEntryType::Adjustment, "test:seed:{$user->id}");

    return $user;
}

function character(string $slug = 'sera'): Character
{
    return Character::query()->where('slug', $slug)->first()
        ?? Character::factory()->create(['slug' => $slug, 'name' => ucfirst($slug)]);
}

function conversationFor(User $user, string $slug = 'sera'): Conversation
{
    return $user->conversations()->create(['character_id' => character($slug)->id]);
}

/**
 * A user message waiting for its reply.
 */
function pendingMessage(User $user, string $content = 'Hello', ?Conversation $conversation = null): Message
{
    $conversation ??= conversationFor($user);

    return $conversation->messages()->create([
        'user_id' => $user->id,
        'role' => MessageRole::User,
        'content' => $content,
        'status' => MessageStatus::Queued,
        'client_message_id' => (string) Str::uuid(),
    ]);
}

function balance(User $user): int
{
    return (int) $user->wallet()->value('balance');
}
