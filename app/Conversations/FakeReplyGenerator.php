<?php

namespace App\Conversations;

use App\Models\Message;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Replies without an API key.
 *
 *   #flaky  the first attempt fails, the retry succeeds
 *   #fail   every attempt fails and the tokens are refunded
 */
final class FakeReplyGenerator implements ReplyGenerator
{
    public function __construct(private readonly int $latencyMs) {}

    public function generate(Message $message): GeneratedReply
    {
        if (str_contains($message->content, '#fail')) {
            throw new RuntimeException('Simulated provider outage (#fail).');
        }

        if (str_contains($message->content, '#flaky') && Cache::add("fake-reply:flaky:{$message->id}", true, 600)) {
            throw new RuntimeException('Simulated transient error (#flaky). The retry will succeed.');
        }

        if ($this->latencyMs > 0) {
            usleep($this->latencyMs * 1000);
        }

        $character = $message->conversation->character;
        $text = $this->replyAs($character->slug, Str::limit(trim($message->content), 80));

        $promptCharacters = mb_strlen($character->system_prompt);

        foreach (ConversationHistory::forPrompt($message) as $turn) {
            $promptCharacters += mb_strlen($turn['content']);
        }

        return new GeneratedReply(
            text: $text,
            inputTokens: TokenEstimator::tokensFor($promptCharacters),
            outputTokens: TokenEstimator::tokensFor(mb_strlen($text)),
        );
    }

    private function replyAs(string $slug, string $quote): string
    {
        return match ($slug) {
            'sera' => "There's no rush. You said \"{$quote}\". Before we look for an answer, notice where your body touches the chair. Stay with that for three breaths, then tell me what changed.",
            'mira' => "\"{$quote}\" Okay, that's a lot of drama for one sentence, and I mean that kindly. What's the version of this you'd laugh about in a month?",
            'atlas' => "Let's map it. You said \"{$quote}\". Three columns: what you know, what you're guessing, what you can't control. What goes in the first one?",
            'kai' => "\"{$quote}\". You already know what you're going to do. What's actually stopping you from doing it today?",
            default => "I hear you: \"{$quote}\". Tell me more.",
        };
    }
}
