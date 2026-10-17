<?php

namespace App\Http\Resources;

use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Conversation
 */
class ConversationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'character' => [
                'slug' => $this->character->slug,
                'name' => $this->character->name,
            ],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
