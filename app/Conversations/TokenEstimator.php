<?php

namespace App\Conversations;

use App\Models\Message;

/**
 * How many tokens to reserve before the reply exists: the input (about 4 characters
 * per token) plus max_tokens.
 */
final class TokenEstimator
{
    public function estimate(Message $message): int
    {
        $characters = mb_strlen($message->conversation->character->system_prompt);

        foreach (ConversationHistory::forPrompt($message) as $turn) {
            $characters += mb_strlen($turn['content']);
        }

        return self::tokensFor($characters) + (int) config('ulvi.messages.max_output_tokens');
    }

    public static function tokensFor(int $characters): int
    {
        return (int) ceil($characters / 4);
    }
}
