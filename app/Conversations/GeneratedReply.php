<?php

namespace App\Conversations;

final readonly class GeneratedReply
{
    public function __construct(
        public string $text,
        public int $inputTokens,
        public int $outputTokens,
    ) {}

    public function totalTokens(): int
    {
        return $this->inputTokens + $this->outputTokens;
    }
}
