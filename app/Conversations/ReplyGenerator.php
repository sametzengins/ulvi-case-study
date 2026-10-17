<?php

namespace App\Conversations;

use App\Models\Message;

interface ReplyGenerator
{
    /**
     * Throw ReplyFailedPermanently when retrying cannot help. Any other exception is retried.
     */
    public function generate(Message $message): GeneratedReply;
}
