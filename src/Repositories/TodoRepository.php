<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Todo;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;

final readonly class TodoRepository implements TodoRepositoryInterface
{
    private DoctrineTodoRepository $inner;

    public function __construct(
        Connection $connection,
        ?LoggerInterface $logger = null
    ) {
        $this->inner = new DoctrineTodoRepository($connection, $logger);
    }

    /**
     * @return Todo[]
     */
    public function findAll(?bool $completed = null, int $limit = 20, int $offset = 0): array
    {
        return $this->inner->findAll($completed, $limit, $offset);
    }

    public function countAll(?bool $completed = null): int
    {
        return $this->inner->countAll($completed);
    }

    public function findById(int $id): ?Todo
    {
        return $this->inner->findById($id);
    }

    public function create(string $title, ?string $description = null, bool $completed = false): Todo
    {
        return $this->inner->create($title, $description, $completed);
    }

    public function update(int $id, array $data): ?Todo
    {
        return $this->inner->update($id, $data);
    }

    public function delete(int $id): bool
    {
        return $this->inner->delete($id);
    }
}
