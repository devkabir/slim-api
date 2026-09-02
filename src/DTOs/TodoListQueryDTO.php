<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Exceptions\ValidationException;

readonly class TodoListQueryDTO
{
    public const DEFAULT_PAGE    = 1;
    public const DEFAULT_LIMIT   = 20;
    public const HARD_MAX_LIMIT  = 100;

    public function __construct(
        public ?bool $completed = null,
        public int $page = self::DEFAULT_PAGE,
        public int $limit = self::DEFAULT_LIMIT
    ) {
    }

    /**
     * @param array<string, mixed> $queryParams
     * @throws ValidationException
     */
    public static function fromQueryParams(array $queryParams): self
    {
        $errors = [];

        // 1. Validate 'completed' filter
        $completed = null;
        if (isset($queryParams['completed'])) {
            $val = strtolower(trim((string)$queryParams['completed']));
            if (in_array($val, ['1', 'true'], true)) {
                $completed = true;
            } elseif (in_array($val, ['0', 'false'], true)) {
                $completed = false;
            } else {
                $errors['completed'] = 'Filter "completed" must be one of: 1, 0, true, false.';
            }
        }

        // 2. Validate 'page' (positive integer >= 1)
        $page = self::DEFAULT_PAGE;
        if (isset($queryParams['page'])) {
            $rawPage = filter_var($queryParams['page'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($rawPage === false || $rawPage === null) {
                $errors['page'] = 'Query parameter "page" must be a positive integer (minimum 1).';
            } else {
                $page = (int)$rawPage;
            }
        }

        // 3. Validate 'limit' / 'per_page' with hard maximum limit
        $limit         = self::DEFAULT_LIMIT;
        $rawLimitParam = $queryParams['limit'] ?? $queryParams['per_page'] ?? null;
        if ($rawLimitParam !== null) {
            $rawLimit = filter_var($rawLimitParam, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($rawLimit === false || $rawLimit === null) {
                $errors['limit'] = sprintf('Query parameter "limit" must be a positive integer between 1 and %d.', self::HARD_MAX_LIMIT);
            } elseif ((int)$rawLimit > self::HARD_MAX_LIMIT) {
                $errors['limit'] = sprintf('Page size limit exceeds hard maximum of %d items per page.', self::HARD_MAX_LIMIT);
            } else {
                $limit = (int)$rawLimit;
            }
        }

        if ( ! empty($errors)) {
            throw new ValidationException($errors);
        }

        return new self(
            completed: $completed,
            page: $page,
            limit: $limit
        );
    }
}
