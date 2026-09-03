<?php
namespace App\Services\Dynamics\Exceptions;
use RuntimeException;

/** A Business Central API call returned a non-2xx response. */
class BcRequestException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $status = 500,
        /** True for HTTP 412 — an optimistic-concurrency conflict, not a transient failure. */
        public readonly bool $isConflict = false,
    ) {
        parent::__construct($message, $status);
    }
}
