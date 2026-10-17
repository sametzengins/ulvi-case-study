<?php

namespace App\Models;

use App\Enums\MessageRole;
use App\Enums\MessageStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $conversation_id
 * @property int $user_id
 * @property MessageRole $role
 * @property string $content
 * @property MessageStatus $status
 * @property string|null $client_message_id
 * @property int|null $reply_to_id
 * @property int $reserved_tokens
 * @property int|null $input_tokens
 * @property int|null $output_tokens
 * @property string|null $failure_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'conversation_id', 'user_id', 'role', 'content', 'status', 'client_message_id',
    'reply_to_id', 'reserved_tokens', 'input_tokens', 'output_tokens', 'failure_reason',
])]
class Message extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => MessageRole::class,
            'status' => MessageStatus::class,
            'reserved_tokens' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Conversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
