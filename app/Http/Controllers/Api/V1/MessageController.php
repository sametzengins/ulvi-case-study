<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\SendMessage;
use App\Http\Controllers\Controller;
use App\Http\Requests\SendMessageRequest;
use App\Http\Resources\MessageResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MessageController extends Controller
{
    public function index(Request $request, int $conversation): AnonymousResourceCollection
    {
        $messages = $request->user()->conversations()->findOrFail($conversation)
            ->messages()
            ->orderByDesc('id')
            ->cursorPaginate(30);

        return MessageResource::collection($messages);
    }

    /**
     * 202: the reply arrives later as agent.responded on conversation.{id}.
     */
    public function store(SendMessageRequest $request, SendMessage $sendMessage, int $conversation): JsonResponse
    {
        $conversation = $request->user()->conversations()->with('character')->findOrFail($conversation);

        [$message, $created] = $sendMessage->handle(
            $request->user(),
            $conversation,
            $request->string('message')->toString(),
            $request->string('client_message_id')->toString(),
        );

        return MessageResource::make($message)
            ->response()
            ->setStatusCode(202)
            ->header('Idempotent-Replayed', $created ? 'false' : 'true');
    }
}
