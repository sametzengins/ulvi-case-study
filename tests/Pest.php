<?php

use App\Billing\StripeEvents;
use App\Billing\StripeSignature;
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
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)->in('Concurrency');

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

/**
 * POST a Stripe event to the webhook endpoint, signed the way Stripe signs it.
 *
 * @param  array<string, mixed>  $event
 */
function deliverStripeEvent(array $event, ?int $timestamp = null, ?string $secret = null): TestResponse
{
    $payload = json_encode($event, JSON_THROW_ON_ERROR);
    $signature = StripeSignature::header($payload, $secret ?? (string) config('ulvi.stripe.webhook_secret'), $timestamp);

    return test()->call('POST', '/api/webhooks/stripe', server: [
        'HTTP_STRIPE_SIGNATURE' => $signature,
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
    ], content: $payload);
}

/**
 * @param  array<string, mixed>  $object
 * @return array<string, mixed>
 */
function stripeEvent(string $type, array $object, ?string $id = null): array
{
    return StripeEvents::make($type, $object, $id);
}
