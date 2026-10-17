<?php

namespace App\Conversations;

use RuntimeException;

/**
 * Retrying will not help (bad request, refusal, invalid credentials).
 */
final class ReplyFailedPermanently extends RuntimeException {}
