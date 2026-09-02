<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

final class TodoNotFoundException extends RuntimeException
{
    public function __construct(int $id)
    {
        parent::__construct(sprintf('Todo with ID %d not found.', $id), 404);
    }
}
