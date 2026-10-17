<?php

use App\Conversations\GeneratedReply;
use App\Conversations\ReplyFailedPermanently;
use App\Conversations\ReplyGenerator;
use App\Enums\LedgerEntryType;
use App\Enums\MessageRole;
use App\Enums\MessageStatus;
use App\Events\AgentFailed;
use App\Events\AgentResponded;
use App\Jobs\GenerateReply;
use App\Models\LedgerEntry;
use App\Models\Message;
use App\Models\User;
use App\Wallet\TokenWallet;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    Event::fake([AgentResponded::class, AgentFailed::class]);
});

/**
 * A user message with its tokens reserved, exactly as SendMessage leaves it.
 */
function heldMessage(User $user, string $content = 'Hello', int $reserve = 5_000): Message
{
    $message = pendingMessage($user, $content);
    app(TokenWallet::class)->debit($user, $reserve, LedgerEntryType::MessageHold, "message:{$message->id}:hold");
    $message->update(['reserved_tokens' => $reserve]);

    return $message;
}

function refunds(): int
{
    return LedgerEntry::query()->where('type', LedgerEntryType::MessageRefund)->count();
}

/**
 * One attempt by a queue worker. Exceptions surface the way the worker sees them.
 */
function attempt(Message $message): void
{
    app()->call([new GenerateReply($message), 'handle']);
}

function replyWith(string $text, int $input, int $output): void
{
    app()->instance(ReplyGenerator::class, new class($text, $input, $output) implements ReplyGenerator
    {
        public function __construct(private string $text, private int $input, private int $output) {}

        public function generate(Message $message): GeneratedReply
        {
            return new GeneratedReply($this->text, $this->input, $this->output);
        }
    });
}

it('saves the reply, charges what it used and tells the client', function () {
    $user = userWithTokens(10_000);
    $message = heldMessage($user, reserve: 5_000);
    replyWith('Breathe in for four.', input: 900, output: 300);

    attempt($message);

    $reply = Message::query()->where('role', MessageRole::Assistant)->sole();

    expect($message->fresh()->status)->toBe(MessageStatus::Completed)
        ->and($reply->reply_to_id)->toBe($message->id)
        ->and($reply->content)->toBe('Breathe in for four.')
        ->and(LedgerEntry::query()->where('type', LedgerEntryType::MessageRefund)->sole()->amount)->toBe(5_000 - 1_200)
        ->and(balance($user))->toBe(10_000 - 1_200);

    Event::assertDispatched(AgentResponded::class, fn (AgentResponded $event) => $event->reply->is($reply));
});

it('replies once and refunds once when the job runs twice', function () {
    $user = userWithTokens(10_000);
    $message = heldMessage($user);
    replyWith('Once.', input: 100, output: 100);

    attempt($message);
    attempt($message);

    expect(Message::query()->where('role', MessageRole::Assistant)->count())->toBe(1)
        ->and(refunds())->toBe(1)
        ->and(balance($user))->toBe(10_000 - 200);

    Event::assertDispatchedTimes(AgentResponded::class, 1);
});

it('never charges more than it reserved', function () {
    $user = userWithTokens(10_000);
    $message = heldMessage($user, reserve: 400);
    replyWith('Long.', input: 900, output: 900);

    attempt($message);

    expect(refunds())->toBe(0)
        ->and(balance($user))->toBe(10_000 - 400);
});

it('keeps the tokens reserved while a transient failure is retried, then completes', function () {
    $user = userWithTokens(10_000);
    $message = heldMessage($user, '#flaky please');

    expect(fn () => attempt($message))->toThrow(RuntimeException::class, '#flaky');
    expect($message->fresh()->status)->toBe(MessageStatus::Processing)
        ->and(refunds())->toBe(0)
        ->and(balance($user))->toBe(10_000 - 5_000);

    attempt($message);   // the worker's second attempt

    expect($message->fresh()->status)->toBe(MessageStatus::Completed)
        ->and(refunds())->toBe(1);
});

it('gives every token back when the reply fails for good', function () {
    $user = userWithTokens(10_000);
    $message = heldMessage($user, '#fail');
    $job = new GenerateReply($message);

    foreach (range(1, $job->tries) as $ignored) {
        expect(fn () => attempt($message))->toThrow(RuntimeException::class);
    }

    $job->failed(new RuntimeException('Simulated provider outage (#fail).'));   // what the worker calls after the last attempt

    expect($message->fresh()->status)->toBe(MessageStatus::Failed)
        ->and($message->fresh()->failure_reason)->toContain('outage')
        ->and(refunds())->toBe(1)
        ->and(balance($user))->toBe(10_000);

    Event::assertDispatched(AgentFailed::class);
});

it('does not retry what cannot succeed', function () {
    $user = userWithTokens(10_000);
    $message = heldMessage($user);

    app()->instance(ReplyGenerator::class, new class implements ReplyGenerator
    {
        public function generate(Message $message): GeneratedReply
        {
            throw new ReplyFailedPermanently('The model declined to answer.');
        }
    });

    // Through the sync queue, so $this->fail() reaches a real job instance.
    GenerateReply::dispatchSync($message);

    expect($message->fresh()->status)->toBe(MessageStatus::Failed)
        ->and(balance($user))->toBe(10_000);
});

it('ignores a late reply for a message that has already been given up on', function () {
    $user = userWithTokens(10_000);
    $message = heldMessage($user);
    replyWith('Too late.', input: 10, output: 10);

    (new GenerateReply($message))->failed(new RuntimeException('timed out'));
    attempt($message);

    expect(Message::query()->where('role', MessageRole::Assistant)->count())->toBe(0)
        ->and($message->fresh()->status)->toBe(MessageStatus::Failed)
        ->and(refunds())->toBe(1)
        ->and(balance($user))->toBe(10_000);
});

it('refunds messages whose job was lost', function () {
    config(['ulvi.messages.stale_after_minutes' => 15]);
    $user = userWithTokens(10_000);
    $lost = heldMessage($user);
    $lost->forceFill(['created_at' => now()->subMinutes(16)])->save();
    $recent = heldMessage($user);

    $this->artisan('messages:fail-stale')->assertSuccessful();

    expect($lost->fresh()->status)->toBe(MessageStatus::Failed)
        ->and($recent->fresh()->status)->toBe(MessageStatus::Queued)
        ->and(balance($user))->toBe(10_000 - 5_000);
});
