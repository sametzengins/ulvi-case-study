<?php

namespace App\Wallet;

use RuntimeException;

final class InsufficientTokens extends RuntimeException
{
    public function __construct(
        public readonly int $needed,
        public readonly int $available,
    ) {
        parent::__construct("Needed {$needed} tokens, {$available} available.");
    }
}
