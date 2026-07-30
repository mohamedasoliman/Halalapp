<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class AwqatUpstreamException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly bool $uncertain = false,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
