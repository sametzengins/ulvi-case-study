<?php

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

// Only the owner of a conversation may listen to it.
Broadcast::channel('conversation.{conversationId}', function (User $user, int $conversationId) {
    return Conversation::query()->whereKey($conversationId)->where('user_id', $user->id)->exists();
});
