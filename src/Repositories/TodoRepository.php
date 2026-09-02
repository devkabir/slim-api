<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Config\Database;
use App\Models\Todo;
use PDO;

class TodoRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * @return Todo[]
     */
    public function findAll(?bool $completed = null): array
    {
        $sql = "SELECT * FROM `todos`";
        $params = [];

        if ($completed !== null) {
            $sql .= " WHERE `completed` = :completed";
            $params[':completed'] = $completed ? 1 : 0;
        }

        $sql .= " ORDER BY `created_at` DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        return array_map(fn($row) => Todo::fromArray($row), $rows);
    }

    public function findById(int $id): ?Todo
    {
        $stmt = $this->db->prepare("SELECT * FROM `todos` WHERE `id` = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        if (!$row) {
            return null;
        }

        return Todo::fromArray($row);
    }

    public function create(string $title, ?string $description = null, bool $completed = false): Todo
    {
        $stmt = $this->db->prepare(
            "INSERT INTO `todos` (`title`, `description`, `completed`) VALUES (:title, :description, :completed)"
        );

        $stmt->execute([
            ':title' => $title,
            ':description' => $description,
            ':completed' => $completed ? 1 : 0,
        ]);

        $id = (int)$this->db->lastInsertId();
        return $this->findById($id);
    }

    public function update(int $id, array $data): ?Todo
    {
        $fields = [];
        $params = [':id' => $id];

        if (array_key_exists('title', $data)) {
            $fields[] = "`title` = :title";
            $params[':title'] = $data['title'];
        }

        if (array_key_exists('description', $data)) {
            $fields[] = "`description` = :description";
            $params[':description'] = $data['description'];
        }

        if (array_key_exists('completed', $data)) {
            $fields[] = "`completed` = :completed";
            $params[':completed'] = $data['completed'] ? 1 : 0;
        }

        if (empty($fields)) {
            return $this->findById($id);
        }

        $sql = "UPDATE `todos` SET " . implode(', ', $fields) . " WHERE `id` = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $this->findById($id);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM `todos` WHERE `id` = :id");
        $stmt->execute([':id' => $id]);

        return $stmt->rowCount() > 0;
    }
}
