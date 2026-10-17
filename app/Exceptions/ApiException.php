<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * An error the client handles by its code: {"message": "...", "code": "quota_exhausted", "meta": {...}}
 */
class ApiException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public readonly string $errorCode,
        public readonly int $status,
        public readonly array $meta = [],
    ) {
        parent::__construct($errorCode);
    }

    public function render(): JsonResponse
    {
        $body = [
            'message' => __("api.errors.{$this->errorCode}"),
            'code' => $this->errorCode,
        ];

        if ($this->meta !== []) {
            $body['meta'] = $this->meta;
        }

        return response()->json($body, $this->status);
    }
}
