<?php

namespace App\Enums;

enum MessageStatus: string
{
    case Queued = 'queued';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';

    /**
     * A final message never changes again.
     */
    public function isFinal(): bool
    {
        return $this === self::Completed || $this === self::Failed;
    }

    /**
     * @return list<string>
     */
    public static function pendingValues(): array
    {
        return [self::Queued->value, self::Processing->value];
    }
}
