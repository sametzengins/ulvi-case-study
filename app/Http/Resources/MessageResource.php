<?php

namespace App\Http\Resources;

use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Message
 */
class MessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'role' => $this->role->value,
            'content' => $this->content,
            'status' => $this->status->value,
            'client_message_id' => $this->client_message_id,
            'reply_to_id' => $this->reply_to_id,
            'input_tokens' => $this->input_tokens,
            'output_tokens' => $this->output_tokens,
            'failure_reason' => $this->failure_reason,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
