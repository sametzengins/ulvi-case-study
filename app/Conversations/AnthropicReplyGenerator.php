<?php

namespace App\Conversations;

use Anthropic\Client;
use Anthropic\Core\Exceptions\AuthenticationException;
use Anthropic\Core\Exceptions\BadRequestException;
use Anthropic\Core\Exceptions\NotFoundException;
use Anthropic\Core\Exceptions\PermissionDeniedException;
use Anthropic\Core\Exceptions\UnprocessableEntityException;
use Anthropic\Messages\TextBlock;
use App\Models\Message;

/**
 * Replies from Claude. The SDK does not retry (see AppServiceProvider). The queue does.
 */
final class AnthropicReplyGenerator implements ReplyGenerator
{
    public function __construct(
        private readonly Client $client,
        private readonly string $model,
        private readonly int $maxOutputTokens,
    ) {}

    public function generate(Message $message): GeneratedReply
    {
        try {
            $response = $this->client->messages->create(
                maxTokens: $this->maxOutputTokens,
                messages: ConversationHistory::forPrompt($message),
                model: $this->model,
                system: $message->conversation->character->system_prompt,
            );
        } catch (BadRequestException|AuthenticationException|PermissionDeniedException|NotFoundException|UnprocessableEntityException $e) {
            // Retrying these will not help. Other errors go to the queue and are retried.
            throw new ReplyFailedPermanently($e->getMessage(), previous: $e);
        }

        if ($response->stopReason === 'refusal') {
            throw new ReplyFailedPermanently('The model declined to answer.');
        }

        $text = '';

        foreach ($response->content as $block) {
            if ($block instanceof TextBlock) {
                $text .= $block->text;
            }
        }

        return new GeneratedReply(
            text: $text,
            inputTokens: $response->usage->inputTokens,
            outputTokens: $response->usage->outputTokens,
        );
    }
}
