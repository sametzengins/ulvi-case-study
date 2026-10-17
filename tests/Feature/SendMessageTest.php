<?php

use App\Enums\LedgerEntryType;
use App\Enums\MessageStatus;
use App\Jobs\GenerateReply;
use App\Models\LedgerEntry;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

beforeEach(function () {
    Queue::fake();
});

function holds(): int
{
    return LedgerEntry::query()->where('type', LedgerEntryType::MessageHold)->count();
}

function sendMessage(User $user, int $conversationId, string $text = 'I cannot sleep', ?string $clientMessageId = null)
{
    return test()->actingAs($user)->postJson("/api/v1/conversations/{$conversationId}/messages", [
        'message' => $text,
        'client_message_id' => $clientMessageId ?? (string) Str::uuid(),
    ]);
}

it('answers "queued", reserves tokens and leaves the reply to a background job', function () {
    $user = userWithTokens(100_000);
    $conversation = conversationFor($user);

    $response = sendMessage($user, $conversation->id);

    $response->assertStatus(202)
        ->assertHeader('Idempotent-Replayed', 'false')
        ->assertJsonPath('data.status', 'queued')
        ->assertJsonPath('data.role', 'user');

    $message = Message::query()->sole();
    $hold = LedgerEntry::query()->where('type', LedgerEntryType::MessageHold)->sole();

    expect($message->reserved_tokens)->toBeGreaterThan(0)
        ->and($hold->amount)->toBe(-$message->reserved_tokens)
        ->and($hold->idempotency_key)->toBe("message:{$message->id}:hold")
        ->and(balance($user))->toBe(100_000 - $message->reserved_tokens);

    Queue::assertPushed(GenerateReply::class, fn (GenerateReply $job) => $job->message->is($message));
});

it('stores a message once when the same send arrives twice', function () {
    $user = userWithTokens(100_000);
    $conversation = conversationFor($user);
    $clientMessageId = (string) Str::uuid();

    $first = sendMessage($user, $conversation->id, clientMessageId: $clientMessageId);
    $second = sendMessage($user, $conversation->id, clientMessageId: $clientMessageId);

    $second->assertStatus(202)->assertHeader('Idempotent-Replayed', 'true');

    expect($second->json('data.id'))->toBe($first->json('data.id'))
        ->and(Message::query()->count())->toBe(1)
        ->and(holds())->toBe(1);

    Queue::assertPushed(GenerateReply::class, 1);
});

it('accepts nothing when the user is out of tokens', function () {
    $user = userWithTokens(100);
    $conversation = conversationFor($user);

    sendMessage($user, $conversation->id)
        ->assertStatus(402)
        ->assertJsonPath('code', 'quota_exhausted')
        ->assertJsonPath('meta.available', 100);

    expect(Message::query()->count())->toBe(0)
        ->and(holds())->toBe(0)
        ->and(balance($user))->toBe(100);

    Queue::assertNothingPushed();
});

it('does not let anyone post into another user\'s conversation', function () {
    $owner = userWithTokens();
    $stranger = userWithTokens();

    sendMessage($stranger, conversationFor($owner)->id)
        ->assertStatus(404)
        ->assertJsonPath('code', 'not_found');
});

it('validates the request and explains what is wrong', function () {
    $user = userWithTokens();

    $this->actingAs($user)
        ->postJson('/api/v1/conversations/'.conversationFor($user)->id.'/messages', ['message' => ''])
        ->assertStatus(422)
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonValidationErrors(['message', 'client_message_id']);
});

it('rate limits sending per user', function () {
    $user = userWithTokens(10_000_000);
    $conversation = conversationFor($user);

    foreach (range(1, 20) as $ignored) {
        sendMessage($user, $conversation->id)->assertStatus(202);
    }

    sendMessage($user, $conversation->id)
        ->assertStatus(429)
        ->assertJsonPath('code', 'rate_limited')
        ->assertHeader('Retry-After');
});

it('pages through history with a cursor, newest first', function () {
    $user = userWithTokens();
    $conversation = conversationFor($user);

    foreach (range(1, 35) as $i) {
        pendingMessage($user, "message {$i}", $conversation)->update(['status' => MessageStatus::Completed]);
    }

    $first = $this->actingAs($user)->getJson("/api/v1/conversations/{$conversation->id}/messages")->assertOk();
    expect($first->json('data'))->toHaveCount(30)
        ->and($first->json('data.0.content'))->toBe('message 35');

    $second = $this->actingAs($user)->getJson("/api/v1/conversations/{$conversation->id}/messages?cursor={$first->json('meta.next_cursor')}")->assertOk();
    expect($second->json('data'))->toHaveCount(5)
        ->and($second->json('data.4.content'))->toBe('message 1')
        ->and($second->json('meta.next_cursor'))->toBeNull();
});
