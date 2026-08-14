<?php

namespace App\Exceptions;

use RuntimeException;

class ApiProblemException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $errorCode,
        public readonly int $httpStatus = 422,
        public readonly array $errors = [],
    ) {
        parent::__construct($message);
    }
}
