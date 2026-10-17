<?php

namespace App\Conversations;

use App\Enums\MessageRole;
use App\Enums\MessageStatus;
use App\Models\Message;
use Illuminate\Database\Eloquent\Builder;

final class ConversationHistory
{
    /**
     * Earlier answered turns, oldest first, then the message itself.
     *
     * @return list<array{role: string, content: string}>
     */
    public static function forPrompt(Message $message): array
    {
        $earlier = Message::query()
            ->where('conversation_id', $message->conversation_id)
            ->where('id', '<', $message->id)
            ->where(fn (Builder $query) => $query
                ->where('role', MessageRole::Assistant)
                ->orWhere('status', MessageStatus::Completed))
            ->orderByDesc('id')
            ->limit((int) config('ulvi.messages.history_limit'))
            ->get(['role', 'content'])
            ->reverse()
            ->values();

        // The first turn sent to the model has to be the user's.
        while ($earlier->isNotEmpty() && $earlier->first()->role === MessageRole::Assistant) {
            $earlier->shift();
        }

        $turns = $earlier
            ->map(fn (Message $turn) => ['role' => $turn->role->value, 'content' => $turn->content])
            ->all();

        $turns[] = ['role' => MessageRole::User->value, 'content' => $message->content];

        return array_values($turns);
    }
}
