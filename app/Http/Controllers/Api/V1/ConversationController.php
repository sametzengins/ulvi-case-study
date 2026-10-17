<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ConversationResource;
use App\Models\Character;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConversationController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'character' => ['required', 'string', 'exists:characters,slug'],
        ]);

        $character = Character::query()->where('slug', $validated['character'])->firstOrFail();
        $conversation = $request->user()->conversations()->create(['character_id' => $character->id]);

        return ConversationResource::make($conversation->setRelation('character', $character))
            ->response()
            ->setStatusCode(201);
    }
}
