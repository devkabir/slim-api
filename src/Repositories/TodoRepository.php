<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;
use Throwable;
use App\Models\Todo;
use RuntimeException;
use Psr\Log\LoggerInterface;

class TodoRepository
{
    public function __construct(
        private PDO $db,
        private ?LoggerInterface $logger = null
    ) {
    }

    /**
     * @return Todo[]
     */
    public function findAll(?bool $completed = null, int $limit = 20, int $offset = 0): array
    {
        $sql    = "SELECT * FROM `todos`";
        $params = [];

        if ($completed !== null) {
            $sql                  .= " WHERE `completed` = :completed";
            $params[':completed'] = $completed ? 1 : 0;
        }

        $sql .= " ORDER BY `created_at` DESC LIMIT :limit OFFSET :offset";

        try {
            $stmt = $this->db->prepare($sql);
            foreach ($params as $key => $val) {
                $stmt->bindValue($key, $val, PDO::PARAM_INT);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll();

            return array_map(fn($row) => Todo::fromArray($row), $rows);
        } catch (Throwable $e) {
            $this->logger?->error('Database query failed in findAll', [
                'error' => $e->getMessage(),
                'code'  => $e->getCode(),
            ]);
            throw $e;
        }
    }

    public function countAll(?bool $completed = null): int
    {
        $sql    = "SELECT COUNT(*) FROM `todos`";
        $params = [];

        if ($completed !== null) {
            $sql                  .= " WHERE `completed` = :completed";
            $params[':completed'] = $completed ? 1 : 0;
        }

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            return (int)$stmt->fetchColumn();
        } catch (Throwable $e) {
            $this->logger?->error('Database query failed in countAll', [
                'error' => $e->getMessage(),
                'code'  => $e->getCode(),
            ]);
            throw $e;
        }
    }

    public function create(string $title, ?string $description = null, bool $completed = false): Todo
    {
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO `todos` (`title`, `description`, `completed`) VALUES (:title, :description, :completed)"
            );

            $stmt->execute([
                ':title'       => $title,
                ':description' => $description,
                ':completed'   => $completed ? 1 : 0,
            ]);

            $id   = (int)$this->db->lastInsertId();
            $todo = $this->findById($id);

            if ($todo === null) {
                throw new RuntimeException('Created todo could not be loaded from database.');
            }

            return $todo;
        } catch (Throwable $e) {
            $this->logger?->error('Database query failed in create', [
                'title' => $title,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function findById(int $id): ?Todo
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM `todos` WHERE `id` = :id LIMIT 1");
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch();

            if ( ! $row) {
                return null;
            }

            return Todo::fromArray($row);
        } catch (Throwable $e) {
            $this->logger?->error('Database query failed in findById', [
                'id'    => $id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function update(int $id, array $data): ?Todo
    {
        $fields = [];
        $params = [':id' => $id];

        if (array_key_exists('title', $data)) {
            $fields[]         = "`title` = :title";
            $params[':title'] = $data['title'];
        }

        if (array_key_exists('description', $data)) {
            $fields[]               = "`description` = :description";
            $params[':description'] = $data['description'];
        }

        if (array_key_exists('completed', $data)) {
            $fields[]             = "`completed` = :completed";
            $params[':completed'] = $data['completed'] ? 1 : 0;
        }

        if (empty($fields)) {
            return $this->findById($id);
        }

        try {
            $sql  = "UPDATE `todos` SET " . implode(', ', $fields) . " WHERE `id` = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            return $this->findById($id);
        } catch (Throwable $e) {
            $this->logger?->error('Database query failed in update', [
                'id'    => $id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function delete(int $id): bool
    {
        try {
            $stmt = $this->db->prepare("DELETE FROM `todos` WHERE `id` = :id");
            $stmt->execute([':id' => $id]);

            return $stmt->rowCount() > 0;
        } catch (Throwable $e) {
            $this->logger?->error('Database query failed in delete', [
                'id'    => $id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
