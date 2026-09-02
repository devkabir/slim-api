<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Exceptions\ValidationException;

readonly class TodoListQueryDTO
{
    public function __construct(
        public ?bool $completed = null
    ) {
    }

    /**
     * @param array<string, mixed> $queryParams
     */
    public static function fromQueryParams(array $queryParams): self
    {
        if ( ! isset($queryParams['completed'])) {
            return new self(null);
        }

        $val = strtolower(trim((string)$queryParams['completed']));

        if (in_array($val, ['1', 'true'], true)) {
            return new self(true);
        }

        if (in_array($val, ['0', 'false'], true)) {
            return new self(false);
        }

        throw new ValidationException([
            'completed' => 'Filter "completed" must be one of: 1, 0, true, false.'
        ]);
    }
}
