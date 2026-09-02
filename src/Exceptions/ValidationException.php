<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class ValidationException extends RuntimeException
{
    /**
     * @param array<string, string|array<string>> $errors
     */
    public function __construct(
        private array $errors,
        string $message = 'Validation failed.'
    ) {
        parent::__construct($message, 422);
    }

    /**
     * @return array<string, string|array<string>>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
