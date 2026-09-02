<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Todo;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

final readonly class DoctrineTodoRepository implements TodoRepositoryInterface
{
    public function __construct(
        private Connection $connection,
        private ?LoggerInterface $logger = null
    ) {
    }

    /**
     * @return Todo[]
     */
    public function findAll(?bool $completed = null, int $limit = 20, int $offset = 0): array
    {
        try {
            $queryBuilder = $this->connection->createQueryBuilder()
                ->select('*')
                ->from('todos');

            if ($completed !== null) {
                $queryBuilder->where('completed = :completed')
                    ->setParameter('completed', $completed ? 1 : 0, ParameterType::INTEGER);
            }

            $queryBuilder->orderBy('created_at', 'DESC')
                ->setMaxResults($limit)
                ->setFirstResult($offset);

            $rows = $queryBuilder->executeQuery()->fetchAllAssociative();

            return array_map(fn(array $row) => Todo::fromArray($row), $rows);
        } catch (Throwable $e) {
            $this->logger?->error('Doctrine query failed in findAll', [
                'error' => $e->getMessage(),
                'code'  => $e->getCode(),
            ]);
            throw $e;
        }
    }

    public function countAll(?bool $completed = null): int
    {
        try {
            $queryBuilder = $this->connection->createQueryBuilder()
                ->select('COUNT(*)')
                ->from('todos');

            if ($completed !== null) {
                $queryBuilder->where('completed = :completed')
                    ->setParameter('completed', $completed ? 1 : 0, ParameterType::INTEGER);
            }

            return (int)$queryBuilder->executeQuery()->fetchOne();
        } catch (Throwable $e) {
            $this->logger?->error('Doctrine query failed in countAll', [
                'error' => $e->getMessage(),
                'code'  => $e->getCode(),
            ]);
            throw $e;
        }
    }

    public function findById(int $id): ?Todo
    {
        try {
            $row = $this->connection->createQueryBuilder()
                ->select('*')
                ->from('todos')
                ->where('id = :id')
                ->setParameter('id', $id, ParameterType::INTEGER)
                ->setMaxResults(1)
                ->executeQuery()
                ->fetchAssociative();

            if (! $row) {
                return null;
            }

            return Todo::fromArray($row);
        } catch (Throwable $e) {
            $this->logger?->error('Doctrine query failed in findById', [
                'id'    => $id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function create(string $title, ?string $description = null, bool $completed = false): Todo
    {
        try {
            $this->connection->insert('todos', [
                'title'       => $title,
                'description' => $description,
                'completed'   => $completed ? 1 : 0,
            ], [
                'title'       => ParameterType::STRING,
                'description' => $description === null ? ParameterType::NULL : ParameterType::STRING,
                'completed'   => ParameterType::INTEGER,
            ]);

            $id   = (int)$this->connection->lastInsertId();
            $todo = $this->findById($id);

            if ($todo === null) {
                throw new RuntimeException('Created todo could not be loaded from database.');
            }

            return $todo;
        } catch (Throwable $e) {
            $this->logger?->error('Doctrine query failed in create', [
                'title' => $title,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function update(int $id, array $data): ?Todo
    {
        $fields = [];
        $types  = [];

        if (array_key_exists('title', $data)) {
            $fields['title'] = (string)$data['title'];
            $types['title']  = ParameterType::STRING;
        }

        if (array_key_exists('description', $data)) {
            $fields['description'] = $data['description'] !== null ? (string)$data['description'] : null;
            $types['description']  = $data['description'] === null ? ParameterType::NULL : ParameterType::STRING;
        }

        if (array_key_exists('completed', $data)) {
            $fields['completed'] = ! empty($data['completed']) ? 1 : 0;
            $types['completed']  = ParameterType::INTEGER;
        }

        if (empty($fields)) {
            return $this->findById($id);
        }

        try {
            $this->connection->update(
                'todos',
                $fields,
                ['id' => $id],
                array_merge($types, ['id' => ParameterType::INTEGER])
            );

            return $this->findById($id);
        } catch (Throwable $e) {
            $this->logger?->error('Doctrine query failed in update', [
                'id'    => $id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function delete(int $id): bool
    {
        try {
            $affected = $this->connection->delete(
                'todos',
                ['id' => $id],
                ['id' => ParameterType::INTEGER]
            );

            return $affected > 0;
        } catch (Throwable $e) {
            $this->logger?->error('Doctrine query failed in delete', [
                'id'    => $id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
