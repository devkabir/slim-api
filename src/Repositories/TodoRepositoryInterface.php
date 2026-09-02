<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Todo;

interface TodoRepositoryInterface
{
    /**
     * @return Todo[]
     */
    public function findAll(?bool $completed = null, int $limit = 20, int $offset = 0): array;

    public function countAll(?bool $completed = null): int;

    public function findById(int $id): ?Todo;

    public function create(string $title, ?string $description = null, bool $completed = false): Todo;

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): ?Todo;

    public function delete(int $id): bool;
}
