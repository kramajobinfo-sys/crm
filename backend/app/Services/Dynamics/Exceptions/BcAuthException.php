<?php
namespace App\Services\Dynamics\Exceptions;
use RuntimeException;

/** Token acquisition failed: bad credentials, wrong tenant, or consent not granted. */
class BcAuthException extends RuntimeException
{
    public function __construct(string $message, public readonly int $status = 401)
    {
        parent::__construct($message, $status);
    }
}
